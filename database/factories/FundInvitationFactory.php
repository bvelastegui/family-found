<?php

namespace Database\Factories;

use App\Models\FundInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FundInvitation>
 */
class FundInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'token_hash' => hash('sha256', Str::random(64)),
            'invited_by_id' => User::factory(),
            'expires_at' => now()->addDays(7),
            'used_at' => null,
        ];
    }
}
