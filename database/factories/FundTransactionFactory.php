<?php

namespace Database\Factories;

use App\Enums\TransactionStatus;
use App\Models\Bank;
use App\Models\Evidence;
use App\Models\FundTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FundTransaction>
 */
class FundTransactionFactory extends Factory
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
            'bank_id' => Bank::factory(),
            'bank_name' => fn (array $attributes): string => (string) Bank::query()->whereKey($attributes['bank_id'])->value('name'),
            'reference' => $reference = fake()->unique()->numerify('###########'),
            'normalized_reference' => $reference,
            'active_reference' => $reference,
            'transaction_date' => now('America/Guayaquil')->toDateString(),
            'amount_cents' => 2500,
            'evidence_id' => fn (array $attributes): int => Evidence::factory()->create(['user_id' => $attributes['user_id'], 'uploaded_by_id' => $attributes['user_id']])->id,
            'status' => TransactionStatus::Pending,
        ];
    }
}
