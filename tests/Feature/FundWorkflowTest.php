<?php

use App\Actions\Fund\FundBalances;
use App\Actions\Fund\FundContributions;
use App\Actions\Fund\FundTransactions;
use App\Actions\Fund\InstallFund;
use App\Enums\JournalAccount;
use App\Models\FundTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

test('a transaction stays outside the ledger until approval and rejection records no journal', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $period = (int) DB::table('contribution_periods')->value('id');

    $first = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'ABC-100', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$period], 'installment_ids' => [],
    ], UploadedFile::fake()->create('comprobante.pdf', 1, 'application/pdf'));

    $this->assertDatabaseHas('fund_transactions', ['id' => $first, 'status' => 'pending']);
    $this->assertDatabaseCount('journal_entries', 0);
    $this->assertDatabaseCount('journal_lines', 0);
    app(FundTransactions::class)->reject($treasurer, (string) Str::uuid(), $first, 'Monto incorrecto');
    $this->assertDatabaseCount('journal_entries', 0);
    $this->assertDatabaseHas('fund_transactions', ['id' => $first, 'status' => 'rejected', 'active_reference' => null]);

    $second = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'ABC-100', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$period], 'installment_ids' => [],
    ], UploadedFile::fake()->create('corregido.pdf', 1, 'application/pdf'));
    app(FundTransactions::class)->approve($treasurer, (string) Str::uuid(), $second);

    $this->assertDatabaseHas('fund_transactions', ['id' => $second, 'status' => 'approved', 'corrected_from_id' => $first]);
    $this->assertDatabaseCount('journal_entries', 1);
    $this->assertDatabaseHas('journal_lines', ['account' => 'cash', 'side' => 'debit', 'amount_cents' => 2500]);
    $this->assertDatabaseHas('journal_lines', ['account' => 'contributions', 'side' => 'credit', 'amount_cents' => 2500]);
    $this->assertDatabaseHas('operation_events', ['event' => 'transaction.rejected', 'subject_id' => $first]);
});

test('the user cannot approve a pending transaction', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $period = (int) DB::table('contribution_periods')->value('id');
    $id = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'NO-APPROVE', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$period], 'installment_ids' => [],
    ], UploadedFile::fake()->create('comprobante.pdf', 1, 'application/pdf'));

    $this->actingAs($member)->post(route('fund.transactions.approve', $id), ['idempotency_key' => (string) Str::uuid()])->assertForbidden();

    expect(FundTransaction::findOrFail($id)->status->value)->toBe('pending');
    $this->assertDatabaseCount('journal_entries', 0);
});

