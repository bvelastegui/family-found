<?php

use App\Models\Bank;
use Database\Seeders\BankSeeder;
use Database\Seeders\DatabaseSeeder;

test('default seeding includes active Ecuadorian banks with normalized names', function () {
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseHas('banks', [
        'name' => 'Banco del Pacífico',
        'normalized_name' => 'BANCO DEL PACÍFICO',
        'active' => true,
    ]);
    $this->assertDatabaseHas('banks', [
        'name' => 'Banco Pichincha',
        'normalized_name' => 'BANCO PICHINCHA',
        'active' => true,
    ]);
});

test('repeated seeding preserves existing banks without duplicates or reactivation', function () {
    $bank = Bank::factory()->create([
        'name' => 'BANCO PICHINCHA',
        'normalized_name' => 'BANCO PICHINCHA',
        'active' => false,
    ]);

    $this->seed(BankSeeder::class);
    $count = Bank::query()->count();
    $this->seed(BankSeeder::class);

    $this->assertDatabaseCount('banks', $count);
    $this->assertDatabaseHas('banks', [
        'id' => $bank->id,
        'name' => 'BANCO PICHINCHA',
        'normalized_name' => 'BANCO PICHINCHA',
        'active' => false,
    ]);
});
