<?php

namespace Database\Factories;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'principal_cents' => 10000,
            'monthly_rate' => '1.000000',
            'term_months' => 3,
            'status' => LoanStatus::Reserved,
        ];
    }
}