test('HTTP registration requires an exact allocation and keeps the bank receipt unique', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $periodId = (int) DB::table('contribution_periods')->value('id');
    $payload = [
        'idempotency_key' => (string) Str::uuid(), 'bank_id' => $bankId, 'reference' => 'receipt-x',
        'transaction_date' => now('America/Guayaquil')->toDateString(), 'amount' => '25.00',
        'period_ids' => [$periodId], 'installment_ids' => [],
    ];

    $this->actingAs($member)->post(route('fund.transactions.store'), [
        ...$payload, 'amount' => '26.00', 'evidence' => UploadedFile::fake()->create('incorrecto.pdf', 1, 'application/pdf'),
    ])->assertSessionHasErrors('amount');
    $this->assertDatabaseCount('fund_transactions', 0);

    $this->actingAs($member)->post(route('fund.transactions.store'), [
        ...$payload, 'evidence' => UploadedFile::fake()->create('correcto.pdf', 1, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('fund_transactions', ['reference' => 'receipt-x', 'status' => 'pending']);
    $this->assertDatabaseCount('journal_entries', 0);
    $this->actingAs($member)->post(route('fund.transactions.store'), [
        ...$payload, 'idempotency_key' => (string) Str::uuid(), 'evidence' => UploadedFile::fake()->create('duplicado.pdf', 1, 'application/pdf'),
    ])->assertSessionHasErrors();
    $this->assertDatabaseCount('fund_transactions', 1);
});

test('correction replaces a posted contribution atomically and preserves the old journal', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $periodId = (int) DB::table('contribution_periods')->value('id');
    $data = ['bank_id' => $bankId, 'reference' => 'CORRECT-ONE', 'transaction_date' => now('America/Guayaquil')->toDateString(), 'amount' => '25.00', 'period_ids' => [$periodId], 'installment_ids' => []];
    $transactions = app(FundTransactions::class);
    $original = $transactions->register($member, (string) Str::uuid(), $data, UploadedFile::fake()->create('original.pdf', 1, 'application/pdf'));
    $transactions->approve($treasurer, (string) Str::uuid(), $original);

    $replacement = $transactions->correct($treasurer, (string) Str::uuid(), $original, [...$data, 'reason' => 'Corrijo la fecha'], UploadedFile::fake()->create('reemplazo.pdf', 1, 'application/pdf'));

    expect($replacement)->not->toBe($original);
    $this->assertDatabaseHas('fund_transactions', ['id' => $original, 'status' => 'approved', 'superseded_by_id' => $replacement]);
    $this->assertDatabaseHas('fund_transactions', ['id' => $replacement, 'status' => 'approved', 'corrected_from_id' => $original]);
    expect(app(FundBalances::class)->account(JournalAccount::Cash))->toBe(2500);
    $this->assertDatabaseCount('journal_entries', 3);
});

test('a failed correction preserves the original balance and approval cannot be repeated with a new key', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $periodId = (int) DB::table('contribution_periods')->value('id');
    $data = ['bank_id' => $bankId, 'reference' => 'UNCHANGED-1', 'transaction_date' => now('America/Guayaquil')->toDateString(), 'amount' => '25.00', 'period_ids' => [$periodId], 'installment_ids' => []];
    $transactions = app(FundTransactions::class);
    $id = $transactions->register($member, (string) Str::uuid(), $data, UploadedFile::fake()->create('original.pdf', 1, 'application/pdf'));
    $transactions->approve($treasurer, (string) Str::uuid(), $id);
    $transactions->approve($treasurer, (string) Str::uuid(), $id);

    $this->assertDatabaseCount('journal_entries', 1);
    try {
        $transactions->correct($treasurer, (string) Str::uuid(), $id, [...$data, 'amount' => '26.00', 'reason' => 'Error'], UploadedFile::fake()->create('nuevo.pdf', 1, 'application/pdf'));
        $this->fail('La corrección incompatible debió rechazarse.');
    } catch (ValidationException) {
        $this->assertDatabaseHas('fund_transactions', ['id' => $id, 'status' => 'approved', 'active_reference' => 'UNCHANGED-1', 'superseded_by_id' => null]);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('fund_transactions', 1);
    }
});

test('other members cannot read financial records or evidence they do not own', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $other = User::factory()->create();
    $periodId = (int) DB::table('contribution_periods')->value('id');
    $id = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'PRIVATE-1', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$periodId], 'installment_ids' => [],
    ], UploadedFile::fake()->create('privado.pdf', 1, 'application/pdf'));
    $evidenceId = FundTransaction::findOrFail($id)->evidence_id;

    $this->actingAs($other)->get(route('fund.transactions.show', $id))->assertForbidden();
    $this->actingAs($other)->get(route('fund.evidences.show', $evidenceId))->assertForbidden();
    $this->actingAs($member)->get(route('fund.transactions.show', $id))->assertOk();
});

