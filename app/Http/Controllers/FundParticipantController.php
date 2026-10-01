<?php

namespace App\Http\Controllers;

use App\Actions\Fund\ManageParticipants;
use App\Http\Requests\Fund\CreateFundParticipantRequest;
use App\Http\Requests\Fund\IssueFundInvitationRequest;
use App\Models\FundInvitation;
use App\Models\FundSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FundParticipantController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(FundSetting::current()->isTreasurer($request->user()), 403);

        return Inertia::render('fund/Participants', [
            'participants' => User::query()->orderBy('name')->paginate(15, ['id', 'name', 'email', 'created_at']),
            'invitations' => FundInvitation::query()->latest('id')->paginate(10, ['id', 'email', 'invited_by_id', 'expires_at', 'used_at', 'created_at'], 'invitations'),
        ]);
    }

    public function invite(IssueFundInvitationRequest $request, ManageParticipants $participants): RedirectResponse
    {
        $participants->invite($request->user(), (string) $request->validated('idempotency_key'), (string) $request->validated('email'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invitación enviada al correo indicado.']);

        return to_route('fund.treasury.participants.index');
    }

    public function store(CreateFundParticipantRequest $request, ManageParticipants $participants): RedirectResponse
    {
        $participants->createDirectly($request->user(), (string) $request->validated('idempotency_key'), (string) $request->validated('name'), (string) $request->validated('email'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cuenta creada. Enviamos un enlace para establecer la contraseña.']);

        return to_route('fund.treasury.participants.index');
    }
}
