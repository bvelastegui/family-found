<?php

use App\Actions\Fund\FundAdministration;
use App\Actions\Fund\FundTransactions;
use App\Actions\Fund\RecordFundEvent;
use App\Models\ContributionPeriod;
use App\Models\Evidence;
use App\Models\FundSetting;
use App\Models\FundTransaction;
use App\Models\Loan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** @return array{User, User, int, User} */
function prepareAuditedFund(): array
{
    [$treasurer, $member, $bankId] = prepareFund();
    $auditor = User::factory()->create(['name' => 'Auditor independiente']);
    app(FundAdministration::class)->auditor($treasurer, (string) Str::uuid(), $auditor->id);

    return [$treasurer, $member, $bankId, $auditor];
}

function registerAuditReceipt(User $member, int $bankId): int
{
    return app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId, 'reference' => 'AUDIT-RECEIPT', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [(int) DB::table('contribution_periods')->value('id')], 'installment_ids' => [],
    ], UploadedFile::fake()->create('audit.pdf', 1, 'application/pdf'));
}

test('the auditor has read access to financial history and receipts but cannot operate treasury', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId, $auditor] = prepareAuditedFund();
    $id = registerAuditReceipt($member, $bankId);
    $transaction = FundTransaction::query()->findOrFail($id);
    $loan = Loan::factory()->create(['user_id' => $member->id]);

    $this->get(route('fund.audit.index'))->assertRedirect(route('login'));

    $this->actingAs($auditor)->get(route('fund.audit.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Audit')->where('fundRoles.auditor', true)->where('fundRoles.treasurer', false));
    $this->get(route('fund.audit.controls'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/AuditControls')->where('findings.total', 0)->has('checks', 10));
    $this->get(route('fund.audit.transactions.show', $id))->assertInertia(fn (Assert $page) => $page
        ->component('fund/AuditRecord')->where('record.participant_name', $member->name)->has('allocations', 1)->has('entries', 0));
    $this->get(route('fund.audit.loans.show', $loan))->assertInertia(fn (Assert $page) => $page->where('record.id', $loan->id));
    $this->get(route('fund.evidences.show', $transaction->evidence_id))->assertDownload('audit.pdf');
    foreach (['approve', 'reject', 'correct'] as $action) {
        $this->post(route('fund.transactions.'.$action, $id), ['idempotency_key' => (string) Str::uuid(), 'reason' => 'Prueba'])->assertForbidden();
    }
    foreach (['disburse', 'cancel', 'correct'] as $action) {
        $this->post(route('fund.loans.'.$action, $loan), ['idempotency_key' => (string) Str::uuid(), 'reason' => 'Prueba'])->assertForbidden();
    }
    foreach (['fund.loans.store', 'fund.banks.store', 'fund.contribution-periods.store', 'fund.treasury.participants.invite'] as $route) {
        $this->post(route($route), [])->assertForbidden();
    }
    $this->get(route('fund.treasury.index'))->assertForbidden();
    $this->assertDatabaseHas('fund_transactions', ['id' => $id, 'status' => 'pending']);
    $this->assertDatabaseCount('journal_entries', 0);
    Storage::disk('fund')->assertExists($transaction->evidence->path);
    foreach ([$treasurer, $member] as $user) {
        $this->actingAs($user)->get(route('fund.audit.index'))->assertForbidden();
        $this->get(route('fund.audit.transactions.show', $id))->assertForbidden();
        $this->get(route('fund.audit.controls'))->assertForbidden();
    }
    app(FundAdministration::class)->auditor($treasurer, (string) Str::uuid(), $member->id);
    $this->actingAs($auditor)->get(route('fund.evidences.show', $transaction->evidence_id))->assertForbidden();
});

test('auditor designation is audited cannot overlap treasury and takes effect immediately', function () {
    [$treasurer, $member, $bankId, $auditor] = prepareAuditedFund();
    expect(fn () => app(FundAdministration::class)->auditor($treasurer, (string) Str::uuid(), $treasurer->id))->toThrow(ValidationException::class);
    expect(fn () => app(FundAdministration::class)->treasurer($treasurer, (string) Str::uuid(), $auditor->id))->toThrow(ValidationException::class);
    $this->artisan('fund:assign-treasurer', ['name' => $auditor->name])->assertFailed();
    expect(fn () => app(FundAdministration::class)->auditor($member, (string) Str::uuid(), $member->id))->toThrow(HttpException::class);
    $this->artisan('fund:assign-auditor', ['email' => $member->email])->assertSuccessful();

    expect(FundSetting::current()->auditor_id)->toBe($member->id);
    $this->actingAs($auditor)->get(route('fund.audit.index'))->assertForbidden();
    $this->actingAs($member)->get(route('fund.audit.index'))->assertInertia(fn (Assert $page) => $page->where('fundRoles.auditor', true));
    $this->assertDatabaseHas('operation_events', ['event' => 'auditor.designated', 'actor_id' => $treasurer->id]);
    $this->artisan('fund:assign-auditor', ['email' => $treasurer->email])->assertFailed();
    $this->artisan('fund:assign-auditor', ['email' => 'missing@example.test'])->assertFailed();
    expect(FundSetting::current()->auditor_id)->toBe($member->id);
});

