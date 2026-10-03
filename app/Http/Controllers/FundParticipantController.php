<?php

namespace App\Http\Controllers;

use App\Actions\Fund\ManageParticipants;
use App\Http\Requests\Fund\CancelFundInvitationRequest;
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

        $validated = $request->validate([
            'invitation_status' => ['sometimes', 'nullable', 'in:pending,cancelled,expired'],
        ]);
        $status = $validated['invitation_status'] ?? '';
        $latestId = '(SELECT MAX(latest_invitation.id) FROM fund_invitations AS latest_invitation WHERE latest_invitation.email = fund_invitations.email)';
        $statusExpression = "CASE WHEN used_at IS NOT NULL THEN 'accepted' WHEN cancelled_at IS NOT NULL THEN 'cancelled' WHEN expires_at <= ? THEN 'expired' WHEN id <> {$latestId} THEN 'superseded' ELSE 'pending' END";
        $now = now();

        return Inertia::render('fund/Participants', [
            'participants' => User::query()->orderBy('name')->paginate(15, ['id', 'name', 'email', 'created_at'])->withQueryString(),
            'invitations' => FundInvitation::query()
                ->select(['id', 'email', 'invited_by_id', 'expires_at', 'used_at', 'cancelled_at', 'created_at'])
                ->selectSub(FundInvitation::query()->from('fund_invitations as latest_invitation')->selectRaw('MAX(latest_invitation.id)')->whereColumn('latest_invitation.email', 'fund_invitations.email'), 'latest_invitation_id')
                ->whereRaw("({$statusExpression}) IN ('pending', 'cancelled', 'expired')", [$now])
                ->when($status !== '', fn ($query) => $query->whereRaw("({$statusExpression}) = ?", [$now, $status]))
                ->orderByRaw("CASE WHEN ({$statusExpression}) = 'pending' THEN 0 ELSE 1 END", [$now])
                ->latest('id')->paginate(10, ['*'], 'invitations')->withQueryString()
                ->through(function (FundInvitation $invitation): array {
                    $status = match (true) {
                        $invitation->used_at !== null => 'accepted',
                        $invitation->cancelled_at !== null => 'cancelled',
                        ! $invitation->expires_at->isFuture() => 'expired',
                        $invitation->id !== (int) $invitation->getAttribute('latest_invitation_id') => 'superseded',
                        default => 'pending',
                    };

                    return [...$invitation->only(['id', 'email', 'expires_at', 'used_at', 'cancelled_at', 'created_at']), 'status' => $status];
                }),
            'filters' => ['invitation_status' => $status],
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

    public function cancel(CancelFundInvitationRequest $request, FundInvitation $invitation, ManageParticipants $participants): RedirectResponse
    {
        $participants->cancel($request->user(), (string) $request->validated('idempotency_key'), $invitation);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invitación cancelada. El enlace ya no permite crear una cuenta.']);

        return back();
    }
}
