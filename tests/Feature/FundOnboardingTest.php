<?php

use App\Models\FundInvitation;
use App\Models\User;
use App\Notifications\FundInvitationNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('only the treasurer can manage participants and send invitations', function () {
    Notification::fake();
    [$treasurer, $member] = prepareFund();
    $payload = ['email' => 'invitada@familia.test', 'idempotency_key' => (string) Str::uuid()];

    $this->actingAs($member)->get(route('fund.treasury.participants.index'))->assertForbidden();
    $this->actingAs($member)->post(route('fund.treasury.participants.invite'), $payload)->assertForbidden();
    $this->actingAs($treasurer)->get(route('fund.treasury.participants.index'))
        ->assertInertia(fn (Assert $page) => $page->component('fund/Participants'));
    $this->actingAs($treasurer)->post(route('fund.treasury.participants.invite'), $payload)->assertSessionHasNoErrors();
    Notification::assertSentOnDemand(FundInvitationNotification::class);
    $this->assertDatabaseCount('fund_invitations', 1);

    $this->actingAs($treasurer)->post(route('fund.treasury.participants.invite'), $payload)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('fund_invitations', 1);
    $this->actingAs($treasurer)->post(route('fund.treasury.participants.invite'), [
        'email' => $member->email, 'idempotency_key' => (string) Str::uuid(),
    ])->assertSessionHasErrors('email');
});

test('invitation email renders its entire content in Spanish', function () {
    $notification = new FundInvitationNotification('https://familia.test/invitations/1?signature=test');
    $mail = $notification->toMail((object) []);
    $rendered = $mail->render();

    expect($mail->subject)->toBe('Invitación al Fondo Familiar')
        ->and(str_contains($rendered, 'Saludos,'))->toBeTrue()
        ->and(str_contains($rendered, 'Todos los derechos reservados.'))->toBeTrue()
        ->and(str_contains($rendered, 'Si tienes problemas para hacer clic en el botón'))->toBeTrue()
        ->and(str_contains($rendered, 'Regards,'))->toBeFalse()
        ->and(str_contains($rendered, 'All rights reserved.'))->toBeFalse()
        ->and(str_contains($rendered, "If you're having trouble clicking"))->toBeFalse();
});

