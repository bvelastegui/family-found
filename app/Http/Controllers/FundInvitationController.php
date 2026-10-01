<?php

namespace App\Http\Controllers;

use App\Actions\Fund\ManageParticipants;
use App\Http\Requests\Fund\AcceptFundInvitationRequest;
use App\Models\FundInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class FundInvitationController extends Controller
{
    public function show(Request $request, FundInvitation $invitation, ManageParticipants $participants): Response
    {
        $token = (string) $request->query('token');
        abort_unless($participants->isActive($invitation, $token), 410, 'La invitación ya no está disponible.');

        return Inertia::render('auth/AcceptInvitation', [
            'email' => $invitation->email,
            'invitationId' => $invitation->id,
            'acceptUrl' => URL::temporarySignedRoute('invitations.accept', $invitation->expires_at, ['invitation' => $invitation->id, 'token' => $token]),
        ]);
    }

    public function accept(AcceptFundInvitationRequest $request, FundInvitation $invitation, ManageParticipants $participants): RedirectResponse
    {
        $participants->accept($invitation, (string) $request->query('token'), (string) $request->validated('name'), (string) $request->validated('password'));

        return to_route('login')->with('status', 'Cuenta creada. Ahora puedes iniciar sesión.');
    }
}
