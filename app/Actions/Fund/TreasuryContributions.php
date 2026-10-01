<?php

namespace App\Actions\Fund;

use App\Enums\TransactionStatus;
use App\Models\ContributionPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TreasuryContributions
{
    /**
     * @return list<array{month: string, expected_cents: int, received_cents: int}>
     */
    public function chart(CarbonImmutable $today): array
    {
        $periods = ContributionPeriod::query()->where('month', '<=', $today->startOfMonth()->toDateString())
            ->orderByDesc('month')->limit(6)->get()->reverse();
        if ($periods->isEmpty()) {
            return [];
        }

        $received = DB::table('transaction_allocations as allocation')
            ->join('fund_transactions as transaction', 'transaction.id', '=', 'allocation.fund_transaction_id')
            ->whereIn('allocation.contribution_period_id', $periods->pluck('id'))
            ->where('transaction.status', TransactionStatus::Approved->value)
            ->whereNull('transaction.superseded_by_id')
            ->groupBy('allocation.contribution_period_id')
            ->selectRaw('allocation.contribution_period_id, SUM(allocation.amount_cents) as total')
            ->pluck('total', 'contribution_period_id');

        return array_values($periods->map(function (ContributionPeriod $period) use ($received): array {
            $cutoff = $this->registrationCutoff($period);

            return [
                'month' => $period->month->format('Y-m'),
                'expected_cents' => User::query()->where('created_at', '<', $cutoff)->count() * $period->amount_cents,
                'received_cents' => (int) ($received[$period->id] ?? 0),
            ];
        })->all());
    }

    /**
     * @return LengthAwarePaginator<int, array{id: int, name: string, email: string, pending_transaction_id: int|null}>
     */
    public function unpaid(ContributionPeriod $period): LengthAwarePaginator
    {
        $cutoff = $this->registrationCutoff($period);

        return User::query()->select('users.id', 'users.name', 'users.email')
            ->addSelect(['pending_transaction_id' => DB::table('fund_transactions as pending')
                ->join('transaction_allocations as pending_allocation', 'pending_allocation.fund_transaction_id', '=', 'pending.id')
                ->select('pending.id')
                ->whereColumn('pending.user_id', 'users.id')
                ->where('pending.status', TransactionStatus::Pending->value)
                ->where('pending_allocation.contribution_period_id', $period->id)
                ->orderByDesc('pending.id')->limit(1)])
            ->where('users.created_at', '<', $cutoff)
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')
                ->from('transaction_allocations as paid_allocation')
                ->join('fund_transactions as paid', 'paid.id', '=', 'paid_allocation.fund_transaction_id')
                ->whereColumn('paid.user_id', 'users.id')
                ->where('paid_allocation.contribution_period_id', $period->id)
                ->where('paid.status', TransactionStatus::Approved->value)
                ->whereNull('paid.superseded_by_id'))
            ->orderBy('users.name')->orderBy('users.id')
            ->paginate(10, ['*'], 'unpaid_page')
            ->through(function (User $user): array {
                $pendingId = $user->getAttribute('pending_transaction_id');

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'pending_transaction_id' => $pendingId === null ? null : (int) $pendingId,
                ];
            });
    }

    private function registrationCutoff(ContributionPeriod $period): CarbonImmutable
    {
        return CarbonImmutable::parse($period->month->format('Y-m-d').' 00:00:00', 'America/Guayaquil')
            ->addDays(5)->utc();
    }
}
