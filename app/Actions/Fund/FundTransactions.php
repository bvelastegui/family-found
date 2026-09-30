<?php

namespace App\Actions\Fund;

use App\Enums\TransactionStatus;
use App\Models\Bank;
use App\Models\ContributionPeriod;
use App\Models\FundTransaction;
use App\Models\LoanInstallment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type TransactionData array{bank_id: int, reference: string, transaction_date: string, amount: string, period_ids: list<int>, installment_ids: list<int>, reason?: string}
 *
 * @phpstan-import-type EvidenceData from StoreFundEvidence
 */
class FundTransactions
{
    public function __construct(
        private RunFundOperation $operations,
        private FundContributions $contributions,
        private StoreFundEvidence $evidences,
        private WriteJournalEntry $journal,
        private RecordFundEvent $events,
        private FundBalances $balances,
    ) {}

    /** @param TransactionData $data */
    public function register(User $actor, string $key, array $data, UploadedFile $file): int
    {
        return $this->evidences->using($file, fn (array $evidence): int => $this->operations->handle(
            $actor, 'transaction.register', $key, [...$data, 'evidence_sha256' => $evidence['sha256']],
            fn (): int => $this->record($actor, $actor, $data, $evidence)->id,
        ));
    }

    public function approve(User $actor, string $key, int $transactionId): int
    {
        return $this->operations->handle($actor, 'transaction.approve', $key, ['id' => $transactionId], function () use ($actor, $transactionId): int {
            $transaction = FundTransaction::query()->findOrFail($transactionId);
            abort_if($transaction->superseded_by_id !== null, 409, 'La transacción ya fue sustituida.');
            abort_unless($transaction->status === TransactionStatus::Pending || $transaction->status === TransactionStatus::Approved, 409, 'Un registro rechazado no puede aprobarse.');
            if ($transaction->status === TransactionStatus::Pending) {
                $this->post($actor, $transaction);
            }

            return $transactionId;
        }, 'treasurer');
    }

