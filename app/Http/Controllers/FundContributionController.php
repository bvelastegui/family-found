<?php

namespace App\Http\Controllers;

use App\Actions\Fund\FundBalances;
use App\Enums\JournalAccount;
use App\Enums\TransactionStatus;
use App\Models\ContributionPeriod;
use App\Models\FundTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FundContributionController extends Controller
{
    public function index(Request $request, FundBalances $balances): Response
    {
        $user = $request->user();
        $currentYear = now('America/Guayaquil')->year;
        $firstMonth = ContributionPeriod::query()->min('month');
        $firstYear = $firstMonth === null ? $currentYear : min((int) substr((string) $firstMonth, 0, 4), $currentYear);
        $validated = $request->validate(['year' => ['sometimes', 'integer']]);
        $year = isset($validated['year']) ? (int) $validated['year'] : $currentYear;
        abort_unless($year >= $firstYear && $year <= $currentYear, 404);

        $configured = ContributionPeriod::query()
            ->whereBetween('month', ["{$year}-01-01", "{$year}-12-31"])
            ->orderBy('month')->get()->keyBy(fn (ContributionPeriod $period): string => $period->month->format('Y-m'));
        $approved = DB::table('transaction_allocations as a')
            ->join('fund_transactions as t', 't.id', '=', 'a.fund_transaction_id')
            ->where('t.user_id', $user->id)->where('t.status', TransactionStatus::Approved->value)
            ->whereNull('t.superseded_by_id')->whereIn('a.contribution_period_id', $configured->pluck('id'))
            ->whereNotNull('a.contribution_period_id')->get(['a.contribution_period_id', 't.id as transaction_id'])
            ->keyBy('contribution_period_id');
        $pending = FundTransaction::query()->where('pending_contributor_id', $user->id)->first();
        $pendingIds = $pending ? DB::table('transaction_allocations')->where('fund_transaction_id', $pending->id)
            ->whereNotNull('contribution_period_id')->pluck('contribution_period_id')->all() : [];
        $currentMonth = now('America/Guayaquil')->format('Y-m');
        $periods = collect(range(1, 12))->map(function (int $month) use ($year, $firstMonth, $currentMonth, $configured, $approved, $pending, $pendingIds): array {
            $key = sprintf('%04d-%02d', $year, $month);
            $period = $configured->get($key);

            if ($period === null) {
                return [
                    'id' => null, 'month' => $key, 'amount_cents' => null,
                    'status' => $firstMonth !== null && $key < substr((string) $firstMonth, 0, 7) ? 'before_start' : 'unconfigured',
                    'transaction_id' => null,
                ];
            }

            $approvedTransaction = $approved->get($period->id);
            $pendingTransaction = $pending !== null && in_array($period->id, $pendingIds);

            return [
                'id' => $period->id, 'month' => $key, 'amount_cents' => $period->amount_cents,
                'status' => $approvedTransaction !== null ? 'paid' : ($pendingTransaction ? 'pending' : ($key > $currentMonth ? 'upcoming' : 'unpaid')),
                'transaction_id' => $approvedTransaction !== null ? (int) $approvedTransaction->transaction_id : ($pendingTransaction ? $pending->id : null),
            ];
        });

        return Inertia::render('fund/Contributions', [
            'periods' => $periods,
            'year' => $year,
            'years' => range($firstYear, $currentYear),
            'yearTotalCents' => $periods->where('status', 'paid')->sum('amount_cents'),
            'hasConfiguredPeriods' => $firstMonth !== null,
            'totalCents' => $balances->account(JournalAccount::Contributions, $user->id),
            'pendingTransactionId' => $pending?->id,
        ]);
    }
}
