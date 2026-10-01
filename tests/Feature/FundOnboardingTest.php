<?php

use App\Models\FundInvitation;
use App\Models\User;
use App\Notifications\FundInvitationNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
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
