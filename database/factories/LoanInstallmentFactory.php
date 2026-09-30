<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\LoanInstallment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanInstallment>
 */
class LoanInstallmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'loan_id' => Loan::factory(),
            'number' => 1,
            'due_on' => now('America/Guayaquil')->addMonthNoOverflow()->toDateString(),
            'capital_cents' => 3300,
            'interest_cents' => 100,
            'balance_cents' => 6700,
        ];
    }
}
