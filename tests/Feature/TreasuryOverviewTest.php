<?php

use App\Actions\Fund\FundContributions;
use App\Actions\Fund\FundTransactions;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('treasury contributions distinguish paid pending and ineligible participants using the Ecuador cutoff', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-05 23:30:00', 'America/Guayaquil'));
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $treasurer->update(['name' => 'Tesorería']);
    $member->update(['name' => 'Zeta pendiente']);
    $paidMember = User::factory()->create(['name' => 'Persona al día']);
    $periodId = (int) DB::table('contribution_periods')->value('id');

    $this->actingAs($member)->get(route('fund.treasury.index'))->assertForbidden();
    $this->actingAs($member)->get(route('fund.treasury.contributions.index'))->assertForbidden();
    $this->actingAs($member)->get(route('fund.treasury.reconciliation.index'))->assertForbidden();

    $pendingId = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'PENDIENTE-MARZO', 'transaction_date' => '2027-03-05',
        'amount' => '25.00', 'period_ids' => [$periodId], 'installment_ids' => [],
    ], UploadedFile::fake()->create('revision.pdf', 1, 'application/pdf'));
    $approvedId = app(FundTransactions::class)->register($paidMember, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'APROBADO-MARZO', 'transaction_date' => '2027-03-05',
        'amount' => '25.00', 'period_ids' => [$periodId], 'installment_ids' => [],
    ], UploadedFile::fake()->create('aprobado.pdf', 1, 'application/pdf'));
    app(FundTransactions::class)->approve($treasurer, (string) Str::uuid(), $approvedId);

    $this->travelTo(CarbonImmutable::parse('2027-03-06 00:30:00', 'America/Guayaquil'));
    $lateMember = User::factory()->create(['name' => 'Inscripción posterior']);

    $this->actingAs($treasurer)->get(route('fund.treasury.contributions.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/TreasuryContributions')->where('filters.month', '2027-03')->where('participants.total', 4)
        ->where('participants.data.0.id', $lateMember->id)->where('participants.data.0.status', 'not_applicable')
        ->where('participants.data.0.expected_cents', 0)->where('participants.data.0.pending_cents', 0)
        ->where('participants.data.1.id', $paidMember->id)->where('participants.data.1.status', 'paid')
        ->where('participants.data.1.approved_cents', 2500)->where('participants.data.1.pending_cents', 0)
        ->where('participants.data.2.id', $treasurer->id)->where('participants.data.2.status', 'unpaid')
        ->where('participants.data.2.pending_cents', 2500)
        ->where('participants.data.3.id', $member->id)->where('participants.data.3.status', 'pending')
        ->where('participants.data.3.transaction_id', $pendingId)->where('participants.data.3.approved_cents', 0));

    $this->actingAs($treasurer)->get(route('fund.treasury.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Treasury')
        ->where('pendingCount', 1)
        ->where('contributionChart.0.month', '2027-03')
        ->where('contributionChart.0.expected_cents', 7500)
        ->where('contributionChart.0.received_cents', 2500));

    app(FundTransactions::class)->approve($treasurer, (string) Str::uuid(), $pendingId);
    $this->actingAs($treasurer)->get(route('fund.treasury.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Treasury')->where('pendingCount', 0)->where('contributionChart.0.received_cents', 5000));
    $this->actingAs($treasurer)->get(route('fund.treasury.contributions.index', ['status' => 'paid']))->assertInertia(fn (Assert $page) => $page
        ->component('fund/TreasuryContributions')->where('participants.total', 2));
});

test('treasury contributions show an unconfigured month without assuming an amount', function () {
    $this->travelTo(CarbonImmutable::parse('2027-04-08 12:00:00', 'America/Guayaquil'));
    [$treasurer] = prepareFund();
    DB::table('contribution_periods')->delete();

    $this->actingAs($treasurer)->get(route('fund.treasury.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Treasury')->where('contributionChart', []));
    $this->actingAs($treasurer)->get(route('fund.treasury.contributions.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/TreasuryContributions')->where('participants', null)->where('amountCents', null));
});

