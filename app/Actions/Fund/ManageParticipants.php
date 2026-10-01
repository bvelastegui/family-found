<?php

namespace App\Actions\Fund;

use App\Models\FundInvitation;
use App\Models\User;
use App\Notifications\FundInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageParticipants
{
    public function __construct(private RunFundOperation $operations, private RecordFundEvent $events) {}

    public function invite(User $treasurer, string $key, string $email): FundInvitation
    {
        $email = Str::lower(trim($email));
        $token = hash_hmac('sha256', 'fund-invitation:'.$key, (string) config('app.key'));

        $id = $this->operations->handle($treasurer, 'participant.invite', $key, ['email' => $email], function () use ($treasurer, $email, $token): int {
            if (User::query()->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'Esta persona ya tiene una cuenta.']);
            }
            $invitation = FundInvitation::query()->create([
                'email' => $email,
                'token_hash' => hash('sha256', $token),
                'invited_by_id' => $treasurer->id,
                'expires_at' => now()->addDays(7),
            ]);
            $this->events->handle($treasurer, 'participant.invited', 'invitation', $invitation->id, ['email' => $email]);

            return $invitation->id;
        }, 'treasurer');

        $invitation = FundInvitation::query()->findOrFail($id);
        $url = URL::temporarySignedRoute('invitations.show', $invitation->expires_at, ['invitation' => $invitation->id, 'token' => $token]);
        Notification::route('mail', $email)->notify(new FundInvitationNotification($url));

        return $invitation;
    }

    public function createDirectly(User $treasurer, string $key, string $name, string $email): User
    {
        $email = Str::lower(trim($email));
        $id = $this->operations->handle($treasurer, 'participant.create', $key, compact('name', 'email'), function () use ($treasurer, $name, $email): int {
            if (User::query()->where('email', $email)->exists() || FundInvitation::query()->where('email', $email)->whereNull('used_at')->where('expires_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['email' => 'Este correo ya tiene una cuenta o una invitación vigente.']);
            }
            $user = User::query()->create(['name' => trim($name), 'email' => $email, 'password' => Str::random(64)]);
            $this->events->handle($treasurer, 'participant.created', 'user', $user->id, ['email' => $email]);

            return $user->id;
        }, 'treasurer');

        $user = User::query()->findOrFail($id);
        $status = Password::sendResetLink(['email' => $user->email]);
        if ($status !== Password::ResetLinkSent && $status !== Password::ResetThrottled) {
            throw ValidationException::withMessages(['email' => 'La cuenta fue creada, pero no se pudo enviar el enlace. Intenta reenviarlo desde recuperar contraseña.']);
        }

        return $user;
    }

    public function accept(FundInvitation $invitation, string $token, string $name, string $password): User
    {
        return DB::transaction(function () use ($invitation, $token, $name, $password): User {
            $invitation = FundInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            abort_unless($this->isActive($invitation, $token), 410, 'La invitación ya no está disponible.');

            $user = User::query()->create(['name' => trim($name), 'email' => $invitation->email, 'password' => $password]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $invitation->update(['used_at' => now()]);
            $actor = User::query()->findOrFail($invitation->invited_by_id);
            $this->events->handle($actor, 'participant.invitation-accepted', 'invitation', $invitation->id, ['user_id' => $user->id]);

            return $user;
        }, 5);
    }

    public function isActive(FundInvitation $invitation, string $token): bool
    {
        return $invitation->used_at === null
            && $invitation->expires_at->isFuture()
            && hash_equals($invitation->token_hash, hash('sha256', $token))
            && (int) FundInvitation::query()->where('email', $invitation->email)->latest('id')->value('id') === $invitation->id
            && ! User::query()->where('email', $invitation->email)->exists();
    }
}
