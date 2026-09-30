<?php

namespace App\Actions\Fund;

use App\Models\FundSetting;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RunFundOperation
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  Closure(FundSetting): int  $operation
     */
    public function handle(User $actor, string $action, string $key, array $payload, Closure $operation, string $role = 'member'): int
    {
        if (! Str::isUuid($key)) {
            throw ValidationException::withMessages(['idempotency_key' => 'La operación necesita un identificador válido.']);
        }
        $fingerprint = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $action, $key, $fingerprint, $operation, $role): int {
            $fund = FundSetting::query()->lockForUpdate()->find(1) ?? abort(503, 'El fondo todavía no ha sido instalado.');
            abort_if($role === 'treasurer' && ! $fund->isTreasurer($actor), 403);
            abort_if($role === 'administrator' && ! $fund->isAdministrator($actor), 403);
            $previous = DB::table('operation_requests')->where(['actor_id' => $actor->id, 'action' => $action, 'key' => $key])->first();
            if ($previous !== null) {
                abort_unless(hash_equals((string) $previous->fingerprint, $fingerprint), 409, 'El identificador ya se utilizó con otros datos.');

                return (int) $previous->result_id;
            }
            $result = $operation($fund);
            DB::table('operation_requests')->insert(['actor_id' => $actor->id, 'action' => $action, 'key' => $key, 'fingerprint' => $fingerprint, 'result_id' => $result, 'created_at' => now()]);

            return $result;
        }, 5);
    }
}
