<?php

namespace App\Models;

use Database\Factories\FundSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $administrator_id
 * @property int|null $treasurer_id
 * @property string $currency
 * @property string $timezone
 */
#[Fillable(['id', 'administrator_id', 'treasurer_id', 'currency', 'timezone'])]
class FundSetting extends Model
{
    /** @use HasFactory<FundSettingFactory> */
    use HasFactory;

    public static function current(): self
    {
        return self::query()->find(1) ?? abort(503, 'El fondo todavía no ha sido instalado.');
    }

    public function isTreasurer(User $user): bool
    {
        return $this->treasurer_id === $user->id;
    }

    public function isAdministrator(User $user): bool
    {
        return $this->administrator_id === $user->id;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['administrator_id' => 'integer', 'treasurer_id' => 'integer'];
    }
}