test('the database also prevents overlapping auditor and treasurer assignments', function () {
    [$treasurer] = prepareFund();

    expect(fn () => DB::table('fund_settings')->where('id', 1)->update(['auditor_id' => $treasurer->id]))->toThrow(QueryException::class);
});

test('audit dates use Ecuador days and preserve filters in pagination and detail navigation', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-05 23:30:00', 'America/Guayaquil'));
    Storage::fake('fund');
    [$treasurer, $member, $bankId, $auditor] = prepareAuditedFund();
    $id = registerAuditReceipt($member, $bankId);
    $this->travelTo(CarbonImmutable::parse('2027-03-06 00:30:00', 'America/Guayaquil'));
    app(FundTransactions::class)->approve($treasurer, (string) Str::uuid(), $id);

    $this->actingAs($auditor)->get(route('fund.audit.index', ['from' => '2027-03-05', 'to' => '2027-03-05', 'type' => 'transaction', 'search' => 'AUDIT-RECEIPT']))
        ->assertInertia(fn (Assert $page) => $page->component('fund/Audit')->where('events.total', 1)
            ->where('events.data.0.title', 'Comprobante registrado')->where('events.data.0.actor_name', $member->name));
    for ($index = 0; $index < 22; $index++) {
        app(RecordFundEvent::class)->handle($treasurer, 'bank.saved', 'bank', $bankId);
    }
    $returnTo = route('fund.audit.index', ['type' => 'bank', 'page' => 2], false);
    $this->get(route('fund.audit.index', ['type' => 'bank', 'page' => 2]))->assertInertia(fn (Assert $page) => $page
        ->where('events.current_page', 2)->where('events.links.1.url', fn (string $url): bool => str_contains($url, 'type=bank')));
    $this->get(route('fund.audit.transactions.show', ['transaction' => $id, 'return_to' => $returnTo]))->assertInertia(fn (Assert $page) => $page->where('returnTo', $returnTo));
    $this->get(route('fund.audit.transactions.show', ['transaction' => $id, 'return_to' => 'https://example.com']))->assertInertia(fn (Assert $page) => $page->where('returnTo', '/fund/audit'));
    $this->get(route('fund.audit.index', ['search' => '%']))->assertInertia(fn (Assert $page) => $page->where('events.total', 0));
    $this->get(route('fund.audit.index', ['from' => '2027-03-06', 'to' => '2027-03-05']))->assertSessionHasErrors('to');
    $this->get(route('fund.audit.controls', ['check' => 'unknown']))->assertSessionHasErrors('check');
});

test('valid corrections retain the original reversal and replacement without false findings', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId, $auditor] = prepareAuditedFund();
    $id = registerAuditReceipt($member, $bankId);
    $transactions = app(FundTransactions::class);
    $transactions->approve($treasurer, (string) Str::uuid(), $id);
    $replacement = $transactions->correct($treasurer, (string) Str::uuid(), $id, [
        'bank_id' => $bankId, 'reference' => 'AUDIT-CORRECTED', 'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00', 'period_ids' => [(int) DB::table('contribution_periods')->value('id')], 'installment_ids' => [], 'reason' => 'Referencia corregida',
    ], UploadedFile::fake()->create('corrected.pdf', 1, 'application/pdf'));
    $eventId = (int) DB::table('operation_events')->where('event', 'transaction.corrected')->value('id');

    $this->actingAs($auditor)->get(route('fund.audit.events.show', $eventId))->assertInertia(fn (Assert $page) => $page
        ->component('fund/AuditRecord')->where('selectedEvent', $eventId)->where('record.replacement_id', $replacement)
        ->has('entries', 2)->has('events', 3)->where('events.2.data.reason', 'Referencia corregida'));
    $this->get(route('fund.audit.transactions.show', $replacement))->assertInertia(fn (Assert $page) => $page
        ->where('record.previous_id', $id)->has('entries', 1));
    $this->get(route('fund.audit.controls'))->assertInertia(fn (Assert $page) => $page->where('findings.total', 0));
    $this->assertDatabaseCount('journal_entries', 3);
    Storage::disk('fund')->assertExists(FundTransaction::query()->with('evidence')->findOrFail($replacement)->evidence->path);
});

