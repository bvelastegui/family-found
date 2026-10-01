<?php

namespace Database\Seeders;

use App\Models\FundInvitation;
use App\Models\FundSetting;
use Illuminate\Database\Seeder;

class FundInvitationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $treasurerId = FundSetting::query()->find(1)?->treasurer_id;
        if ($treasurerId !== null) {
            FundInvitation::factory()->create(['invited_by_id' => $treasurerId]);
        }
    }
}
