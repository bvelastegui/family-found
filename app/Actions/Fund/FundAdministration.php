<?php

namespace App\Actions\Fund;

use App\Models\Bank;
use App\Models\FundSetting;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FundAdministration
{
    public function __construct(private RunFundOperation $operations, private RecordFundEvent $events) {}

    public function treasurer(User $actor, string $key, int $userId): int
    {
        return $this->operations->handle($actor, 'treasurer.designate', $key, ['user_id' => $userId], function (FundSetting $fund) use ($actor, $userId): int {
            User::query()->findOrFail($userId);
            if ($fund->auditor_id === $userId) {
                throw ValidationException::withMessages(['user_id' => 'El auditor no puede ser tesorero al mismo tiempo.']);
            }
            $previous = $fund->treasurer_id;
            $fund->update(['treasurer_id' => $userId]);
            $this->events->handle($actor, 'treasurer.designated', 'fund', 1, ['previous_id' => $previous, 'treasurer_id' => $userId]);

            return $userId;
        }, 'administrator');
    }

    public function bank(User $actor, string $key, string $name, bool $active, ?int $bankId = null): int
    {
        $name = Str::squish($name);
        $normalized = Str::upper($name);

        return $this->operations->handle($actor, 'bank.save', $key, ['name' => $name, 'active' => $active, 'bank_id' => $bankId], function () use ($actor, $name, $normalized, $active, $bankId): int {
            if (Bank::query()->where('normalized_name', $normalized)->when($bankId !== null, fn ($query) => $query->where('id', '!=', $bankId))->exists()) {
                throw ValidationException::withMessages(['name' => 'Este banco ya existe en el catálogo.']);
            }
            $bank = $bankId === null ? new Bank : Bank::query()->findOrFail($bankId);
            $previous = $bank->exists ? $bank->only(['name', 'active']) : null;
            $bank->fill(['name' => $name, 'normalized_name' => $normalized, 'active' => $active])->save();
            $this->events->handle($actor, 'bank.saved', 'bank', $bank->id, ['previous' => $previous, 'name' => $name, 'active' => $active]);

            return $bank->id;
        }, 'treasurer');
    }

    public function auditor(User $actor, string $key, int $userId): int
    {
        return $this->operations->handle($actor, 'auditor.designate', $key, ['user_id' => $userId], function (FundSetting $fund) use ($actor, $userId): int {
            User::query()->findOrFail($userId);
            if ($fund->treasurer_id === $userId) {
                throw ValidationException::withMessages(['user_id' => 'El tesorero no puede auditar sus propias operaciones.']);
            }
            $previous = $fund->auditor_id;
            $fund->update(['auditor_id' => $userId]);
            $this->events->handle($actor, 'auditor.designated', 'fund', 1, ['previous_id' => $previous, 'auditor_id' => $userId]);

            return $userId;
        }, 'administrator');
    }
}