test('controls detect missing postings and missing receipt files without rewriting history', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId, $auditor] = prepareAuditedFund();
    $id = registerAuditReceipt($member, $bankId);
    FundTransaction::query()->whereKey($id)->update(['status' => 'approved']);
    $receipt = FundTransaction::query()->with('evidence')->findOrFail($id)->evidence;
    Storage::disk('fund')->delete($receipt->path);

    $this->actingAs($auditor)->get(route('fund.audit.controls', ['check' => 'missing_transaction_entries']))->assertInertia(fn (Assert $page) => $page
        ->where('findings.total', 1)->where('findings.data.0.entity_id', $id)->where('findings.data.0.check_key', 'missing_transaction_entries'));
    $this->get(route('fund.audit.controls', ['check' => 'missing_evidence']))->assertInertia(fn (Assert $page) => $page
        ->where('findings.total', 1)->where('findings.data.0.entity_type', 'transaction')->where('findings.data.0.entity_id', $id));
    $this->get(route('fund.evidences.show', $receipt))->assertNotFound();
    $this->assertDatabaseCount('journal_entries', 0);
    $this->assertDatabaseHas('fund_transactions', ['id' => $id, 'status' => 'approved']);
    Storage::disk('fund')->assertMissing($receipt->path);
});

test('controls detect changed allocations and unbalanced ledger lines', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId, $auditor] = prepareAuditedFund();
    $id = registerAuditReceipt($member, $bankId);
    app(FundTransactions::class)->approve($treasurer, (string) Str::uuid(), $id);
    $entryId = (int) DB::table('journal_entries')->where('fund_transaction_id', $id)->value('id');
    $period = ContributionPeriod::factory()->create(['month' => now('America/Guayaquil')->startOfMonth()->addMonth()->toDateString()]);
    DB::table('transaction_allocations')->insert(['fund_transaction_id' => $id, 'contribution_period_id' => $period->id, 'amount_cents' => 2500]);
    DB::table('journal_lines')->insert(['journal_entry_id' => $entryId, 'account' => 'interest', 'side' => 'credit', 'amount_cents' => 100, 'user_id' => $member->id]);

    $this->actingAs($auditor)->get(route('fund.audit.controls', ['check' => 'allocation_mismatch']))->assertInertia(fn (Assert $page) => $page
        ->where('findings.total', 1)->where('findings.data.0.expected_cents', 2500)->where('findings.data.0.actual_cents', 5000));
    $this->get(route('fund.audit.controls', ['check' => 'unbalanced_entries']))->assertInertia(fn (Assert $page) => $page
        ->where('findings.total', 1)->where('findings.data.0.entity_id', $entryId));
    $this->get(route('fund.audit.controls', ['check' => 'transaction_posting_mismatch']))->assertInertia(fn (Assert $page) => $page->where('findings.total', 1));
    Storage::disk('fund')->assertExists(FundTransaction::query()->with('evidence')->findOrFail($id)->evidence->path);
});

test('a balanced reversal with the wrong amount or owner is still detected', function (bool $wrongOwner) {
    Storage::fake('fund');
    [$treasurer, $member, $bankId, $auditor] = prepareAuditedFund();
    $id = registerAuditReceipt($member, $bankId);
    app(FundTransactions::class)->approve($treasurer, (string) Str::uuid(), $id);
    $original = (int) DB::table('journal_entries')->where('fund_transaction_id', $id)->value('id');
    $reversal = DB::table('journal_entries')->insertGetId(['actor_id' => $treasurer->id, 'reversal_of_id' => $original, 'created_at' => now()]);
    DB::table('journal_lines')->insert([
        ['journal_entry_id' => $reversal, 'account' => 'cash', 'side' => 'credit', 'amount_cents' => $wrongOwner ? 2500 : 2400, 'user_id' => $wrongOwner ? $treasurer->id : $member->id],
        ['journal_entry_id' => $reversal, 'account' => 'contributions', 'side' => 'debit', 'amount_cents' => $wrongOwner ? 2500 : 2400, 'user_id' => $wrongOwner ? $treasurer->id : $member->id],
    ]);

    $this->actingAs($auditor)->get(route('fund.audit.controls', ['check' => 'invalid_reversals']))->assertInertia(fn (Assert $page) => $page
        ->where('findings.total', 1)->where('findings.data.0.entity_id', (int) $reversal));
    $this->get(route('fund.audit.controls', ['check' => 'unbalanced_entries']))->assertInertia(fn (Assert $page) => $page->where('findings.total', 0));
    $this->get(route('fund.audit.entries.show', $reversal))->assertInertia(fn (Assert $page) => $page
        ->where('source.type', 'entry')->where('source.id', $original)->where('entries.0.reversal_of_id', $original));
    Storage::disk('fund')->assertExists(FundTransaction::query()->with('evidence')->findOrFail($id)->evidence->path);
})->with(['wrong amount' => false, 'wrong owner' => true]);

