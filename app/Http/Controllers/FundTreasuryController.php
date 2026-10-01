<?php

namespace App\Http\Controllers;

use App\Actions\Fund\FundBalances;
use App\Actions\Fund\TreasuryContributions;
use App\Enums\TransactionStatus;
use App\Models\ContributionPeriod;
use App\Models\FundSetting;
use App\Models\FundTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FundTreasuryController extends Controller
{
    public function index(Request $request, FundBalances $balances, TreasuryContributions $contributions): Response
    {
        abort_unless(FundSetting::current()->isTreasurer($request->user()), 403);
        $today = CarbonImmutable::now('America/Guayaquil');

        return Inertia::render('fund/Treasury', [
            'pendingCount' => FundTransaction::query()->where('status', TransactionStatus::Pending)->count(),
            'balances' => $balances->summary(),
            'contributionChart' => $contributions->chart($today),
        ]);
    }

    public function contributions(Request $request, TreasuryContributions $contributions): Response
    {
        abort_unless(FundSetting::current()->isTreasurer($request->user()), 403);
        $validated = $request->validate([
            'month' => ['sometimes', 'date_format:Y-m'],
            'status' => ['sometimes', 'nullable', 'in:paid,pending,unpaid,not_applicable'],
        ]);
        $month = $validated['month'] ?? CarbonImmutable::now('America/Guayaquil')->format('Y-m');
        $status = $validated['status'] ?? '';
        $period = ContributionPeriod::query()->where('month', $month.'-01')->first();

        return Inertia::render('fund/TreasuryContributions', [
            'participants' => $period === null ? null : $contributions->participants($period, $status),
            'filters' => ['month' => $month, 'status' => $status],
            'amountCents' => $period?->amount_cents,
        ]);
    }
}
