<?php

use App\Actions\Fund\CalculateAmortization;
use App\Actions\Fund\Money;
use Carbon\CarbonImmutable;

test('the fixed payment schedule reconciles cents and restores the original day after february', function () {
    $schedule = (new CalculateAmortization)->handle(100000, '1', 3, CarbonImmutable::parse('2027-01-31', 'America/Guayaquil'));

    expect(array_column($schedule, 'capital_cents'))->toBe([33002, 33332, 33666]);
    expect(array_column($schedule, 'interest_cents'))->toBe([1000, 670, 337]);
    expect(array_column($schedule, 'due_on'))->toBe(['2027-02-28', '2027-03-31', '2027-04-30']);
    expect($schedule[2]['balance_cents'])->toBe(0);
});

test('zero interest reconciles the last cent', function () {
    $schedule = (new CalculateAmortization)->handle(10000, '0', 3, CarbonImmutable::parse('2027-01-01', 'America/Guayaquil'));

    expect(array_column($schedule, 'capital_cents'))->toBe([3333, 3333, 3334]);
    expect(array_column($schedule, 'interest_cents'))->toBe([0, 0, 0]);
    expect(Money::format(10000))->toBe('100.00');
});
