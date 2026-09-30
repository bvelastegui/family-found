<?php

use App\Actions\Fund\FundBalances;
use App\Actions\Fund\FundLoans;
use App\Actions\Fund\FundTransactions;
use App\Enums\JournalAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

test('reserving money does not post to the ledger and approved repayments retain all interest in the fund', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $period = (int) DB::table('contribution_periods')->value('id');
    $transactions = app(FundTransactions::class);
    $deposit = $transactions->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'FUND-25', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$period], 'installment_ids' => [],
    ], UploadedFile::fake()->create('aporte.pdf', 1, 'application/pdf'));
    $transactions->approve($treasurer, (string) Str::uuid(), $deposit);
    $loans = app(FundLoans::class);

    $loanId = $loans->reserve($treasurer, (string) Str::uuid(), ['user_id' => $member->id, 'amount' => '25.00', 'monthly_rate' => '1', 'term_months' => 3]);

    expect(app(FundBalances::class)->summary()['available'])->toBe(0);
    $this->assertDatabaseCount('journal_entries', 1);
    $loans->disburse($treasurer, (string) Str::uuid(), $loanId, [
        'bank_id' => $bankId, 'reference' => 'LOAN-1', 'transaction_date' => now('America/Guayaquil')->toDateString(), 'amount' => '25.00',
    ], UploadedFile::fake()->create('prestamo.pdf', 1, 'application/pdf'));
    $installment = DB::table('loan_installments')->where('loan_id', $loanId)->orderBy('number')->first();
    expect((int) $installment->capital_cents)->toBe(825);
    expect((int) $installment->interest_cents)->toBe(25);
    $this->assertDatabaseCount('journal_entries', 2);
    $payment = $transactions->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'PAY-1', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '8.50', 'period_ids' => [], 'installment_ids' => [$installment->id],
    ], UploadedFile::fake()->create('pago.pdf', 1, 'application/pdf'));
    expect(app(FundBalances::class)->account(JournalAccount::Interest))->toBe(0);
    $transactions->approve($treasurer, (string) Str::uuid(), $payment);

    $summary = app(FundBalances::class)->summary();
    expect($summary['contributions'])->toBe(2500);
    expect($summary['principal'])->toBe(1675);
    expect($summary['interest'])->toBe(25);
    expect($summary['cash'])->toBe(850);
    expect($summary['available'])->toBe(850);
    $this->assertDatabaseCount('journal_entries', 3);
    $this->assertDatabaseHas('journal_lines', ['account' => 'interest', 'side' => 'credit', 'amount_cents' => 25]);
    $this->actingAs($member)->get(route('fund.loans.show', $loanId))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('fund/Loan')->where('outstandingCents', 1675)->has('loan.installments', 3));
});

test('a correction of an unused disbursement replaces the debt without creating spendable interim cash', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $period = (int) DB::table('contribution_periods')->value('id');
    $deposit = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'INVEST-1', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$period], 'installment_ids' => [],
    ], UploadedFile::fake()->create('aporte.pdf', 1, 'application/pdf'));
    app(FundTransactions::class)->approve($treasurer, (string) Str::uuid(), $deposit);
    $loans = app(FundLoans::class);
    $original = $loans->reserve($treasurer, (string) Str::uuid(), ['user_id' => $member->id, 'amount' => '20.00', 'monthly_rate' => '0', 'term_months' => 2]);
    $loans->disburse($treasurer, (string) Str::uuid(), $original, [
        'bank_id' => $bankId, 'reference' => 'DEPOSIT-1', 'transaction_date' => now('America/Guayaquil')->toDateString(), 'amount' => '20.00',
    ], UploadedFile::fake()->create('desembolso.pdf', 1, 'application/pdf'));

    $replacement = $loans->correct($treasurer, (string) Str::uuid(), $original, [
        'amount' => '20.00', 'monthly_rate' => '0', 'term_months' => 2, 'bank_id' => $bankId,
        'reference' => 'DEPOSIT-1', 'transaction_date' => now('America/Guayaquil')->toDateString(), 'reason' => 'Corregir comprobante',
    ], UploadedFile::fake()->create('corregido.pdf', 1, 'application/pdf'));

    expect($replacement)->not->toBe($original);
    expect(app(FundBalances::class)->summary()['available'])->toBe(500);
    expect(app(FundBalances::class)->summary()['principal'])->toBe(2000);
    $this->assertDatabaseHas('loans', ['id' => $original, 'status' => 'superseded', 'superseded_by_id' => $replacement]);
    $this->assertDatabaseCount('journal_entries', 4);
});

