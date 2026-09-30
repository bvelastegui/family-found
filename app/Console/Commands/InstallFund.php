<?php

namespace App\Console\Commands;

use App\Actions\Fund\InstallFund as InstallFundAction;
use App\Models\FundSetting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('fund:install {--name=} {--email=}')]
#[Description('Crea el fondo y su cuenta administrativa inicial sin saldos ni credenciales predeterminadas')]
class InstallFund extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(InstallFundAction $install): int
    {
        if (FundSetting::query()->exists()) {
            $this->info('El fondo ya está instalado. No se modificó la cuenta administrativa.');

            return self::SUCCESS;
        }
        $name = $this->option('name') ?: config('fund.initial_administrator.name');
        $email = $this->option('email') ?: config('fund.initial_administrator.email');
        $password = config('fund.initial_administrator.password');
        if ($this->input->isInteractive()) {
            $name = $name ?: $this->ask('Nombre del administrador');
            $email = $email ?: $this->ask('Correo del administrador');
            $password = $password ?: $this->secret('Contraseña inicial (al menos 12 caracteres)');
        }
        try {
            $install->handle((string) $name, (string) $email, (string) $password);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }
        $this->info('Fondo instalado. El administrador puede iniciar sesión y designar al tesorero.');

        return self::SUCCESS;
    }
}
