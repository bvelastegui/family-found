<?php

namespace App\Models;

use App\Enums\LoanStatus;
use Carbon\CarbonImmutable;
use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property int $id
 * @property int $user_id
 * @property int $principal_cents
 * @property string $monthly_rate
 * @property int $term_months
 * @property LoanStatus $status
 * @property int|null $bank_id
 * @property string|null $bank_name
 * @property string|null $reference
 * @property CarbonImmutable|null $disbursed_on
 * @property int|null $evidence_id
 * @property int|null $superseded_by_id
 * @property-read User $user
 * @property-read Collection<int, LoanInstallment> $installments
 */
#[Fillable(['user_id', 'principal_cents', 'monthly_rate', 'term_months', 'status', 'bank_id', 'bank_name', 'reference', 'disbursed_on', 'evidence_id', 'superseded_by_id'])]
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (self $loan): void {
            if ($loan->isDirty(['user_id', 'principal_cents', 'monthly_rate', 'term_months'])) {
                throw new LogicException('Las condiciones registradas del préstamo son inmutables.');
            }
            if ($loan->getOriginal('status') === LoanStatus::Disbursed && $loan->isDirty(['bank_id', 'bank_name', 'reference', 'disbursed_on', 'evidence_id'])) {
                throw new LogicException('El desembolso se corrige mediante compensación y reemplazo.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('Los préstamos se conservan en el historial.');
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<LoanInstallment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('number');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['principal_cents' => 'integer', 'term_months' => 'integer', 'monthly_rate' => 'decimal:6', 'status' => LoanStatus::class, 'disbursed_on' => 'immutable_date'];
    }
}
