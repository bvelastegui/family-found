<?php

namespace App\Actions\Fund;

use App\Enums\LoanStatus;
use App\Enums\TransactionStatus;
use App\Models\Bank;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type LoanData array{user_id: int, amount: string, monthly_rate: string, term_months: int}
 * @phpstan-type DisbursementData array{bank_id: int, reference: string, transaction_date: string, amount: string}
 *
 * @phpstan-import-type EvidenceData from StoreFundEvidence
 */
class FundLoans
{
    public function __construct(
        private RunFundOperation $operations,
        private RecordFundEvent $events,
        private FundBalances $balances,
        private WriteJournalEntry $journal,
        private CalculateAmortization $amortization,
        private StoreFundEvidence $evidences,
    ) {}

    /** @param LoanData $data */
    public function reserve(User $actor, string $key, array $data): int
    {
        return $this->operations->handle($actor, 'loan.reserve', $key, $data, fn (): int => $this->createReservation($actor, $data)->id, 'treasurer');
    }

    public function cancel(User $actor, string $key, int $loanId, string $reason): int
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Indica el motivo de la cancelación.']);
        }

        return $this->operations->handle($actor, 'loan.cancel', $key, ['id' => $loanId, 'reason' => $reason], function () use ($actor, $loanId, $reason): int {
            $loan = Loan::query()->findOrFail($loanId);
            abort_unless($loan->status === LoanStatus::Reserved, 409, 'Solo se cancela una reserva no desembolsada.');
            $loan->update(['status' => LoanStatus::Cancelled]);
            $this->events->handle($actor, 'loan.cancelled', 'loan', $loanId, ['reason' => $reason]);

            return $loanId;
        }, 'treasurer');
    }

    /** @param DisbursementData $data */
    public function disburse(User $actor, string $key, int $loanId, array $data, UploadedFile $file): int
    {
        return $this->evidences->using($file, fn (array $evidence): int => $this->operations->handle($actor, 'loan.disburse', $key, ['id' => $loanId, ...$data, 'evidence_sha256' => $evidence['sha256']], function () use ($actor, $loanId, $data, $evidence): int {
            $loan = Loan::query()->findOrFail($loanId);
            $this->post($actor, $loan, $data, $evidence);

            return $loanId;
        }, 'treasurer'));
    }

    /** @param array{amount: string, monthly_rate: string, term_months: int, bank_id: int, reference: string, transaction_date: string, reason: string} $data */
    public function correct(User $actor, string $key, int $loanId, array $data, UploadedFile $file): int
    {
        if (trim($data['reason']) === '') {
            throw ValidationException::withMessages(['reason' => 'Indica el motivo de la corrección.']);
        }

        return $this->evidences->using($file, fn (array $evidence): int => $this->operations->handle($actor, 'loan.correct', $key, ['id' => $loanId, ...$data, 'evidence_sha256' => $evidence['sha256']], function () use ($actor, $loanId, $data, $evidence): int {
            $original = Loan::query()->findOrFail($loanId);
            abort_unless($original->status === LoanStatus::Disbursed, 409, 'Solo se corrige un desembolso vigente.');
            if ($this->hasDependentPayments($original)) {
                throw ValidationException::withMessages(['correction' => 'El préstamo tiene pagos registrados sobre su tabla.']);
            }
            $entry = DB::table('journal_entries')->where('loan_id', $loanId)->value('id');
            abort_if($entry === null, 409, 'No se encontró el asiento del desembolso.');
            $reversal = $this->journal->reverse($actor, (int) $entry);
            $replacement = $this->createReservation($actor, ['user_id' => $original->user_id, 'amount' => $data['amount'], 'monthly_rate' => $data['monthly_rate'], 'term_months' => $data['term_months']]);
            $original->update(['status' => LoanStatus::Superseded, 'superseded_by_id' => $replacement->id]);
            $this->post($actor, $replacement, ['amount' => $data['amount'], 'bank_id' => $data['bank_id'], 'reference' => $data['reference'], 'transaction_date' => $data['transaction_date']], $evidence);
            if ($this->balances->summary()['available'] < 0) {
                throw ValidationException::withMessages(['correction' => 'El fondo no tiene saldo suficiente para el reemplazo.']);
            }
            $this->events->handle($actor, 'loan.corrected', 'loan', $loanId, ['reason' => $data['reason'], 'replacement_id' => $replacement->id, 'reversal_entry_id' => $reversal]);

            return $replacement->id;
        }, 'treasurer'));
    }

    public function hasDependentPayments(Loan $loan): bool
    {
        return DB::table('transaction_allocations as a')
            ->join('loan_installments as i', 'i.id', '=', 'a.loan_installment_id')
            ->join('fund_transactions as t', 't.id', '=', 'a.fund_transaction_id')
            ->where('i.loan_id', $loan->id)
            ->whereIn('t.status', [TransactionStatus::Pending->value, TransactionStatus::Approved->value])
            ->whereNull('t.superseded_by_id')
            ->exists();
    }

    /** @param LoanData $data */
    private function createReservation(User $actor, array $data): Loan
    {
        User::query()->findOrFail($data['user_id']);
        $principal = Money::cents($data['amount']);
        if ($principal > $this->balances->summary()['available']) {
            throw ValidationException::withMessages(['amount' => 'El fondo no tiene dinero disponible suficiente.']);
        }
        $this->amortization->handle($principal, $data['monthly_rate'], $data['term_months'], CarbonImmutable::now('America/Guayaquil')->startOfDay());
        $loan = Loan::query()->create(['user_id' => $data['user_id'], 'principal_cents' => $principal, 'monthly_rate' => $data['monthly_rate'], 'term_months' => $data['term_months'], 'status' => LoanStatus::Reserved]);
        $this->events->handle($actor, 'loan.reserved', 'loan', $loan->id, ['user_id' => $loan->user_id, 'principal_cents' => $principal, 'monthly_rate' => $loan->monthly_rate, 'term_months' => $loan->term_months]);

        return $loan;
    }

    /**
     * @param  DisbursementData  $data
     * @param  EvidenceData  $evidence
     */
    private function post(User $actor, Loan $loan, array $data, array $evidence): void
    {
        abort_unless($loan->status === LoanStatus::Reserved, 409, 'El préstamo no tiene una reserva activa.');
        if (Money::cents($data['amount']) !== $loan->principal_cents) {
            throw ValidationException::withMessages(['amount' => 'El desembolso debe coincidir con el monto reservado.']);
        }
        $bank = Bank::query()->findOrFail($data['bank_id']);
        if (! $bank->active) {
            throw ValidationException::withMessages(['bank_id' => 'Selecciona un banco activo.']);
        }
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $data['transaction_date'], 'America/Guayaquil');
        if ($date->isAfter(CarbonImmutable::now('America/Guayaquil')->endOfDay())) {
            throw ValidationException::withMessages(['transaction_date' => 'La fecha del desembolso no puede ser futura.']);
        }
        $schedule = $this->amortization->handle($loan->principal_cents, $loan->monthly_rate, $loan->term_months, $date);
        $file = $this->evidences->persist($actor, $loan->user, $evidence);
        $loan->update(['bank_id' => $bank->id, 'bank_name' => $bank->name, 'reference' => $data['reference'], 'disbursed_on' => $date->toDateString(), 'evidence_id' => $file->id, 'status' => LoanStatus::Disbursed]);
        foreach ($schedule as $row) {
            LoanInstallment::query()->create(['loan_id' => $loan->id, ...$row]);
        }
        $entry = $this->journal->handle($actor, [
            ['account' => 'loan_principal', 'side' => 'debit', 'amount_cents' => $loan->principal_cents, 'user_id' => $loan->user_id, 'loan_id' => $loan->id],
            ['account' => 'cash', 'side' => 'credit', 'amount_cents' => $loan->principal_cents, 'user_id' => $loan->user_id, 'loan_id' => $loan->id],
        ], loanId: $loan->id);
        $this->events->handle($actor, 'loan.disbursed', 'loan', $loan->id, ['journal_entry_id' => $entry, 'evidence_id' => $file->id, 'date' => $date->toDateString()]);
    }
}
