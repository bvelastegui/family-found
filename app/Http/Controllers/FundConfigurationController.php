<?php

namespace App\Http\Controllers;

use App\Actions\Fund\FundAdministration;
use App\Actions\Fund\FundContributions;
use App\Http\Requests\Fund\FundBankRequest;
use App\Http\Requests\Fund\FundContributionPeriodRequest;
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

        return Inertia::render('fund/ContributionSettings', ['periods' => ContributionPeriod::query()->orderByDesc('month')->paginate(15)]);
    }

    public function storePeriod(FundContributionPeriodRequest $request, FundContributions $contributions): RedirectResponse
    {
        $contributions->setPeriod($request->user(), (string) $request->validated('idempotency_key'), (string) $request->validated('month'), (string) $request->validated('amount'));

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
