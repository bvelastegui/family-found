<?php

namespace App\Console\Commands;

use App\Actions\Fund\FundAdministration;
use App\Models\FundSetting;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('fund:assign-treasurer {name : Nombre completo o parte del nombre del usuario}')]
#[Description('Busca un usuario por nombre y lo designa tesorero del fondo')]
class AssignFundTreasurer extends Command
{
    public function handle(FundAdministration $administration): int
    {
        $term = trim((string) $this->argument('name'));
        if ($term === '') {
            $this->error('Indica el nombre que deseas buscar.');

            return self::FAILURE;
        }

        $escaped = addcslashes($term, '%_\\');
        $matches = User::query()->where('name', 'like', "%{$escaped}%")->orderBy('name')->limit(21)->get();
        if ($matches->isEmpty()) {
            $this->error('No se encontró ningún usuario con ese nombre.');

            return self::FAILURE;
        }
        if ($matches->count() > 20) {
            $this->error('Hay más de 20 coincidencias. Escribe un nombre más específico.');

            return self::FAILURE;
        }
        if ($matches->count() > 1 && ! $this->input->isInteractive()) {
            $this->warn('La búsqueda es ambigua. Precisa el nombre:');
            foreach ($matches as $match) {
                $this->line("{$match->name} <{$match->email}>");
            }

            return self::FAILURE;
        }
        $chosen = $matches->first();
        if ($matches->count() > 1) {
            $options = $matches->map(fn (User $user): string => "{$user->name} <{$user->email}>")->all();
            $selected = $this->choice('Selecciona al nuevo tesorero', $options);
            $chosen = $matches->first(fn (User $user): bool => "{$user->name} <{$user->email}>" === $selected);
        }

        $fund = FundSetting::current();
        $administrator = User::query()->findOrFail($fund->administrator_id);
        $administration->treasurer($administrator, (string) Str::uuid(), $chosen->id);
        $this->info("Tesorero designado: {$chosen->name} <{$chosen->email}>.");

        return self::SUCCESS;
    }
}
