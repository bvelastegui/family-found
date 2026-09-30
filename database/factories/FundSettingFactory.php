<?php

namespace Database\Factories;

use App\Models\FundSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FundSetting>
 */
class FundSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 1,
            'administrator_id' => User::factory(),
            'treasurer_id' => null,
            'currency' => 'USD',
            'timezone' => 'America/Guayaquil',
        ];
    }
}
