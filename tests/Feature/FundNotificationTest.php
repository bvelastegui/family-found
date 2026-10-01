<?php

use App\Actions\Fund\FundTransactions;
use App\Models\User;
use App\Notifications\FundActivityNotification;
use App\Notifications\FundActivityPushNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('registration alerts the treasurer and a decision alerts the participant exactly once', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $periodId = (int) DB::table('contribution_periods')->value('id');
    $transaction = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'ALERT-1', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$periodId], 'installment_ids' => [],
    ], UploadedFile::fake()->create('evidence.pdf', 1, 'application/pdf'));

    $this->assertDatabaseCount('notifications', 1);
    $this->assertDatabaseHas('notifications', ['notifiable_id' => $treasurer->id, 'notifiable_type' => User::class]);
    $this->actingAs($treasurer)->get(route('fund.notifications.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Notifications')->where('notifications.data.0.title', 'Comprobante por conciliar')
        ->where('notifications.data.0.url', route('fund.transactions.show', $transaction, false))
        ->where('unreadNotificationsCount', 1));

    app(FundTransactions::class)->approve($treasurer, (string) Str::uuid(), $transaction);
    app(FundTransactions::class)->approve($treasurer, (string) Str::uuid(), $transaction);
    $this->assertDatabaseCount('notifications', 2);
    $this->actingAs($member)->get(route('fund.notifications.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Notifications')->where('notifications.total', 1)
        ->where('notifications.data.0.title', 'Comprobante aprobado'));
});

test('rejection alerts only the transaction owner and notifications cannot be read across accounts', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $other = User::factory()->create();
    $transaction = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'ALERT-REJECT', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [(int) DB::table('contribution_periods')->value('id')], 'installment_ids' => [],
    ], UploadedFile::fake()->create('evidence.pdf', 1, 'application/pdf'));
    app(FundTransactions::class)->reject($treasurer, (string) Str::uuid(), $transaction, 'Comprobante ilegible');
    $notification = $member->notifications()->firstOrFail();

    $this->actingAs($other)->patch(route('fund.notifications.read', $notification->id))->assertNotFound();
    $this->actingAs($other)->get(route('fund.notifications.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Notifications')->where('notifications.total', 0));
    $this->actingAs($member)->patch(route('fund.notifications.read', $notification->id))->assertRedirect();
    $this->assertNotNull($notification->fresh()->read_at);
    $this->actingAs($treasurer)->patch(route('fund.notifications.read-all'))->assertRedirect();
    $this->assertSame(0, $treasurer->unreadNotifications()->count());
});

test('push subscriptions are owned by their account and invalid endpoints are refused', function () {
    [$treasurer, $member] = prepareFund();
    $endpoint = 'https://push.example.test/subscription/abc';
    $payload = ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-key'], 'content_encoding' => 'aes128gcm'];

    $this->get(route('fund.notifications.index'))->assertRedirect(route('login'));
    $this->post(route('fund.push-subscriptions.store'), $payload)->assertRedirect(route('login'));
    $this->actingAs($member)->post(route('fund.push-subscriptions.store'), [...$payload, 'endpoint' => 'http://example.test/invalid'])->assertSessionHasErrors('endpoint');
    $this->actingAs($treasurer)->post(route('fund.push-subscriptions.store'), $payload)->assertRedirect(route('fund.notifications.index'));
    $this->assertDatabaseHas('push_subscriptions', ['endpoint' => $endpoint, 'subscribable_id' => $treasurer->id]);
    $this->actingAs($member)->post(route('fund.push-subscriptions.store'), $payload)->assertSessionHasErrors('endpoint');
    $this->actingAs($member)->delete(route('fund.push-subscriptions.destroy'), ['endpoint' => $endpoint])->assertRedirect();
    $this->assertDatabaseHas('push_subscriptions', ['endpoint' => $endpoint, 'subscribable_id' => $treasurer->id]);
    $this->actingAs($treasurer)->delete(route('fund.push-subscriptions.destroy'), ['endpoint' => $endpoint])->assertRedirect();
    $this->assertDatabaseCount('push_subscriptions', 0);
});

test('a subscribed treasurer is offered a queued web push notification', function () {
    [$treasurer, $member, $bankId] = prepareFund();
    $treasurer->updatePushSubscription('https://push.example.test/subscription/abc', 'public-key', 'auth-key');
    config()->set('webpush.vapid.public_key', 'test-public-key');
    Notification::fake();
    Storage::fake('fund');

    $transaction = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'ALERT-PUSH', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [(int) DB::table('contribution_periods')->value('id')], 'installment_ids' => [],
    ], UploadedFile::fake()->create('evidence.pdf', 1, 'application/pdf'));

    Notification::assertSentTo($treasurer, FundActivityNotification::class);
    Notification::assertSentTo($treasurer, FundActivityPushNotification::class, fn (FundActivityPushNotification $notification): bool => $notification->toWebPush($treasurer)->toArray()['data']['url'] === route('fund.transactions.show', $transaction, false));
    Notification::assertNotSentTo($member, FundActivityPushNotification::class);
});
