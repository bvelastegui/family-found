<?php

use App\Actions\Fund\FundAdministration;
use App\Actions\Fund\FundContributions;
use App\Actions\Fund\FundLoans;
use App\Actions\Fund\FundTransactions;
use App\Actions\Fund\InstallFund;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('the authenticated start highlights the first unpaid period without counting a pending transfer', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $period = DB::table('contribution_periods')->first();
    $transaction = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'NAV-PENDING', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [(int) $period->id], 'installment_ids' => [],
    ], UploadedFile::fake()->create('comprobante.pdf', 1, 'application/pdf'));

    $this->actingAs($member)->get(route('dashboard'))->assertRedirect(route('fund.contributions.index'));
    $this->actingAs($member)->get(route('fund.contributions.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Contributions')->where('totalCents', 0)
        ->missing('pendingTransactions')->missing('upcomingInstallments')->missing('hasLoans'));
    $this->actingAs($member)->get(route('fund.index'))->assertRedirect(route('dashboard'));
    $this->assertDatabaseCount('journal_entries', 0);
});

test('the contribution calendar shows the configured first month and links its approved receipt', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $period = DB::table('contribution_periods')->first();
    $transaction = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'NAV-APPROVED', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [(int) $period->id], 'installment_ids' => [],
    ], UploadedFile::fake()->create('comprobante.pdf', 1, 'application/pdf'));
    app(FundTransactions::class)->approve($treasurer, (string) Str::uuid(), $transaction);

    $monthIndex = (int) now('America/Guayaquil')->format('n') - 1;
    $this->actingAs($member)->get(route('fund.contributions.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Contributions')->where('totalCents', 2500)->where('yearTotalCents', 2500)->has('periods', 12)
        ->where("periods.{$monthIndex}.month", substr((string) $period->month, 0, 7))
        ->where("periods.{$monthIndex}.status", 'paid')->where("periods.{$monthIndex}.transaction_id", $transaction));
});

test('the contribution calendar displays twelve months per year and allows switching between years', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-12 15:00:00', 'UTC'));
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    app(FundContributions::class)->setPeriod($treasurer, (string) Str::uuid(), '2026-01', '25.00');
    app(FundContributions::class)->setPeriod($treasurer, (string) Str::uuid(), '2027-01', '25.00');
    app(FundContributions::class)->setPeriod($treasurer, (string) Str::uuid(), '2027-04', '25.00');
    $january = (int) DB::table('contribution_periods')->where('month', '2026-01-01')->value('id');
    $transaction = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'YEAR-2026', 'transaction_date' => '2027-03-12',
        'amount' => '25.00', 'period_ids' => [$january], 'installment_ids' => [],
    ], UploadedFile::fake()->create('enero.pdf', 1, 'application/pdf'));
    app(FundTransactions::class)->approve($treasurer, (string) Str::uuid(), $transaction);

    $this->actingAs($member)->get(route('fund.contributions.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Contributions')->where('year', 2027)->where('years', [2026, 2027])
        ->has('periods', 12)->where('periods.0.month', '2027-01')->where('periods.0.status', 'unpaid')
        ->where('periods.1.status', 'unconfigured')->where('periods.2.month', '2027-03')
        ->where('periods.3.status', 'upcoming')
        ->where('totalCents', 2500)->where('yearTotalCents', 0));
    $this->actingAs($member)->get(route('fund.contributions.index', ['year' => 2026]))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Contributions')->where('year', 2026)->has('periods', 12)
        ->where('periods.0.month', '2026-01')->where('periods.0.status', 'paid')
        ->where('periods.0.transaction_id', $transaction)->where('yearTotalCents', 2500));
    $this->actingAs($member)->get(route('fund.contributions.index', ['year' => 2025]))->assertNotFound();
});

test('an unconfigured calendar keeps its twelve months without inventing contributions', function () {
    app(InstallFund::class)->handle('Administradora', 'empty-calendar@familia.test', 'password-seguro-inicial');
    $member = User::factory()->create();

    $this->actingAs($member)->get(route('fund.contributions.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Contributions')->where('hasConfiguredPeriods', false)->has('periods', 12)
        ->where('periods.0.status', 'unconfigured')->where('totalCents', 0));
});

test('an empty loan list reports one page without a loan record', function () {
    [, $member] = prepareFund();

    $this->actingAs($member)->get(route('fund.loans.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Loans')->has('loans.data', 0)->where('loans.last_page', 1)
        ->missing('users')->missing('banks'));
});

