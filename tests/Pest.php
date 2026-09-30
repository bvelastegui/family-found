<?php

use App\Actions\Fund\FundAdministration;
use App\Actions\Fund\FundContributions;
use App\Actions\Fund\InstallFund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/** @return array{User, User, int} */
function prepareFund(): array
{
    $fund = app(InstallFund::class)->handle('Administradora', 'admin@familia.test', 'password-seguro-inicial');
    $admin = User::findOrFail($fund->administrator_id);
    app(FundAdministration::class)->treasurer($admin, (string) Str::uuid(), $admin->id);
    $bankId = app(FundAdministration::class)->bank($admin, (string) Str::uuid(), 'Banco familiar', true);
    $member = User::factory()->create();
    app(FundContributions::class)->setPeriod($admin, (string) Str::uuid(), now('America/Guayaquil')->format('Y-m'), '25.00');

    return [$admin, $member, $bankId];
}