    public function reject(User $actor, string $key, int $transactionId, string $reason): int
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Indica el motivo del rechazo.']);
        }

        return $this->operations->handle($actor, 'transaction.reject', $key, ['id' => $transactionId, 'reason' => $reason], function () use ($actor, $transactionId, $reason): int {
            $transaction = FundTransaction::query()->findOrFail($transactionId);
            abort_unless($transaction->status === TransactionStatus::Pending, 409, 'Solo se puede rechazar un registro pendiente.');
            $transaction->update(['status' => TransactionStatus::Rejected, 'active_reference' => null, 'pending_contributor_id' => null]);
            $this->events->handle($actor, 'transaction.rejected', 'transaction', $transactionId, ['reason' => $reason]);

            return $transactionId;
        }, 'treasurer');
    }

    /** @param TransactionData $data */
    public function correct(User $actor, string $key, int $transactionId, array $data, UploadedFile $file): int
    {
        if (trim($data['reason'] ?? '') === '') {
            throw ValidationException::withMessages(['reason' => 'Indica el motivo de la corrección.']);
        }

        return $this->evidences->using($file, fn (array $evidence): int => $this->operations->handle($actor, 'transaction.correct', $key, ['id' => $transactionId, ...$data, 'evidence_sha256' => $evidence['sha256']], function () use ($actor, $transactionId, $data, $evidence): int {
            $original = FundTransaction::query()->findOrFail($transactionId);
            abort_unless($original->status === TransactionStatus::Approved && $original->superseded_by_id === null, 409, 'Solo se corrige una operación aprobada vigente.');
            $originalInstallments = LoanInstallment::query()->whereIn('id', DB::table('transaction_allocations')->where('fund_transaction_id', $original->id)->whereNotNull('loan_installment_id')->pluck('loan_installment_id'))->get();
            foreach ($originalInstallments->groupBy('loan_id') as $installments) {
                $later = $this->contributions->activeAllocations($original->id)->join('loan_installments as i', 'i.id', '=', 'a.loan_installment_id')->where('i.loan_id', $installments->first()->loan_id)->where('i.number', '>', $installments->min('number'))->exists();
                if ($later) {
                    throw ValidationException::withMessages(['correction' => 'Existen pagos posteriores dependientes en el préstamo.']);
                }
            }
            $entryId = DB::table('journal_entries')->where('fund_transaction_id', $original->id)->value('id');
            abort_if($entryId === null, 409, 'No se encontró el asiento original.');
            $original->update(['active_reference' => null]);
            $replacement = $this->record($actor, $original->user, $data, $evidence, $original->id);
            $original->update(['superseded_by_id' => $replacement->id]);
            $reversal = $this->journal->reverse($actor, (int) $entryId);
            $this->post($actor, $replacement);
            $paidMonths = ContributionPeriod::query()->whereIn('id', $this->contributions->paidPeriodIds($original->user))->orderBy('month')->get();
            $expected = $original->user->created_at?->toImmutable()->setTimezone('America/Guayaquil')->startOfMonth();
            foreach ($paidMonths as $period) {
                if ($expected === null || $period->month->format('Y-m') !== $expected->format('Y-m')) {
                    throw ValidationException::withMessages(['correction' => 'La corrección dejaría un salto entre aportes ya aprobados.']);
                }
                $expected = $expected->addMonth();
            }
            $summary = $this->balances->summary();
            if ($summary['available'] < 0 || $summary['cash'] < 0 || $summary['principal'] < 0) {
                throw ValidationException::withMessages(['correction' => 'El fondo no tiene saldo suficiente para esta corrección.']);
            }
            $this->events->handle($actor, 'transaction.corrected', 'transaction', $original->id, ['reason' => $data['reason'], 'replacement_id' => $replacement->id, 'reversal_entry_id' => $reversal]);

            return $replacement->id;
        }, 'treasurer'));
    }

    /**
     * @param  TransactionData  $data
     * @param  EvidenceData  $evidence
     */
    private function record(User $actor, User $owner, array $data, array $evidence, ?int $correctedId = null): FundTransaction
    {
        $bank = Bank::query()->findOrFail($data['bank_id']);
        if (! $bank->active) {
            throw ValidationException::withMessages(['bank_id' => 'Selecciona un banco activo.']);
        }
        $normalized = Str::upper(preg_replace('/\s+/u', '', $data['reference']) ?? '');
        if ($normalized === '' || mb_strlen($normalized) > 191) {
            throw ValidationException::withMessages(['reference' => 'El número de comprobante no es válido.']);
        }
        if (FundTransaction::query()->where('bank_id', $bank->id)->where('active_reference', $normalized)->exists()) {
            throw ValidationException::withMessages(['reference' => 'Este banco y comprobante ya tienen un registro pendiente o aprobado.']);
        }
        if ($data['period_ids'] !== [] && FundTransaction::query()->where('pending_contributor_id', $owner->id)->exists()) {
            throw ValidationException::withMessages(['period_ids' => 'Espera la aprobación o rechazo de tu aporte pendiente.']);
        }
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $data['transaction_date'], 'America/Guayaquil');
        if ($date->isAfter(CarbonImmutable::now('America/Guayaquil')->endOfDay())) {
            throw ValidationException::withMessages(['transaction_date' => 'La fecha bancaria no puede ser futura.']);
        }
        $amount = Money::cents($data['amount']);
        $allocations = $this->contributions->allocations($owner, $data['period_ids'], $data['installment_ids'], $correctedId);
        if ($amount !== Money::sum(array_column($allocations, 'amount_cents'))) {
            throw ValidationException::withMessages(['amount' => 'El monto debe coincidir exactamente con los aportes y cuotas seleccionados.']);
        }
        $previousRejected = FundTransaction::query()->where('user_id', $owner->id)->where('bank_id', $bank->id)->where('normalized_reference', $normalized)->where('status', TransactionStatus::Rejected)->latest('id')->value('id');
        $file = $this->evidences->persist($actor, $owner, $evidence);
        $transaction = FundTransaction::query()->create([
            'user_id' => $owner->id, 'bank_id' => $bank->id, 'bank_name' => $bank->name, 'reference' => $data['reference'],
            'normalized_reference' => $normalized, 'active_reference' => $normalized, 'transaction_date' => $date->toDateString(),
            'amount_cents' => $amount, 'evidence_id' => $file->id, 'status' => TransactionStatus::Pending,
            'pending_contributor_id' => $data['period_ids'] === [] ? null : $owner->id,
            'corrected_from_id' => $correctedId ?? $previousRejected,
        ]);
        foreach ($allocations as $allocation) {
            DB::table('transaction_allocations')->insert(['fund_transaction_id' => $transaction->id, ...$allocation]);
        }
        ContributionPeriod::query()->whereIn('id', $data['period_ids'])->whereNull('locked_at')->update(['locked_at' => now()]);
        $this->events->handle($actor, 'transaction.registered', 'transaction', $transaction->id, ['user_id' => $owner->id, 'amount_cents' => $amount, 'evidence_id' => $file->id, 'corrected_from_id' => $transaction->corrected_from_id]);

        return $transaction;
    }

    private function post(User $actor, FundTransaction $transaction): void
    {
        abort_unless($transaction->status === TransactionStatus::Pending, 409, 'Solo se puede aprobar un registro pendiente.');
        $stored = DB::table('transaction_allocations')->where('fund_transaction_id', $transaction->id)->get();
        $periodIds = array_values($stored->whereNotNull('contribution_period_id')->pluck('contribution_period_id')->map(fn ($id): int => (int) $id)->all());
        $installmentIds = array_values($stored->whereNotNull('loan_installment_id')->pluck('loan_installment_id')->map(fn ($id): int => (int) $id)->all());
        $allocations = $this->contributions->allocations($transaction->user, $periodIds, $installmentIds);
        if (Money::sum(array_column($allocations, 'amount_cents')) !== $transaction->amount_cents) {
            throw ValidationException::withMessages(['amount' => 'Las asignaciones ya no coinciden con el monto registrado.']);
        }
        $loanIds = LoanInstallment::query()->whereIn('id', $installmentIds)->pluck('loan_id', 'id');
        $lines = [['account' => 'cash', 'side' => 'debit', 'amount_cents' => $transaction->amount_cents, 'user_id' => $transaction->user_id]];
        foreach ($allocations as $allocation) {
            if ($allocation['contribution_period_id'] !== null) {
                $lines[] = ['account' => 'contributions', 'side' => 'credit', 'amount_cents' => $allocation['amount_cents'], 'user_id' => $transaction->user_id];
            } else {
                $loanId = (int) $loanIds[$allocation['loan_installment_id']];
                foreach (['capital_cents' => 'loan_principal', 'interest_cents' => 'interest'] as $field => $account) {
                    if ($allocation[$field] > 0) {
                        $lines[] = ['account' => $account, 'side' => 'credit', 'amount_cents' => $allocation[$field], 'user_id' => $transaction->user_id, 'loan_id' => $loanId];
                    }
                }
            }
        }
        $entry = $this->journal->handle($actor, $lines, transactionId: $transaction->id);
        $transaction->update(['status' => TransactionStatus::Approved, 'pending_contributor_id' => null]);
        $this->events->handle($actor, 'transaction.approved', 'transaction', $transaction->id, ['journal_entry_id' => $entry]);
    }
}
