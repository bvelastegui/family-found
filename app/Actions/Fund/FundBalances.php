<?php

namespace App\Actions\Fund;

use App\Enums\JournalAccount;
use App\Enums\LoanStatus;
use App\Models\Loan;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;

class FundBalances
{
    public function account(JournalAccount $account, ?int $userId = null, ?int $loanId = null): int
    {
        $normalSide = in_array($account, [JournalAccount::Cash, JournalAccount::LoanPrincipal], true) ? 'debit' : 'credit';
        $value = DB::table('journal_lines')->where('account', $account->value)
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->when($loanId !== null, fn ($query) => $query->where('loan_id', $loanId))
            ->selectRaw('COALESCE(SUM(CASE WHEN side = ? THEN amount_cents ELSE -amount_cents END), 0) AS balance', [$normalSide])->value('balance');

        return BigInteger::of((string) $value)->toInt();
    }

    /** @return array{contributions: int, interest: int, principal: int, cash: int, reserved: int, available: int} */
    public function summary(): array
    {
        $cash = $this->account(JournalAccount::Cash);
        $reserved = BigInteger::of((string) Loan::query()->where('status', LoanStatus::Reserved)->sum('principal_cents'))->toInt();

        return ['contributions' => $this->account(JournalAccount::Contributions), 'interest' => $this->account(JournalAccount::Interest), 'principal' => $this->account(JournalAccount::LoanPrincipal), 'cash' => $cash, 'reserved' => $reserved, 'available' => $cash - $reserved];
    }
}