test('transferring the only treasurer immediately removes the old treasurer financial permissions', function () {
    [$oldTreasurer, $member] = prepareFund();
    $administrator = $oldTreasurer;
    $newTreasurer = User::factory()->create();

    $this->actingAs($administrator)->post(route('administration.treasurer.update'), [
        'idempotency_key' => (string) Str::uuid(), 'user_id' => $newTreasurer->id,
    ])->assertRedirect(route('administration.treasurer.edit'));

    $this->assertDatabaseHas('fund_settings', ['id' => 1, 'treasurer_id' => $newTreasurer->id]);
    $this->actingAs($oldTreasurer)->post(route('fund.contribution-periods.store'), [
        'idempotency_key' => (string) Str::uuid(), 'month' => '2027-01', 'amount' => '25.00',
    ])->assertForbidden();
    $this->actingAs($newTreasurer)->post(route('fund.contribution-periods.store'), [
        'idempotency_key' => (string) Str::uuid(), 'month' => '2027-01', 'amount' => '25.00',
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('contribution_periods', ['month' => '2027-01-01', 'amount_cents' => 2500]);
});

test('a member can submit only one pending contribution and the same month cannot be paid twice', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $periodId = (int) DB::table('contribution_periods')->value('id');
    $payload = ['bank_id' => $bankId, 'transaction_date' => now('America/Guayaquil')->toDateString(), 'amount' => '25.00', 'period_ids' => [$periodId], 'installment_ids' => []];
    $transactions = app(FundTransactions::class);
    $first = $transactions->register($member, (string) Str::uuid(), [...$payload, 'reference' => 'FIRST-1'], UploadedFile::fake()->create('first.pdf', 1, 'application/pdf'));

    $this->actingAs($member)->post(route('fund.transactions.store'), [
        ...$payload, 'reference' => 'SECOND-1', 'idempotency_key' => (string) Str::uuid(), 'evidence' => UploadedFile::fake()->create('second.pdf', 1, 'application/pdf'),
    ])->assertSessionHasErrors('period_ids');
    $this->assertDatabaseCount('fund_transactions', 1);
    $transactions->approve($treasurer, (string) Str::uuid(), $first);
    $this->actingAs($member)->post(route('fund.transactions.store'), [
        ...$payload, 'reference' => 'THIRD-1', 'idempotency_key' => (string) Str::uuid(), 'evidence' => UploadedFile::fake()->create('third.pdf', 1, 'application/pdf'),
    ])->assertSessionHasErrors('period_ids');
    $this->assertDatabaseCount('journal_entries', 1);
});

test('the first contribution follows the fund local month even across a UTC date boundary', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 02:00:00', 'UTC'));
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $period = DB::table('contribution_periods')->first();

    expect($period->month)->toBe('2026-09-01');
    $this->actingAs($member)->post(route('fund.transactions.store'), [
        'idempotency_key' => (string) Str::uuid(), 'bank_id' => $bankId, 'reference' => 'SEPTEMBER-1',
        'transaction_date' => '2026-09-30', 'amount' => '25.00', 'period_ids' => [$period->id], 'installment_ids' => [],
        'evidence' => UploadedFile::fake()->create('local.pdf', 1, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('journal_entries', 0);
});

test('the member sees their fund and contribution form without treasury settings', function () {
    [$treasurer, $member] = prepareFund();

    $this->actingAs($member)->get(route('fund.index'))->assertRedirect(route('dashboard'));
    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->component('Dashboard')->where('isTreasurer', false)->where('contributedCents', 0)->where('nextContribution.month', now('America/Guayaquil')->format('Y-m')));
    $this->actingAs($member)->get(route('fund.transactions.create'))->assertInertia(fn (Assert $page) => $page->component('fund/TransactionForm')->where('hasPendingContribution', false)->has('banks', 1));
    $this->actingAs($member)->get(route('fund.contribution-periods.index'))->assertForbidden();
    $this->actingAs($treasurer)->get(route('fund.contribution-periods.index'))->assertOk();
});

test('fund installation does not replace its initial administrator when run again', function () {
    $install = app(InstallFund::class);
    $fund = $install->handle('Primera administradora', 'first@familia.test', 'password-seguro-inicial');
    $adminId = $fund->administrator_id;

    $again = $install->handle('Otra persona', 'other@familia.test', 'otra-password-segura');

    expect($again->administrator_id)->toBe($adminId);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseHas('users', ['id' => $adminId, 'email' => 'first@familia.test']);
    $this->assertDatabaseCount('operation_events', 1);
});

test('the first defined contribution period is payable even when the user registered later', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-12 15:00:00', 'UTC'));
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    app(FundContributions::class)->setPeriod($treasurer, (string) Str::uuid(), '2027-01', '25.00');
    app(FundContributions::class)->setPeriod($treasurer, (string) Str::uuid(), '2027-02', '25.00');
    $january = (int) DB::table('contribution_periods')->where('month', '2027-01-01')->value('id');
    $march = (int) DB::table('contribution_periods')->where('month', '2027-03-01')->value('id');

    $this->actingAs($member)->get(route('fund.transactions.create'))->assertInertia(fn (Assert $page) => $page->component('fund/TransactionForm')->where('periods.0.month', '2027-01')->where('periods.1.month', '2027-02')->where('periods.2.month', '2027-03'));
    $this->actingAs($member)->post(route('fund.transactions.store'), [
        'idempotency_key' => (string) Str::uuid(), 'bank_id' => $bankId, 'reference' => 'MARCH-FIRST',
        'transaction_date' => '2027-03-12', 'amount' => '25.00', 'period_ids' => [$march], 'installment_ids' => [],
        'evidence' => UploadedFile::fake()->create('marzo.pdf', 1, 'application/pdf'),
    ])->assertSessionHasErrors('period_ids');
    $this->actingAs($member)->post(route('fund.transactions.store'), [
        'idempotency_key' => (string) Str::uuid(), 'bank_id' => $bankId, 'reference' => 'JANUARY-FIRST',
        'transaction_date' => '2027-03-12', 'amount' => '25.00', 'period_ids' => [$january], 'installment_ids' => [],
        'evidence' => UploadedFile::fake()->create('enero.pdf', 1, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('fund_transactions', ['reference' => 'JANUARY-FIRST', 'status' => 'pending']);
    $this->assertDatabaseCount('journal_entries', 0);
});

test('the initial configured period cannot move backwards after a transaction references it', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-12 15:00:00', 'UTC'));
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $march = (int) DB::table('contribution_periods')->where('month', '2027-03-01')->value('id');
    app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'FIRST-PERIOD', 'transaction_date' => '2027-03-12',
        'amount' => '25.00', 'period_ids' => [$march], 'installment_ids' => [],
    ], UploadedFile::fake()->create('marzo.pdf', 1, 'application/pdf'));

    expect(fn () => app(FundContributions::class)->setPeriod($treasurer, (string) Str::uuid(), '2027-01', '25.00'))
        ->toThrow(ValidationException::class);
    $this->assertDatabaseMissing('contribution_periods', ['month' => '2027-01-01']);
    $this->assertDatabaseCount('journal_entries', 0);
});

test('MySQL forbids rewriting an approved journal line or deleting an audit event', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $periodId = (int) DB::table('contribution_periods')->value('id');
    $id = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'IMMUTABLE-1', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$periodId], 'installment_ids' => [],
    ], UploadedFile::fake()->create('comprobante.pdf', 1, 'application/pdf'));
    app(FundTransactions::class)->approve($treasurer, (string) Str::uuid(), $id);

    expect(fn () => DB::table('journal_lines')->where('account', 'cash')->update(['amount_cents' => 9999]))->toThrow(QueryException::class);
    expect(fn () => DB::table('operation_events')->where('event', 'transaction.approved')->delete())->toThrow(QueryException::class);
    $this->assertDatabaseHas('journal_lines', ['account' => 'cash', 'amount_cents' => 2500]);
    $this->assertDatabaseHas('operation_events', ['event' => 'transaction.approved', 'subject_id' => $id]);
});
