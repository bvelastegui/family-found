<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $banks = [
            'Banco Pichincha',
            'Banco Guayaquil',
            'Banco del Pacífico',
            'Produbanco',
            'Banco Internacional',
            'Banco Bolivariano',
            'Banco del Austro',
            'Banco de Loja',
            'Banco de Machala',
            'Banco Solidario',
            'Banco ProCredit',
            'Banco General Rumiñahui',
            'BanEcuador',
        ];

        foreach ($banks as $name) {
            Bank::query()->firstOrCreate(
                ['normalized_name' => Str::upper($name)],
                ['name' => $name, 'active' => true],
            );
        }
    }
}
