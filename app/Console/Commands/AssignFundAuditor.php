<?php

namespace App\Console\Commands;

use App\Actions\Fund\FundAdministration;
use App\Models\FundSetting;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Signature('fund:assign-auditor {email : Correo exacto del usuario que será auditor}')]
#[Description('Designa un auditor independiente del tesorero del fondo')]
class AssignFundAuditor extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FundAdministration $administration): int
    {
        $user = User::query()->where('email', trim((string) $this->argument('email')))->first();
        if ($user === null) {
            $this->error('No se encontró un usuario con ese correo.');

            return self::FAILURE;
        }
        $administrator = User::query()->findOrFail(FundSetting::current()->administrator_id);
        try {
            $administration->auditor($administrator, (string) Str::uuid(), $user->id);
        } catch (ValidationException $exception) {
            $this->error($exception->errors()['user_id'][0]);

            return self::FAILURE;
        }
        $this->info("Auditor designado: {$user->name} <{$user->email}>.");

        return self::SUCCESS;
    }
}