test('controls detect posting a pending transfer and a journal without a financial origin', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId, $auditor] = prepareAuditedFund();
    $id = registerAuditReceipt($member, $bankId);
    $entryId = DB::table('journal_entries')->insertGetId(['actor_id' => $treasurer->id, 'fund_transaction_id' => $id, 'created_at' => now()]);
    DB::table('journal_lines')->insert([
        ['journal_entry_id' => $entryId, 'account' => 'cash', 'side' => 'debit', 'amount_cents' => 2500],
        ['journal_entry_id' => $entryId, 'account' => 'contributions', 'side' => 'credit', 'amount_cents' => 2500],
    ]);
    $orphan = DB::table('journal_entries')->insertGetId(['actor_id' => $treasurer->id, 'created_at' => now()]);

    $this->actingAs($auditor)->get(route('fund.audit.controls', ['check' => 'unexpected_transaction_entries']))->assertInertia(fn (Assert $page) => $page
        ->where('findings.total', 1)->where('findings.data.0.entity_id', $id));
    $this->get(route('fund.audit.controls', ['check' => 'invalid_entry_origins']))->assertInertia(fn (Assert $page) => $page
        ->where('findings.total', 1)->where('findings.data.0.entity_id', (int) $orphan));
    Storage::disk('fund')->assertExists(FundTransaction::query()->with('evidence')->findOrFail($id)->evidence->path);
});

test('loan controls detect absent postings evidence and a balanced but incorrect disbursement', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId, $auditor] = prepareAuditedFund();
    $missingReceipt = Evidence::factory()->create(['user_id' => $member->id, 'uploaded_by_id' => $treasurer->id]);
    Storage::disk('fund')->put($missingReceipt->path, 'dummy evidence file');
    $missing = Loan::factory()->create(['user_id' => $member->id, 'status' => 'disbursed', 'principal_cents' => 3000, 'evidence_id' => $missingReceipt->id]);
    $wrongReceipt = Evidence::factory()->create(['user_id' => $member->id, 'uploaded_by_id' => $treasurer->id]);
    Storage::disk('fund')->put($wrongReceipt->path, 'dummy evidence file');
    $wrong = Loan::factory()->create(['user_id' => $member->id, 'status' => 'disbursed', 'principal_cents' => 3000, 'evidence_id' => $wrongReceipt->id]);
    $entryId = DB::table('journal_entries')->insertGetId(['actor_id' => $treasurer->id, 'loan_id' => $wrong->id, 'created_at' => now()]);
    DB::table('journal_lines')->insert([
        ['journal_entry_id' => $entryId, 'account' => 'loan_principal', 'side' => 'debit', 'amount_cents' => 2000, 'loan_id' => $wrong->id],
        ['journal_entry_id' => $entryId, 'account' => 'cash', 'side' => 'credit', 'amount_cents' => 2000, 'loan_id' => $wrong->id],
    ]);

    $this->actingAs($auditor)->get(route('fund.audit.controls', ['check' => 'missing_loan_entries']))->assertInertia(fn (Assert $page) => $page
        ->where('findings.total', 1)->where('findings.data.0.entity_id', $missing->id));
    $this->get(route('fund.audit.controls', ['check' => 'loan_posting_mismatch']))->assertInertia(fn (Assert $page) => $page
        ->where('findings.total', 1)->where('findings.data.0.entity_id', $wrong->id));
    $this->get(route('fund.audit.controls', ['check' => 'missing_evidence']))->assertInertia(fn (Assert $page) => $page
        ->where('findings.total', 0)->where('checks.9.count', 0));
    Storage::disk('fund')->assertExists([$missingReceipt->path, $wrongReceipt->path]);
});

test('audit control findings paginate with the selected check preserved', function () {
    [$treasurer, $member, $bankId, $auditor] = prepareAuditedFund();
    for ($index = 0; $index < 17; $index++) {
        DB::table('journal_entries')->insert(['actor_id' => $treasurer->id, 'created_at' => now()]);
    }

    $this->actingAs($auditor)->get(route('fund.audit.controls', ['check' => 'invalid_entry_origins', 'page' => 2]))->assertInertia(fn (Assert $page) => $page
        ->where('findings.total', 17)->where('findings.current_page', 2)->has('findings.data', 2)
        ->where('findings.links.1.url', fn (string $url): bool => str_contains($url, 'check=invalid_entry_origins')));
});
