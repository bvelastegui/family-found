<?php

namespace App\Actions\Fund;

use Brick\Math\BigRational;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class CalculateAmortization
{
    /**
     * @return list<array{number: int, due_on: string, capital_cents: int, interest_cents: int, balance_cents: int}>
     */
    public function handle(int $principal, string $monthlyRate, int $months, CarbonImmutable $date): array
    {
        if ($principal <= 0 || $months < 1 || $months > 65535 || ! preg_match('/^\d{1,6}(?:\.\d{1,6})?$/D', $monthlyRate) || $date->addMonthsNoOverflow($months)->year > 9999) {
            throw ValidationException::withMessages(['loan' => 'El monto, tasa o plazo del préstamo no es válido.']);
        }

        try {
            $rate = BigRational::of($monthlyRate)->dividedBy(100);
            $payment = $rate->isZero()
                ? BigRational::of($principal)->dividedBy($months)
                : BigRational::of($principal)->multipliedBy($rate)->dividedBy(BigRational::one()->minus($rate->plus(1)->power(-$months)));
            $regular = $payment->toScale(0, RoundingMode::HalfUp)->toInt();
            $balance = $principal;
            $rows = [];
            for ($number = 1; $number <= $months; $number++) {
                $interest = $rate->multipliedBy($balance)->toScale(0, RoundingMode::HalfUp)->toInt();
                $capital = $number === $months ? $balance : $regular - $interest;
                $total = Money::sum([$capital, $interest]);
                if ($capital < 0 || $capital > $balance || $total <= 0 || ($number < $months && $capital === $balance)) {
                    throw ValidationException::withMessages(['loan' => 'El monto y plazo no permiten cuotas válidas en centavos.']);
                }
                $balance -= $capital;
                $rows[] = ['number' => $number, 'due_on' => $date->addMonthsNoOverflow($number)->toDateString(), 'capital_cents' => $capital, 'interest_cents' => $interest, 'balance_cents' => $balance];
            }

            return $rows;
        } catch (MathException) {
            throw ValidationException::withMessages(['loan' => 'La tabla de amortización excede los importes representables.']);
        }
    }
}