test('only the treasurer can open the dedicated loan reservation screen', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $periodId = (int) DB::table('contribution_periods')->value('id');
    $deposit = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'LOAN-CREATE-FUND', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$periodId], 'installment_ids' => [],
    ], UploadedFile::fake()->create('aporte.pdf', 1, 'application/pdf'));
    app(FundTransactions::class)->approve($treasurer, (string) Str::uuid(), $deposit);

    $this->actingAs($member)->get(route('fund.treasury.loans.create'))->assertForbidden();
    $this->actingAs($treasurer)->get(route('fund.treasury.loans.create'))
        ->assertInertia(fn (Assert $page) => $page->component('fund/LoanCreate')->where('availableCents', 2500)
            ->has('users', 2));
    $this->actingAs($member)->get(route('fund.loans.index'))
        ->assertInertia(fn (Assert $page) => $page->component('fund/Loans')->where('isTreasurer', false)
            ->where('reservedLoans', null)->missing('users')->missing('banks'));
    $loanId = app(FundLoans::class)->reserve($treasurer, (string) Str::uuid(), [
        'user_id' => $member->id, 'amount' => '20.00', 'monthly_rate' => '2.00', 'term_months' => 2,
    ]);
    $this->actingAs($treasurer)->get(route('fund.loans.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Loans')->where('reservedLoans.total', 1)->where('reservedLoans.data.0.id', $loanId)
        ->where('reservedLoans.data.0.principal_cents', 2000));
});

test('disbursement correction gets its own treasury form and explains dependent payments before editing', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $periodId = (int) DB::table('contribution_periods')->value('id');
    $transactions = app(FundTransactions::class);
    $deposit = $transactions->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'LOAN-CORRECTION-FUND', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$periodId], 'installment_ids' => [],
    ], UploadedFile::fake()->create('aporte.pdf', 1, 'application/pdf'));
    $transactions->approve($treasurer, (string) Str::uuid(), $deposit);
    $loans = app(FundLoans::class);
    $loanId = $loans->reserve($treasurer, (string) Str::uuid(), [
        'user_id' => $member->id, 'amount' => '20.00', 'monthly_rate' => '0', 'term_months' => 2,
    ]);

    $this->actingAs($treasurer)->get(route('fund.loans.correction', $loanId))->assertStatus(409);
    $loans->disburse($treasurer, (string) Str::uuid(), $loanId, [
        'bank_id' => $bankId, 'reference' => 'DISBURSE-CORRECTION', 'transaction_date' => now('America/Guayaquil')->toDateString(), 'amount' => '20.00',
    ], UploadedFile::fake()->create('desembolso.pdf', 1, 'application/pdf'));

    $this->actingAs($member)->get(route('fund.loans.correction', $loanId))->assertForbidden();
    $this->actingAs($treasurer)->get(route('fund.loans.correction', $loanId))
        ->assertInertia(fn (Assert $page) => $page->component('fund/LoanCorrection')->where('hasDependentPayments', false)
            ->where('loan.principal_cents', 2000)->where('loan.user.name', $member->name)->has('banks', 1));
    $this->actingAs($member)->get(route('fund.loans.index'))
        ->assertInertia(fn (Assert $page) => $page->component('fund/Loans')->has('loans.data', 1)
            ->where('loans.data.0.outstanding_cents', 2000));

    $installmentId = (int) DB::table('loan_installments')->where('loan_id', $loanId)->orderBy('number')->value('id');
    $transactions->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'PAYMENT-BLOCK-CORRECTION', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '10.00', 'period_ids' => [], 'installment_ids' => [$installmentId],
    ], UploadedFile::fake()->create('cuota.pdf', 1, 'application/pdf'));

    $this->actingAs($treasurer)->get(route('fund.loans.correction', $loanId))
        ->assertInertia(fn (Assert $page) => $page->component('fund/LoanCorrection')->where('hasDependentPayments', true));
    $this->actingAs($member)->post(route('fund.loans.correct', $loanId), [])->assertForbidden();
    $this->assertDatabaseCount('journal_entries', 2);
});

test('transaction history filters by status and destination without exposing another members records', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $other = User::factory()->create();
    $periodId = (int) DB::table('contribution_periods')->value('id');
    app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'NAV-OWN', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$periodId], 'installment_ids' => [],
    ], UploadedFile::fake()->create('propio.pdf', 1, 'application/pdf'));
    app(FundTransactions::class)->register($other, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'NAV-OTHER', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$periodId], 'installment_ids' => [],
    ], UploadedFile::fake()->create('ajeno.pdf', 1, 'application/pdf'));

    $this->actingAs($member)->get(route('fund.transactions.index', ['status' => 'pending', 'type' => 'contribution']))
        ->assertInertia(fn (Assert $page) => $page->component('fund/Transactions')->has('transactions.data', 1)
            ->where('transactions.data.0.reference', 'NAV-OWN')->where('filters.status', 'pending'));
    $this->actingAs($treasurer)->get(route('fund.transactions.index', ['status' => 'pending']))
        ->assertInertia(fn (Assert $page) => $page->component('fund/Transactions')->has('transactions.data', 0));
    $this->actingAs($treasurer)->get(route('fund.treasury.reconciliation.index', ['status' => 'pending']))
        ->assertInertia(fn (Assert $page) => $page->component('fund/Transactions')->has('transactions.data', 2));
    $this->actingAs($member)->get(route('fund.transactions.index', ['status' => 'unknown']))->assertNotFound();
});

