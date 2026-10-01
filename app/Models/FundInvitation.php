<?php

namespace App\Models;

use Database\Factories\FundInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $email
 * @property string $token_hash
 * @property int $invited_by_id
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 */
#[Fillable(['email', 'token_hash', 'invited_by_id', 'expires_at', 'used_at'])]
#[Hidden(['token_hash'])]
class FundInvitation extends Model
{
    /** @use HasFactory<FundInvitationFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }
}
