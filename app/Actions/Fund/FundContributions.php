<?php

namespace App\Actions\Fund;

use App\Enums\LoanStatus;
use App\Enums\TransactionStatus;
use App\Models\ContributionPeriod;
use App\Models\LoanInstallment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @phpstan-type Allocation array{contribution_period_id: int|null, loan_installment_id: int|null, amount_cents: int, capital_cents: int, interest_cents: int} */
class FundContributions
{
    public function __construct(private RunFundOperation $operations, private RecordFundEvent $events) {}

    public function setPeriod(User $actor, string $key, string $month, string $amount): int
    {
        $date = CarbonImmutable::createFromFormat('!Y-m', $month, 'America/Guayaquil');
        $cents = Money::cents($amount);

        return $this->operations->handle($actor, 'contribution.configure', $key, compact('month', 'amount'), function () use ($actor, $date, $cents): int {
            return $this->configurePeriod($actor, $date, $cents)->id;
        }, 'treasurer');
    }

    public function setRange(User $actor, string $key, string $firstMonth, string $lastMonth, string $amount): int
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $firstMonth, 'America/Guayaquil');
        $end = CarbonImmutable::createFromFormat('!Y-m', $lastMonth, 'America/Guayaquil');
        $months = ($end->year - $start->year) * 12 + $end->month - $start->month + 1;
        if ($months < 1 || $months > 120) {
            throw ValidationException::withMessages(['last_month' => 'Selecciona un rango de entre uno y 120 meses consecutivos.']);
        }
        $cents = Money::cents($amount);

        return $this->operations->handle($actor, 'contribution.configure-range', $key, compact('firstMonth', 'lastMonth', 'amount'), function () use ($actor, $start, $months, $cents): int {
            for ($offset = 0; $offset < $months; $offset++) {
                $month = $start->addMonths($offset);
                try {
                    $this->configurePeriod($actor, $month, $cents);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['first_month' => $month->format('Y-m').': '.($exception->errors()['month'][0] ?? 'No se pudo configurar esta cuota.')]);
                }
            }

            return $months;
        }, 'treasurer');
    }

    private function configurePeriod(User $actor, CarbonImmutable $date, int $cents): ContributionPeriod
    {
        $period = ContributionPeriod::query()->where('month', $date->toDateString())->first();
        if ($period !== null && ($period->locked_at !== null || $date->lessThanOrEqualTo(CarbonImmutable::now('America/Guayaquil')->startOfMonth()))) {
            throw ValidationException::withMessages(['month' => 'Solo puedes modificar meses futuros que aún no se hayan utilizado.']);
        }
        $firstConfigured = ContributionPeriod::query()->orderBy('month')->first();
        if ($period === null && $firstConfigured !== null && $date->lessThan($firstConfigured->month) && ContributionPeriod::query()->whereNotNull('locked_at')->exists()) {
            throw ValidationException::withMessages(['month' => 'No puedes adelantar el primer período después de registrar aportes.']);
        }
        $previous = $period?->amount_cents;
        $period ??= new ContributionPeriod(['month' => $date->toDateString()]);
        $period->amount_cents = $cents;
        $period->save();
        $this->events->handle($actor, 'contribution.configured', 'period', $period->id, ['previous_cents' => $previous, 'amount_cents' => $cents, 'month' => $date->format('Y-m')]);

        return $period;
    }

    /**
     * @param  list<int>  $periodIds
     * @param  list<int>  $installmentIds
     * @return list<Allocation>
     */
    public function allocations(User $user, array $periodIds, array $installmentIds, ?int $ignoreTransaction = null): array
    {
        $rows = [];
        if (count(array_unique($periodIds)) !== count($periodIds) || count(array_unique($installmentIds)) !== count($installmentIds)) {
            throw ValidationException::withMessages(['allocations' => 'No puedes seleccionar un mes o cuota más de una vez.']);
        }
        if ($periodIds !== []) {
            $paid = $this->paidPeriodIds($user, $ignoreTransaction);
            $paidMonths = ContributionPeriod::query()->whereIn('id', $paid)->pluck('month')->map(fn ($month): string => CarbonImmutable::parse($month)->format('Y-m'))->all();
            $expected = ContributionPeriod::query()->orderBy('month')->first()?->month;
            if ($expected === null) {
                throw ValidationException::withMessages(['period_ids' => 'El tesorero todavía no ha definido el primer período de aportes.']);
            }
            while (in_array($expected->format('Y-m'), $paidMonths, true)) {
                $expected = $expected->addMonth();
            }
            $periods = ContributionPeriod::query()->whereIn('id', $periodIds)->orderBy('month')->get();
            if ($periods->count() !== count($periodIds)) {
                throw ValidationException::withMessages(['period_ids' => 'Todos los meses deben tener una cuota configurada.']);
            }
            foreach ($periods as $period) {
                if ($period->month->format('Y-m') !== $expected->format('Y-m') || $period->month->year > CarbonImmutable::now('America/Guayaquil')->year || in_array($period->id, $paid, true)) {
                    throw ValidationException::withMessages(['period_ids' => 'Selecciona meses consecutivos desde el más antiguo pendiente y hasta diciembre del año actual.']);
                }
                $rows[] = ['contribution_period_id' => $period->id, 'loan_installment_id' => null, 'amount_cents' => $period->amount_cents, 'capital_cents' => 0, 'interest_cents' => 0];
                $expected = $expected->addMonth();
            }
        }
        if ($installmentIds !== []) {
            $paid = $this->paidInstallmentIds($ignoreTransaction);
            $installments = LoanInstallment::query()->with('loan')->whereIn('id', $installmentIds)->orderBy('number')->get();
            if ($installments->count() !== count($installmentIds)) {
                throw ValidationException::withMessages(['installment_ids' => 'La selección contiene cuotas inexistentes.']);
            }
            foreach ($installments->groupBy('loan_id') as $selected) {
                $loan = $selected->first()->loan;
                if ($loan->user_id !== $user->id || $loan->status !== LoanStatus::Disbursed) {
                    throw ValidationException::withMessages(['installment_ids' => 'Solo puedes pagar tus préstamos desembolsados.']);
                }
                $expectedIds = $loan->installments()->whereNotIn('id', $paid)->limit($selected->count())->pluck('id')->map(fn ($id): int => (int) $id)->all();
                if ($selected->modelKeys() !== $expectedIds) {
                    throw ValidationException::withMessages(['installment_ids' => 'Selecciona cuotas completas y consecutivas desde la más antigua pendiente.']);
                }
                foreach ($selected as $installment) {
                    $rows[] = ['contribution_period_id' => null, 'loan_installment_id' => $installment->id, 'amount_cents' => Money::sum([$installment->capital_cents, $installment->interest_cents]), 'capital_cents' => $installment->capital_cents, 'interest_cents' => $installment->interest_cents];
                }
            }
        }

        return $rows;
    }

    /** @return list<int> */
    public function paidPeriodIds(User $user, ?int $ignoreTransaction = null): array
    {
        return array_values($this->activeAllocations($ignoreTransaction)->where('t.user_id', $user->id)->whereNotNull('a.contribution_period_id')->pluck('a.contribution_period_id')->map(fn ($id): int => (int) $id)->all());
    }

    public function activeAllocations(?int $ignoreTransaction = null): Builder
    {
        return DB::table('transaction_allocations as a')->join('fund_transactions as t', 't.id', '=', 'a.fund_transaction_id')
            ->where('t.status', TransactionStatus::Approved->value)->whereNull('t.superseded_by_id')
            ->when($ignoreTransaction !== null, fn (Builder $query): Builder => $query->where('t.id', '!=', $ignoreTransaction));
    }

    /** @return list<int> */
    public function paidInstallmentIds(?int $ignoreTransaction = null): array
    {
        return array_values($this->activeAllocations($ignoreTransaction)->whereNotNull('a.loan_installment_id')->pluck('a.loan_installment_id')->map(fn ($id): int => (int) $id)->all());
    }
}