test('treasury and correction screens remain protected after treasurer handover', function () {
    Storage::fake('fund');
    [$administrator, $member, $bankId] = prepareFund();
    $periodId = (int) DB::table('contribution_periods')->value('id');
    $transaction = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'NAV-CORRECT', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$periodId], 'installment_ids' => [],
    ], UploadedFile::fake()->create('correccion.pdf', 1, 'application/pdf'));

    $this->actingAs($member)->get(route('fund.treasury.index'))->assertForbidden();
    $this->actingAs($administrator)->get(route('fund.treasury.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Treasury')->where('pendingCount', 1)->where('balances.cash', 0));
    $this->actingAs($administrator)->get(route('fund.transactions.edit', $transaction))->assertStatus(409);
    app(FundTransactions::class)->approve($administrator, (string) Str::uuid(), $transaction);
    $this->actingAs($administrator)->get(route('fund.transactions.edit', $transaction))->assertInertia(fn (Assert $page) => $page
        ->component('fund/TransactionCorrection')->where('transaction.id', $transaction)->where('selectedPeriodIds.0', $periodId));
    $this->actingAs($member)->get(route('fund.transactions.edit', $transaction))->assertForbidden();

    app(FundAdministration::class)->treasurer($administrator, (string) Str::uuid(), $member->id);
    $this->actingAs($administrator)->get(route('fund.treasury.index'))->assertForbidden();
    $this->actingAs($administrator)->get(route('fund.treasury.contributions.index'))->assertForbidden();
    $this->actingAs($administrator)->get(route('fund.treasury.reconciliation.index'))->assertForbidden();
    $this->actingAs($administrator)->get(route('fund.treasury.participants.index'))->assertForbidden();
    $this->actingAs($member)->get(route('fund.treasury.index'))->assertOk();
    $this->actingAs($member)->get(route('fund.treasury.contributions.index'))->assertOk();
    $this->actingAs($member)->get(route('fund.treasury.reconciliation.index'))->assertOk();
    $this->actingAs($administrator)->get(route('dashboard'))->assertRedirect(route('fund.contributions.index'));
    $this->actingAs($member)->get(route('dashboard'))->assertRedirect(route('fund.contributions.index'));
    $this->actingAs($administrator)->get(route('fund.contribution-periods.index'))->assertForbidden();
    $this->actingAs($administrator)->get(route('fund.banks.index'))->assertForbidden();
    $this->actingAs($member)->get(route('fund.banks.index'))->assertOk();
});

test('treasury catalog pages render separate resources and do not leak to members', function () {
    [$treasurer, $member] = prepareFund();

    $this->actingAs($treasurer)->get(route('fund.contribution-periods.index'))
        ->assertInertia(fn (Assert $page) => $page->component('fund/ContributionSettings')->has('periods.data', 1));
    $this->actingAs($treasurer)->get(route('fund.banks.index'))
        ->assertInertia(fn (Assert $page) => $page->component('fund/Banks')->has('banks.data', 1));
    $this->actingAs($member)->get(route('fund.banks.index'))->assertForbidden();
});

test('transaction detail attributes registration and approval to their actual actors', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $periodId = (int) DB::table('contribution_periods')->value('id');
    $transaction = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'TRACE-ACTORS', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$periodId], 'installment_ids' => [],
    ], UploadedFile::fake()->create('registro.pdf', 1, 'application/pdf'));

    $this->actingAs($member)->get(route('fund.transactions.show', $transaction))
        ->assertInertia(fn (Assert $page) => $page->component('fund/Transaction')->has('events', 1)
            ->where('events.0.event', 'transaction.registered')
            ->where('events.0.actor_id', $member->id)
            ->where('events.0.actor_name', $member->name));

    app(FundTransactions::class)->approve($treasurer, (string) Str::uuid(), $transaction);

    $this->actingAs($member)->get(route('fund.transactions.show', $transaction))
        ->assertInertia(fn (Assert $page) => $page->component('fund/Transaction')->has('events', 2)
            ->where('events.1.event', 'transaction.approved')
            ->where('events.1.actor_id', $treasurer->id)
            ->where('events.1.actor_name', $treasurer->name)
            ->has('events.1.created_at'));
    $this->actingAs($member)->get(route('fund.transactions.index', ['status' => 'approved']))
        ->assertInertia(fn (Assert $page) => $page->component('fund/Transactions')
            ->where('transactions.data.0.approved_by', $treasurer->name)
            ->has('transactions.data.0.approved_at'));
});

test('rejection history preserves the reviewers identity and multiline explanation', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $periodId = (int) DB::table('contribution_periods')->value('id');
    $transaction = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'TRACE-REJECT', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$periodId], 'installment_ids' => [],
    ], UploadedFile::fake()->create('registro.pdf', 1, 'application/pdf'));
    $reason = "Comprobante ilegible.\nSolicita uno nuevo.";
    app(FundTransactions::class)->reject($treasurer, (string) Str::uuid(), $transaction, $reason);

    $this->actingAs($member)->get(route('fund.transactions.show', $transaction))
        ->assertInertia(fn (Assert $page) => $page->component('fund/Transaction')->has('events', 2)
            ->where('events.1.event', 'transaction.rejected')
            ->where('events.1.actor_name', $treasurer->name)
            ->where('events.1.data', fn (string $data): bool => json_decode($data, true)['reason'] === $reason));
});
