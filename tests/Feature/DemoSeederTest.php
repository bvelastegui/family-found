<?php

use App\Actions\Fund\FundBalances;
use App\Actions\Fund\FundLoans;
use App\Actions\Fund\FundTransactions;
use App\Enums\LoanStatus;
use App\Enums\TransactionStatus;
use App\Models\Evidence;
use App\Models\FundSetting;
use App\Models\FundTransaction;
use App\Models\Loan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('demo seeding creates usable accounts and coherent financial scenarios across calendar boundaries', function (string $date) {
    $today = CarbonImmutable::parse($date, 'America/Guayaquil');
    $this->travelTo($today);
    Storage::fake('fund');

    $this->seed(DemoSeeder::class);

    $this->assertDatabaseCount('users', 20);
    $this->assertDatabaseCount('contribution_periods', 8);
    $this->assertDatabaseCount('fund_invitations', 3);
    $administrator = User::query()->where('email', 'admin.demo@example.test')->firstOrFail();
    $treasurer = User::query()->where('email', 'tesorero.demo@example.test')->firstOrFail();
    $member = User::query()->where('email', 'ana.demo@example.test')->firstOrFail();
    expect(FundSetting::current()->administrator_id)->toBe($administrator->id);
    expect(FundSetting::current()->treasurer_id)->toBe($treasurer->id);
    $auditor = User::query()->where('email', 'auditor.demo@example.test')->firstOrFail();
    expect(FundSetting::current()->auditor_id)->toBe($auditor->id);
    expect(Hash::check(DemoSeeder::PASSWORD, $member->password))->toBeTrue();
    expect(User::query()->whereNull('email_verified_at')->count())->toBe(0);
    expect(now()->equalTo($today))->toBeTrue();
    expect(CarbonImmutable::now()->equalTo($today))->toBeTrue();
    expect(FundTransaction::query()->where('status', TransactionStatus::Approved)->count())->toBe(102);
    expect(FundTransaction::query()->where('status', TransactionStatus::Pending)->count())->toBe(4);
    expect(FundTransaction::query()->where('status', TransactionStatus::Rejected)->count())->toBe(1);
    expect(Loan::query()->where('status', LoanStatus::Disbursed)->count())->toBe(3);
    expect(Loan::query()->where('status', LoanStatus::Reserved)->count())->toBe(2);
    expect(Loan::query()->where('status', LoanStatus::Cancelled)->count())->toBe(1);
    expect(DB::table('notifications')->whereNull('read_at')->count())->toBeGreaterThan(0);
    expect(DB::table('notifications')->whereNotNull('read_at')->count())->toBeGreaterThan(0);
    $balances = app(FundBalances::class)->summary();
    expect($balances['contributions'])->toBe(240000);
    expect($balances['reserved'])->toBe(25000);
    expect($balances['interest'])->toBeGreaterThan(0);
    expect($balances['principal'])->toBeGreaterThan(0)->toBeLessThan(100000);
    expect($balances['available'])->toBe($balances['cash'] - 25000)->toBeGreaterThan(0);
    expect(DB::table('journal_lines')->select('journal_entry_id')->groupBy('journal_entry_id')
        ->havingRaw('SUM(CASE WHEN side = ? THEN amount_cents ELSE -amount_cents END) <> 0', ['debit'])->exists())->toBeFalse();
    $paths = Evidence::query()->pluck('path')->all();
    Storage::disk('fund')->assertExists($paths);
    $receipt = Evidence::query()->firstOrFail();
    expect(Storage::disk('fund')->get($receipt->path))->toStartWith('%PDF-1.4')->toContain('Comprobante de demostracion', 'startxref', '%%EOF');

    $this->actingAs($treasurer)->get(route('fund.treasury.index'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/Treasury')->where('pendingCount', 4)->has('contributionChart', 6)
        ->where('contributionChart.5.received_cents', 27500));
    $this->get(route('fund.treasury.contributions.index', ['status' => 'pending']))->assertInertia(fn (Assert $page) => $page
        ->component('fund/TreasuryContributions')->where('participants.total', 3));
    $this->get(route('fund.treasury.contributions.index', ['month' => $today->subMonthNoOverflow()->format('Y-m'), 'status' => 'not_applicable']))
        ->assertInertia(fn (Assert $page) => $page->where('participants.total', 1)->where('participants.data.0.email', 'rosa.demo@example.test'));
    $this->actingAs($member)->get(route('fund.transactions.create'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/TransactionForm')->has('loans', 1)->where('hasPendingContribution', false));
    $this->actingAs($auditor)->get(route('fund.audit.controls'))->assertInertia(fn (Assert $page) => $page
        ->component('fund/AuditControls')->where('findings.total', 0));
})->with(['first day and previous year' => '2027-01-01 08:00:00', 'after the monthly cutoff' => '2027-03-15 16:00:00']);

test('repeating the demo seeder preserves subsequent decisions accounts and stored files', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-15 12:00:00', 'America/Guayaquil'));
    Storage::fake('fund');
    $this->seed(DemoSeeder::class);
    $treasurer = User::query()->where('email', 'tesorero.demo@example.test')->firstOrFail();
    $pending = FundTransaction::query()->where('status', TransactionStatus::Pending)->firstOrFail();
    app(FundTransactions::class)->approve($treasurer, (string) Str::uuid(), $pending->id);
    $treasurer->update(['password' => 'MiNuevaClaveDemo2027!']);
    $tables = ['users', 'banks', 'contribution_periods', 'fund_transactions', 'transaction_allocations', 'loans', 'loan_installments', 'evidences', 'journal_entries', 'journal_lines', 'operation_events', 'operation_requests', 'notifications', 'fund_invitations'];
    $counts = array_map(fn (string $table): int => DB::table($table)->count(), $tables);
    $paths = Storage::disk('fund')->allFiles();
    $this->travelTo(CarbonImmutable::parse('2027-04-15 12:00:00', 'America/Guayaquil'));

    $this->seed(DemoSeeder::class);

    foreach ($tables as $index => $table) {
        $this->assertDatabaseCount($table, $counts[$index]);
    }
    $this->assertDatabaseHas('fund_transactions', ['id' => $pending->id, 'status' => 'approved']);
    expect(Hash::check('MiNuevaClaveDemo2027!', $treasurer->fresh()->password))->toBeTrue();
    expect(Storage::disk('fund')->allFiles())->toBe($paths);
});

test('the demo seeder leaves an already installed fund untouched', function () {
    Storage::fake('fund');
    [$administrator] = prepareFund();

    expect(fn () => $this->seed(DemoSeeder::class))->toThrow(LogicException::class, 'DemoSeeder necesita una base de datos de demostración sin un fondo instalado.');

    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('contribution_periods', 1);
    expect(FundSetting::current()->administrator_id)->toBe($administrator->id);
    expect(FundSetting::current()->treasurer_id)->toBe($administrator->id);
    expect(Storage::disk('fund')->allFiles())->toBe([]);
});

test('a failed demo seed rolls back the dataset cleans receipts and restores the clock', function () {
    $today = CarbonImmutable::parse('2027-03-15 12:00:00', 'America/Guayaquil');
    $this->travelTo($today);
    Storage::fake('fund');
    $this->mock(FundLoans::class)->shouldReceive('reserve')->once()->andThrow(new RuntimeException('Desembolso de demostración interrumpido.'));

    expect(fn () => $this->seed(DemoSeeder::class))->toThrow(RuntimeException::class, 'Desembolso de demostración interrumpido.');

    foreach (['users', 'fund_settings', 'contribution_periods', 'fund_transactions', 'evidences', 'journal_entries', 'operation_events', 'notifications'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    expect(Storage::disk('fund')->allFiles())->toBe([]);
    expect(now()->equalTo($today))->toBeTrue();
    expect(CarbonImmutable::now()->equalTo($today))->toBeTrue();
});