test('a signed invitation can be accepted once and expires after seven days', function () {
    Notification::fake();
    [$treasurer] = prepareFund();
    $this->actingAs($treasurer)->post(route('fund.treasury.participants.invite'), [
        'email' => 'nueva@familia.test', 'idempotency_key' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();
    $url = '';
    Notification::assertSentOnDemand(FundInvitationNotification::class, function (FundInvitationNotification $notification) use (&$url): bool {
        $url = $notification->invitationUrl;

        return true;
    });
    $this->assertDatabaseHas('fund_invitations', ['email' => 'nueva@familia.test', 'used_at' => null]);
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $this->assertSame(hash('sha256', $query['token']), FundInvitation::query()->firstOrFail()->token_hash);

    $this->app['auth']->logout();
    $this->get($url)->assertInertia(fn (Assert $page) => $page->component('auth/AcceptInvitation')->where('email', 'nueva@familia.test'));
    $this->post(route('invitations.accept', FundInvitation::query()->firstOrFail()), [
        'name' => 'Sin firma', 'password' => 'password-segura', 'password_confirmation' => 'password-segura',
    ])->assertForbidden();
    $this->post($url, [
        'name' => 'Nueva participante', 'password' => 'password-segura', 'password_confirmation' => 'password-segura',
    ])->assertRedirect(route('login'));
    $this->assertDatabaseHas('users', ['email' => 'nueva@familia.test', 'name' => 'Nueva participante']);
    $this->assertDatabaseMissing('fund_invitations', ['email' => 'nueva@familia.test', 'used_at' => null]);
    $this->get($url)->assertStatus(410);
    $this->post($url, ['name' => 'Otra', 'password' => 'password-segura', 'password_confirmation' => 'password-segura'])->assertStatus(410);
});

test('expired invitations cannot create accounts', function () {
    Notification::fake();
    [$treasurer] = prepareFund();
    $this->actingAs($treasurer)->post(route('fund.treasury.participants.invite'), [
        'email' => 'vencida@familia.test', 'idempotency_key' => (string) Str::uuid(),
    ]);
    $url = '';
    Notification::assertSentOnDemand(FundInvitationNotification::class, function (FundInvitationNotification $notification) use (&$url): bool {
        $url = $notification->invitationUrl;

        return true;
    });
    $this->app['auth']->logout();
    $this->travel(8)->days();
    $this->get($url)->assertForbidden();
    $this->assertDatabaseMissing('users', ['email' => 'vencida@familia.test']);
});

test('directly created participants receive a password setup link', function () {
    Notification::fake();
    [$treasurer, $member] = prepareFund();
    $payload = ['name' => 'Alta directa', 'email' => 'directa@familia.test', 'idempotency_key' => (string) Str::uuid()];
    $this->actingAs($member)->post(route('fund.treasury.participants.store'), $payload)->assertForbidden();
    $this->actingAs($treasurer)->post(route('fund.treasury.participants.store'), $payload)->assertSessionHasNoErrors();
    $user = User::query()->where('email', 'directa@familia.test')->firstOrFail();
    Notification::assertSentTo($user, ResetPassword::class);
    $this->actingAs($treasurer)->post(route('fund.treasury.participants.store'), $payload)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('users', 3);
});

test('ambiguous noninteractive treasurer assignment does not change the fund', function () {
    [$treasurer] = prepareFund();
    User::factory()->create(['name' => 'Tesorero Vecino']);
    User::factory()->create(['name' => 'Tesorero Hermano']);

    $this->artisan('fund:assign-treasurer', ['name' => 'Tesorero', '--no-interaction' => true])->assertFailed();
    $this->assertDatabaseHas('fund_settings', ['id' => 1, 'treasurer_id' => $treasurer->id]);
});

test('the current treasurer can cancel an invitation sent by another person and invalidate its link', function () {
    [$treasurer, $member] = prepareFund();
    $token = 'token-de-invitacion';
    $invitation = FundInvitation::factory()->create([
        'invited_by_id' => $member->id,
        'token_hash' => hash('sha256', $token),
    ]);
    $url = URL::temporarySignedRoute('invitations.show', $invitation->expires_at, ['invitation' => $invitation->id, 'token' => $token]);
    $payload = ['idempotency_key' => (string) Str::uuid()];

    $this->actingAs($treasurer)->post(route('fund.treasury.participants.cancel', $invitation), $payload)->assertSessionHasNoErrors();
    $cancelledAt = $invitation->fresh()->cancelled_at;
    expect($cancelledAt)->not->toBeNull();
    $this->assertDatabaseHas('operation_events', ['actor_id' => $treasurer->id, 'event' => 'participant.invitation-cancelled', 'subject_id' => $invitation->id]);
    $this->actingAs($treasurer)->post(route('fund.treasury.participants.cancel', $invitation), $payload)->assertSessionHasNoErrors();
    expect($invitation->fresh()->cancelled_at->equalTo($cancelledAt))->toBeTrue();
    expect(DB::table('operation_events')->where('event', 'participant.invitation-cancelled')->count())->toBe(1);

    $this->app['auth']->logout();
    $this->get($url)->assertStatus(410);
    $this->post($url, ['name' => 'Invitada', 'password' => 'password-segura', 'password_confirmation' => 'password-segura'])->assertStatus(410);
    $this->assertDatabaseMissing('users', ['email' => $invitation->email]);
    $this->assertDatabaseHas('fund_invitations', ['id' => $invitation->id, 'used_at' => null]);
});

test('participants cannot cancel invitations even when they sent them', function () {
    [$treasurer, $member] = prepareFund();
    $invitation = FundInvitation::factory()->create(['invited_by_id' => $member->id]);

    $this->actingAs($member)->post(route('fund.treasury.participants.cancel', $invitation), ['idempotency_key' => (string) Str::uuid()])->assertForbidden();

    $this->assertDatabaseHas('fund_invitations', ['id' => $invitation->id, 'cancelled_at' => null]);
    $this->assertDatabaseMissing('operation_events', ['event' => 'participant.invitation-cancelled']);
});

test('accepted and expired invitations cannot be cancelled', function (string $state, string $message) {
    [$treasurer] = prepareFund();
    $invitation = FundInvitation::factory()->create([
        'invited_by_id' => $treasurer->id,
        'used_at' => $state === 'accepted' ? now() : null,
        'expires_at' => $state === 'expired' ? now()->subMinute() : now()->addDay(),
    ]);

    $this->actingAs($treasurer)->post(route('fund.treasury.participants.cancel', $invitation), ['idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors(['invitation' => $message]);

    $this->assertDatabaseHas('fund_invitations', ['id' => $invitation->id, 'cancelled_at' => null]);
})->with([
    ['accepted', 'No puedes cancelar una invitación que ya fue aceptada.'],
    ['expired', 'Esta invitación ya venció.'],
]);

test('cancelled invitations no longer block direct participant creation', function () {
    Notification::fake();
    [$treasurer] = prepareFund();
    $invitation = FundInvitation::factory()->create(['invited_by_id' => $treasurer->id, 'cancelled_at' => now()]);

    $this->actingAs($treasurer)->post(route('fund.treasury.participants.store'), ['name' => 'Nueva persona', 'email' => $invitation->email, 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', ['email' => $invitation->email]);
});

test('the invitation list exposes server calculated statuses without exposing tokens', function () {
    [$treasurer] = prepareFund();
    $previous = FundInvitation::factory()->create(['invited_by_id' => $treasurer->id]);
    $cancelled = FundInvitation::factory()->create(['invited_by_id' => $treasurer->id, 'email' => $previous->email, 'cancelled_at' => now()]);

    $this->actingAs($treasurer)->get(route('fund.treasury.participants.index'))->assertInertia(fn (Assert $page) => $page
        ->where('invitations.data.0.status', 'cancelled')
        ->where('invitations.total', 1)
        ->missing('invitations.data.0.token_hash'));
});
