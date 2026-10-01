<?php

namespace App\Actions\Fund;

use App\Enums\TransactionStatus;
use App\Models\ContributionPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
     * @return LengthAwarePaginator<int, array{id: int, name: string, email: string, expected_cents: int, approved_cents: int, pending_cents: int, status: string, transaction_id: int|null}>
     */
    public function participants(ContributionPeriod $period, string $status = ''): LengthAwarePaginator
    {
        $cutoff = $this->registrationCutoff($period);
        $allocations = DB::table('transaction_allocations as allocation')
            ->join('fund_transactions as transaction', 'transaction.id', '=', 'allocation.fund_transaction_id')
            ->where('allocation.contribution_period_id', $period->id)
            ->whereNull('transaction.superseded_by_id')
            ->groupBy('transaction.user_id')->select('transaction.user_id')
            ->selectRaw('SUM(CASE WHEN transaction.status = ? THEN allocation.amount_cents ELSE 0 END) as approved_cents', [TransactionStatus::Approved->value])
            ->selectRaw('MAX(CASE WHEN transaction.status = ? THEN transaction.id END) as approved_id', [TransactionStatus::Approved->value])
            ->selectRaw('MAX(CASE WHEN transaction.status = ? THEN transaction.id END) as pending_id', [TransactionStatus::Pending->value]);
        $participants = DB::table('users')->leftJoinSub($allocations, 'payments', 'payments.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.email', 'payments.approved_id', 'payments.pending_id')
            ->selectRaw('COALESCE(payments.approved_cents, 0) as approved_cents')
            ->selectRaw('CASE WHEN users.created_at < ? THEN ? ELSE 0 END as expected_cents', [$cutoff, $period->amount_cents])
            ->selectRaw('CASE WHEN users.created_at >= ? THEN ? WHEN COALESCE(payments.approved_cents, 0) >= ? THEN ? WHEN payments.pending_id IS NOT NULL THEN ? ELSE ? END as status', [$cutoff, 'not_applicable', $period->amount_cents, 'paid', 'pending', 'unpaid']);

        return DB::query()->fromSub($participants, 'participants')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString()
            ->through(function (object $person): array {
                $expected = (int) $person->expected_cents;
                $approved = (int) $person->approved_cents;
                $transactionId = $person->pending_id ?? $person->approved_id;

                return [
                    'id' => (int) $person->id, 'name' => $person->name, 'email' => $person->email,
                    'expected_cents' => $expected, 'approved_cents' => $approved,
                    'pending_cents' => max(0, $expected - $approved), 'status' => $person->status,
                    'transaction_id' => $transactionId === null ? null : (int) $transactionId,
                ];
            });
    }

    private function registrationCutoff(ContributionPeriod $period): CarbonImmutable
    {
        return CarbonImmutable::parse($period->month->format('Y-m-d').' 00:00:00', 'America/Guayaquil')
            ->addDays(5)->utc();
    }
}
