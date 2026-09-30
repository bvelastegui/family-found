<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LoanInstallmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $loan_id
 * @property int $number
 * @property CarbonImmutable $due_on
 * @property int $capital_cents
 * @property int $interest_cents
 * @property int $balance_cents
 * @property-read Loan $loan
 */
#[Fillable(['loan_id', 'number', 'due_on', 'capital_cents', 'interest_cents', 'balance_cents'])]
class LoanInstallment extends Model
{
    /** @use HasFactory<LoanInstallmentFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return BelongsTo<Loan, $this> */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['due_on' => 'immutable_date', 'number' => 'integer', 'capital_cents' => 'integer', 'interest_cents' => 'integer', 'balance_cents' => 'integer'];
    }
}
