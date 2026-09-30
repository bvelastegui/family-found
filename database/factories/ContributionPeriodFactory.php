<?php

namespace Database\Factories;

use App\Models\ContributionPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContributionPeriod>
 */
class ContributionPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'month' => now('America/Guayaquil')->startOfMonth()->toDateString(),
            'amount_cents' => 2500,
            'locked_at' => null,
        ];
    }
}
