<?php

use App\Actions\Fund\FundTransactions;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('treasury only lists people without approved contributions after the fifth in Ecuador', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-05 23:30:00', 'America/Guayaquil'));
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $member->update(['name' => 'Zeta pendiente']);
    $paidMember = User::factory()->create(['name' => 'Persona al día']);
    $periodId = (int) DB::table('contribution_periods')->value('id');

    $this->actingAs($member)->get(route('fund.treasury.index'))->assertForbidden();
    $this->actingAs($treasurer)->get(route('fund.treasury.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Treasury')->where('contributionMonth', '2027-03')->where('unpaid', null));

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
    User::factory()->create(['name' => 'Inscripción posterior']);

    $this->actingAs($treasurer)->get(route('fund.treasury.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Treasury')
        ->where('unpaid.total', 2)
        ->has('unpaid.data', 2)
        ->where('unpaid.data.0.id', $treasurer->id)
        ->where('unpaid.data.0.pending_transaction_id', null)
        ->where('unpaid.data.1.id', $member->id)
        ->where('unpaid.data.1.pending_transaction_id', $pendingId)
        ->where('contributionChart.0.month', '2027-03')
        ->where('contributionChart.0.expected_cents', 7500)
        ->where('contributionChart.0.received_cents', 2500));

    app(FundTransactions::class)->approve($treasurer, (string) Str::uuid(), $pendingId);
    $this->actingAs($treasurer)->get(route('fund.treasury.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Treasury')->where('unpaid.total', 1)->where('contributionChart.0.received_cents', 5000));
});

test('treasury omits the overdue list when this month has no configured contribution', function () {
    $this->travelTo(CarbonImmutable::parse('2027-04-08 12:00:00', 'America/Guayaquil'));
    [$treasurer] = prepareFund();
    DB::table('contribution_periods')->delete();

    $this->actingAs($treasurer)->get(route('fund.treasury.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Treasury')->where('unpaid', null)->where('contributionMonth', null)->where('contributionChart', []));
});

test('the overdue list paginates independently from pending transfers', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-05 12:00:00', 'America/Guayaquil'));
    [$treasurer] = prepareFund();
    User::factory()->count(11)->create();
    $this->travelTo(CarbonImmutable::parse('2027-03-06 12:00:00', 'America/Guayaquil'));

    $this->actingAs($treasurer)->get(route('fund.treasury.index', ['unpaid_page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->component('fund/Treasury')
            ->where('unpaid.total', 13)->where('unpaid.current_page', 2)->has('unpaid.data', 3)
            ->where('pending.current_page', 1)->has('pending.data', 0));
});
