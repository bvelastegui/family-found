<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\FundTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $user_id
 * @property int $bank_id
 * @property string $bank_name
 * @property string $reference
 * @property string $normalized_reference
 * @property string|null $active_reference
 * @property CarbonImmutable $transaction_date
 * @property int $amount_cents
 * @property int $evidence_id
 * @property TransactionStatus $status
 * @property int|null $pending_contributor_id
 * @property int|null $corrected_from_id
 * @property int|null $superseded_by_id
 * @property-read User $user
 * @property-read Evidence $evidence
 */
#[Fillable(['user_id', 'bank_id', 'bank_name', 'reference', 'normalized_reference', 'active_reference', 'transaction_date', 'amount_cents', 'evidence_id', 'status', 'pending_contributor_id', 'corrected_from_id', 'superseded_by_id'])]
class FundTransaction extends Model
{
    /** @use HasFactory<FundTransactionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (self $transaction): void {
            if (array_diff(array_keys($transaction->getDirty()), ['status', 'active_reference', 'pending_contributor_id', 'superseded_by_id', 'updated_at']) !== []) {
                throw new LogicException('Los datos de una transacción registrada son inmutables.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('Una transacción registrada no se elimina.');
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Evidence, $this> */
    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => TransactionStatus::class, 'transaction_date' => 'immutable_date', 'amount_cents' => 'integer'];
    }
}
