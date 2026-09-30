<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ContributionPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property CarbonImmutable $month
 * @property int $amount_cents
 * @property CarbonImmutable|null $locked_at
 */
#[Fillable(['month', 'amount_cents', 'locked_at'])]
class ContributionPeriod extends Model
{
    /** @use HasFactory<ContributionPeriodFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (self $period): void {
            if ($period->getOriginal('locked_at') !== null && $period->isDirty(['month', 'amount_cents'])) {
                throw ValidationException::withMessages(['amount' => 'La cuota ya fue utilizada y no puede cambiarse.']);
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['month' => 'immutable_date', 'amount_cents' => 'integer', 'locked_at' => 'immutable_datetime'];
    }
}
