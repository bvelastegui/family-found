<?php

namespace App\Http\Controllers;

use App\Actions\Fund\FundAdministration;
use App\Actions\Fund\FundContributions;
use App\Http\Requests\Fund\FundBankRequest;
use App\Http\Requests\Fund\FundContributionPeriodRequest;
use App\Http\Requests\Fund\FundContributionRangeRequest;
use App\Models\Bank;
use App\Models\ContributionPeriod;
use App\Models\FundSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FundConfigurationController extends Controller
{
    public function periods(Request $request): Response
    {
        abort_unless(FundSetting::current()->isTreasurer($request->user()), 403);

        $today = now('America/Guayaquil')->startOfMonth();

        return Inertia::render('fund/ContributionSettings', [
            'periods' => ContributionPeriod::query()->orderByDesc('month')->paginate(15)->through(fn (ContributionPeriod $period): array => [
                'id' => $period->id,
                'month' => $period->month->format('Y-m'),
                'amount_cents' => $period->amount_cents,
                'locked_at' => $period->locked_at?->toIso8601String(),
                'editable' => $period->locked_at === null && $period->month->greaterThan($today),
            ]),
        ]);
    }

    public function storePeriod(FundContributionPeriodRequest $request, FundContributions $contributions): RedirectResponse
    {
        $contributions->setPeriod($request->user(), (string) $request->validated('idempotency_key'), (string) $request->validated('month'), (string) $request->validated('amount'));

        return to_route('fund.contribution-periods.index');
    }

    public function storeRange(FundContributionRangeRequest $request, FundContributions $contributions): RedirectResponse
    {
        $contributions->setRange($request->user(), (string) $request->validated('idempotency_key'), (string) $request->validated('first_month'), (string) $request->validated('last_month'), (string) $request->validated('amount'));

        return to_route('fund.contribution-periods.index');
    }

    public function banks(Request $request): Response
    {
        abort_unless(FundSetting::current()->isTreasurer($request->user()), 403);

        return Inertia::render('fund/Banks', ['banks' => Bank::query()->orderBy('name')->paginate(15)]);
    }

    public function storeBank(FundBankRequest $request, FundAdministration $administration): RedirectResponse
    {
        $administration->bank($request->user(), (string) $request->validated('idempotency_key'), (string) $request->validated('name'), $request->boolean('active'));

        return to_route('fund.banks.index');
    }

    public function updateBank(FundBankRequest $request, Bank $bank, FundAdministration $administration): RedirectResponse
    {
        $administration->bank($request->user(), (string) $request->validated('idempotency_key'), (string) $request->validated('name'), $request->boolean('active'), $bank->id);

        return to_route('fund.banks.index');
    }
}
