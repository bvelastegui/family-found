<?php

namespace Database\Factories;

use App\Models\Evidence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evidence>
 */
class EvidenceFactory extends Factory
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
            'uploaded_by_id' => fn (array $attributes): int => (int) $attributes['user_id'],
            'path' => 'evidences/'.fake()->uuid().'.pdf',
            'original_name' => 'comprobante.pdf',
            'mime' => 'application/pdf',
            'size' => 100,
            'sha256' => hash('sha256', 'fixture'),
            'created_at' => now(),
        ];
    }
}