test('treasury contributions preserve the selected month and status while paginating', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-05 12:00:00', 'America/Guayaquil'));
    [$treasurer] = prepareFund();
    User::factory()->count(16)->create();
    $this->travelTo(CarbonImmutable::parse('2027-03-06 12:00:00', 'America/Guayaquil'));

    $this->actingAs($treasurer)->get(route('fund.treasury.contributions.index', ['month' => '2027-03', 'status' => 'unpaid', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->component('fund/TreasuryContributions')
            ->where('participants.total', 18)->where('participants.current_page', 2)->has('participants.data', 3)
            ->where('participants.links.1.url', fn (string $url) => str_contains($url, 'month=2027-03') && str_contains($url, 'status=unpaid')));
});

test('reconciliation defaults to pending and preserves a safe return context after a decision', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $periodId = (int) DB::table('contribution_periods')->value('id');
    $transactionId = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'RETURN-CONTEXT', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$periodId], 'installment_ids' => [],
    ], UploadedFile::fake()->create('comprobante.pdf', 1, 'application/pdf'));
    $returnTo = route('fund.treasury.contributions.index', ['month' => now('America/Guayaquil')->format('Y-m'), 'status' => 'pending', 'page' => 2], false);

    $this->actingAs($treasurer)->get(route('fund.treasury.reconciliation.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Transactions')->where('reconciliation', true)->where('filters.status', 'pending')->where('transactions.total', 1));
    $this->get(route('fund.transactions.show', ['transaction' => $transactionId, 'return_to' => $returnTo]))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Transaction')->where('returnTo', $returnTo));
    $this->post(route('fund.transactions.approve', ['transaction' => $transactionId, 'return_to' => $returnTo]), ['idempotency_key' => (string) Str::uuid()])
        ->assertRedirect(route('fund.transactions.show', ['transaction' => $transactionId, 'return_to' => $returnTo]));
    $this->assertDatabaseHas('fund_transactions', ['id' => $transactionId, 'status' => 'approved']);
    $this->get(route('fund.treasury.reconciliation.index'))->assertInertia(fn (Assert $page) => $page->where('transactions.total', 0));
    $this->get(route('fund.treasury.reconciliation.index', ['status' => 'approved']))->assertInertia(fn (Assert $page) => $page->where('transactions.total', 1));
    $this->get(route('fund.treasury.reconciliation.index', ['status' => '']))->assertInertia(fn (Assert $page) => $page->where('transactions.total', 1));
    $this->get(route('fund.transactions.show', ['transaction' => $transactionId, 'return_to' => 'https://example.com/fund/treasury/contributions']))
        ->assertInertia(fn (Assert $page) => $page->where('returnTo', null));
});

test('treasury contributions reject invalid months and unknown states', function (array $query, string $field) {
    [$treasurer] = prepareFund();

    $this->actingAs($treasurer)->get(route('fund.treasury.contributions.index', $query))->assertSessionHasErrors($field);
})->with([
    'invalid month' => [['month' => '2027-13'], 'month'],
    'unknown status' => [['status' => 'approved'], 'status'],
]);

test('a rejection keeps the reconciliation filters and does not count as a paid contribution', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $periodId = (int) DB::table('contribution_periods')->value('id');
    $transactionId = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'REJECT-CONTEXT', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$periodId], 'installment_ids' => [],
    ], UploadedFile::fake()->create('comprobante.pdf', 1, 'application/pdf'));
    $returnTo = route('fund.treasury.reconciliation.index', ['status' => 'pending', 'search' => 'REJECT', 'page' => 2], false);

    $this->actingAs($treasurer)->post(route('fund.transactions.reject', ['transaction' => $transactionId, 'return_to' => $returnTo]), [
        'idempotency_key' => (string) Str::uuid(), 'reason' => 'El comprobante no coincide con el depósito.',
    ])->assertRedirect(route('fund.transactions.show', ['transaction' => $transactionId, 'return_to' => $returnTo]));
    $this->assertDatabaseHas('fund_transactions', ['id' => $transactionId, 'status' => 'rejected']);
    $this->assertDatabaseCount('journal_entries', 0);
    $this->get(route('fund.treasury.reconciliation.index', ['status' => 'rejected']))->assertInertia(fn (Assert $page) => $page
        ->where('transactions.total', 1)->where('transactions.data.0.id', $transactionId));
    $this->get(route('fund.treasury.contributions.index', ['status' => 'pending']))->assertInertia(fn (Assert $page) => $page
        ->where('participants.total', 0));
});

test('treasury contributions use the selected periods amount and registration cutoff', function () {
    $this->travelTo(CarbonImmutable::parse('2027-02-05 12:00:00', 'America/Guayaquil'));
    [$treasurer, $member] = prepareFund();
    app(FundContributions::class)->setPeriod($treasurer, (string) Str::uuid(), '2027-03', '30.00');
    $this->travelTo(CarbonImmutable::parse('2027-03-05 12:00:00', 'America/Guayaquil'));
    $newMember = User::factory()->create();

    $this->actingAs($treasurer)->get(route('fund.treasury.contributions.index', ['month' => '2027-02', 'status' => 'not_applicable']))
        ->assertInertia(fn (Assert $page) => $page->where('amountCents', 2500)->where('participants.total', 1)
            ->where('participants.data.0.id', $newMember->id)->where('participants.data.0.expected_cents', 0));
    $this->get(route('fund.treasury.contributions.index', ['month' => '2027-03', 'status' => 'unpaid']))
        ->assertInertia(fn (Assert $page) => $page->where('amountCents', 3000)->where('participants.total', 3)
            ->where('participants.data.0.expected_cents', 3000));
});
