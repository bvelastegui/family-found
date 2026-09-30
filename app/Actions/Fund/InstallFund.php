<?php

namespace App\Actions\Fund;

use App\Models\FundSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InstallFund
{
    public function __construct(private RecordFundEvent $events) {}

    public function handle(string $name, string $email, string $password): FundSetting
    {
        return DB::transaction(function () use ($name, $email, $password): FundSetting {
            $existing = FundSetting::query()->lockForUpdate()->find(1);
            if ($existing !== null) {
                return $existing;
            }
            Validator::make(compact('name', 'email', 'password'), [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:12'],
            ])->validate();
            $administrator = User::query()->create(compact('name', 'email', 'password'));
            $fund = FundSetting::query()->create(['id' => 1, 'administrator_id' => $administrator->id, 'currency' => 'USD', 'timezone' => 'America/Guayaquil']);
            $this->events->handle($administrator, 'fund.installed', 'fund', 1);

            return $fund;
        }, 5);
    }
}