test('cancelling a reservation releases availability without creating financial movement', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $period = (int) DB::table('contribution_periods')->value('id');
    $deposit = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'FUNDED-1', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$period], 'installment_ids' => [],
    ], UploadedFile::fake()->create('aporte.pdf', 1, 'application/pdf'));
    app(FundTransactions::class)->approve($treasurer, (string) Str::uuid(), $deposit);
    $loanId = app(FundLoans::class)->reserve($treasurer, (string) Str::uuid(), [
        'user_id' => $member->id, 'amount' => '25.00', 'monthly_rate' => '0', 'term_months' => 2,
    ]);

    expect(app(FundBalances::class)->summary()['available'])->toBe(0);
    app(FundLoans::class)->cancel($treasurer, (string) Str::uuid(), $loanId, 'No hubo desembolso');

    expect(app(FundBalances::class)->summary()['available'])->toBe(2500);
    $this->assertDatabaseCount('journal_entries', 1);
    $this->assertDatabaseHas('loans', ['id' => $loanId, 'status' => 'cancelled']);
    $this->assertDatabaseHas('operation_events', ['event' => 'loan.cancelled', 'subject_id' => $loanId]);
});

test('loan installments cannot be paid partially and advance payments retain scheduled interest', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $period = (int) DB::table('contribution_periods')->value('id');
    $transactions = app(FundTransactions::class);
    $deposit = $transactions->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'CAPITAL-25', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$period], 'installment_ids' => [],
    ], UploadedFile::fake()->create('aporte.pdf', 1, 'application/pdf'));
    $transactions->approve($treasurer, (string) Str::uuid(), $deposit);
    $loanId = app(FundLoans::class)->reserve($treasurer, (string) Str::uuid(), ['user_id' => $member->id, 'amount' => '25.00', 'monthly_rate' => '1', 'term_months' => 3]);
    app(FundLoans::class)->disburse($treasurer, (string) Str::uuid(), $loanId, [
        'bank_id' => $bankId, 'reference' => 'DISBURSE-25', 'transaction_date' => now('America/Guayaquil')->toDateString(), 'amount' => '25.00',
    ], UploadedFile::fake()->create('prestamo.pdf', 1, 'application/pdf'));
    $installments = DB::table('loan_installments')->where('loan_id', $loanId)->orderBy('number')->get();

    $this->actingAs($member)->post(route('fund.transactions.store'), [
        'idempotency_key' => (string) Str::uuid(), 'bank_id' => $bankId, 'reference' => 'PARTIAL-1',
        'transaction_date' => now('America/Guayaquil')->toDateString(), 'amount' => '5.00',
        'period_ids' => [], 'installment_ids' => [$installments[0]->id],
        'evidence' => UploadedFile::fake()->create('parcial.pdf', 1, 'application/pdf'),
    ])->assertSessionHasErrors('amount');
    $payment = $transactions->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'ADVANCE-2', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '17.00', 'period_ids' => [], 'installment_ids' => [$installments[0]->id, $installments[1]->id],
    ], UploadedFile::fake()->create('anticipo.pdf', 1, 'application/pdf'));
    $transactions->approve($treasurer, (string) Str::uuid(), $payment);

    expect(app(FundBalances::class)->summary()['interest'])->toBe(42);
    $this->assertDatabaseHas('loan_installments', ['id' => $installments[2]->id, 'capital_cents' => 842, 'interest_cents' => 8]);
    $this->assertDatabaseCount('journal_entries', 3);
});

test('a failed disbursement preserves the reservation and a retry posts only once', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $period = (int) DB::table('contribution_periods')->value('id');
    $deposit = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'DEPOSIT-25', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [$period], 'installment_ids' => [],
    ], UploadedFile::fake()->create('deposito.pdf', 1, 'application/pdf'));
    app(FundTransactions::class)->approve($treasurer, (string) Str::uuid(), $deposit);
    $loans = app(FundLoans::class);
    $loanId = $loans->reserve($treasurer, (string) Str::uuid(), ['user_id' => $member->id, 'amount' => '20.00', 'monthly_rate' => '0', 'term_months' => 2]);
    $payload = ['bank_id' => $bankId, 'reference' => 'TRANSFER-20', 'transaction_date' => now('America/Guayaquil')->toDateString(), 'amount' => '20.00'];

    try {
        $loans->disburse($treasurer, (string) Str::uuid(), $loanId, [...$payload, 'amount' => '19.00'], UploadedFile::fake()->create('equivocado.pdf', 1, 'application/pdf'));
        $this->fail('Un desembolso distinto a la reserva debe rechazarse.');
    } catch (ValidationException) {
        $this->assertDatabaseHas('loans', ['id' => $loanId, 'status' => 'reserved', 'evidence_id' => null]);
        $this->assertDatabaseCount('journal_entries', 1);
    }
    $key = (string) Str::uuid();
    $loans->disburse($treasurer, $key, $loanId, $payload, UploadedFile::fake()->create('correcto.pdf', 1, 'application/pdf'));
    $loans->disburse($treasurer, $key, $loanId, $payload, UploadedFile::fake()->create('correcto.pdf', 1, 'application/pdf'));

    $this->assertDatabaseHas('loans', ['id' => $loanId, 'status' => 'disbursed']);
    $this->assertDatabaseCount('journal_entries', 2);
    $this->assertDatabaseCount('loan_installments', 2);
});
