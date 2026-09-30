<?php

namespace Database\Seeders;

use App\Actions\Fund\InstallFund;
use Illuminate\Database\Seeder;

class FundSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(InstallFund $install): void
    {
        $install->handle((string) config('fund.initial_administrator.name'), (string) config('fund.initial_administrator.email'), (string) config('fund.initial_administrator.password'));
    }
}
