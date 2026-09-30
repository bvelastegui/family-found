<?php

namespace App\Actions\Fund;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\Exception\MathException;
use Illuminate\Validation\ValidationException;

class Money
{
    public static function cents(string $amount, string $field = 'amount'): int
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/D', $amount)) {
            throw ValidationException::withMessages([$field => 'Indica un monto en USD con hasta dos decimales.']);
        }

        try {
            $value = BigDecimal::of($amount)->multipliedBy(100)->toInt();
        } catch (MathException) {
            throw ValidationException::withMessages([$field => 'El monto está fuera del rango permitido.']);
        }

        if ($value <= 0) {
            throw ValidationException::withMessages([$field => 'El monto debe ser mayor que cero.']);
        }

        return $value;
    }

    /** @param list<int> $values */
    public static function sum(array $values): int
    {
        $sum = BigInteger::zero();
        foreach ($values as $value) {
            $sum = $sum->plus($value);
        }

        try {
            return $sum->toInt();
        } catch (MathException) {
            throw ValidationException::withMessages(['amount' => 'El total está fuera del rango permitido.']);
        }
    }

    public static function format(int $cents): string
    {
        return (string) BigDecimal::of($cents)->dividedBy(100, 2);
    }
}
