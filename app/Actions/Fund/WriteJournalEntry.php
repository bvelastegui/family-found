<?php

namespace App\Actions\Fund;

use App\Enums\JournalAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class WriteJournalEntry
{
    /**
     * @param  list<array{account: string, side: string, amount_cents: int, user_id?: int|null, loan_id?: int|null}>  $lines
     */
    public function handle(User $actor, array $lines, ?int $transactionId = null, ?int $loanId = null, ?int $reversalOf = null): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('La contabilización requiere una transacción atómica.');
        }
        $debits = [];
        $credits = [];
        foreach ($lines as $line) {
            if ($line['amount_cents'] <= 0 || JournalAccount::tryFrom($line['account']) === null || ! in_array($line['side'], ['debit', 'credit'], true)) {
                throw ValidationException::withMessages(['ledger' => 'El asiento contiene una línea inválida.']);
            }
            if ($line['side'] === 'debit') {
                $debits[] = $line['amount_cents'];
            } else {
                $credits[] = $line['amount_cents'];
            }
        }
        if (count($lines) < 2 || Money::sum($debits) !== Money::sum($credits) || count(array_filter([$transactionId, $loanId, $reversalOf], fn (?int $id): bool => $id !== null)) !== 1) {
            throw ValidationException::withMessages(['ledger' => 'El asiento debe estar balanceado y tener un único origen.']);
        }
        $id = (int) DB::table('journal_entries')->insertGetId(['actor_id' => $actor->id, 'fund_transaction_id' => $transactionId, 'loan_id' => $loanId, 'reversal_of_id' => $reversalOf, 'created_at' => now()]);
        foreach ($lines as $line) {
            DB::table('journal_lines')->insert(['journal_entry_id' => $id, 'account' => $line['account'], 'side' => $line['side'], 'amount_cents' => $line['amount_cents'], 'user_id' => $line['user_id'] ?? null, 'loan_id' => $line['loan_id'] ?? null]);
        }

        return $id;
    }

    public function reverse(User $actor, int $entryId): int
    {
        $lines = [];
        foreach (DB::table('journal_lines')->where('journal_entry_id', $entryId)->get() as $line) {
            $lines[] = ['account' => (string) $line->account, 'side' => $line->side === 'debit' ? 'credit' : 'debit', 'amount_cents' => (int) $line->amount_cents, 'user_id' => $line->user_id === null ? null : (int) $line->user_id, 'loan_id' => $line->loan_id === null ? null : (int) $line->loan_id];
        }

        return $this->handle($actor, $lines, reversalOf: $entryId);
    }
}
