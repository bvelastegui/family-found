<?php

namespace App\Http\Controllers;

use App\Actions\Fund\FundAdministration;
use App\Http\Requests\Fund\FundTreasurerRequest;
use App\Models\FundSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FundAdministrationController extends Controller
{
    public function edit(Request $request): Response
    {
        $fund = FundSetting::current();
        abort_unless($fund->isAdministrator($request->user()), 403);

        return Inertia::render('fund/Treasurer', ['treasurerId' => $fund->treasurer_id, 'users' => User::query()->orderBy('name')->paginate(20, ['id', 'name', 'email'])]);
    }

    public function update(FundTreasurerRequest $request, FundAdministration $administration): RedirectResponse
    {
        $administration->treasurer($request->user(), (string) $request->validated('idempotency_key'), (int) $request->validated('user_id'));

        return to_route('administration.treasurer.edit');
    }
}
