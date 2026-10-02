<?php

namespace App\Actions\Fund;

use App\Models\Evidence;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AuditControls
{
    /** @var array<string, array{title: string, description: string}> */
    public const array CHECKS = [
        'unbalanced_entries' => ['title' => 'Asientos balanceados', 'description' => 'Al menos dos líneas y el mismo total de débitos y créditos.'],
        'missing_transaction_entries' => ['title' => 'Aprobaciones contabilizadas', 'description' => 'Toda aprobación debe conservar su asiento, incluso después de una corrección.'],
        'unexpected_transaction_entries' => ['title' => 'Pendientes y rechazos sin efecto contable', 'description' => 'Solo las transacciones aprobadas deben tener asientos.'],
        'allocation_mismatch' => ['title' => 'Asignaciones completas', 'description' => 'Las asignaciones deben sumar el monto transferido.'],
        'transaction_posting_mismatch' => ['title' => 'Contabilización de transferencias', 'description' => 'Efectivo, aportes, capital e intereses deben coincidir con las asignaciones.'],
        'missing_loan_entries' => ['title' => 'Desembolsos contabilizados', 'description' => 'Cada desembolso debe conservar su asiento original.'],
        'loan_posting_mismatch' => ['title' => 'Contabilización de préstamos', 'description' => 'El principal prestado y la salida de efectivo deben coincidir con el desembolso.'],
        'invalid_entry_origins' => ['title' => 'Origen único de los asientos', 'description' => 'Una transferencia, un préstamo o un reverso por asiento.'],
        'invalid_reversals' => ['title' => 'Reversos trazables', 'description' => 'Cada reverso debe compensar el original por cuenta, participante y préstamo.'],
        'missing_evidence' => ['title' => 'Comprobantes disponibles', 'description' => 'Los archivos registrados deben existir en el almacenamiento del fondo.'],
    ];

    /** @return array{checks: list<array{key: string, title: string, description: string, count: int}>, findings: LengthAwarePaginator<int, array{check_key: string, entity_type: string, entity_id: int, reference: string, created_at: string, expected_cents: int|null, actual_cents: int|null}>, checkedAt: string} */
    public function run(string $check = ''): array
    {
        return DB::transaction(fn (): array => $this->report($check));
    }

    /** @return array{checks: list<array{key: string, title: string, description: string, count: int}>, findings: LengthAwarePaginator<int, array{check_key: string, entity_type: string, entity_id: int, reference: string, created_at: string, expected_cents: int|null, actual_cents: int|null}>, checkedAt: string} */
    private function report(string $check): array
    {
        $queries = $this->queries();
        $checks = [];
        $combined = null;
        foreach ($queries as $key => $query) {
            $checks[] = ['key' => $key, ...self::CHECKS[$key], 'count' => (clone $query)->count()];
            $combined = $combined === null ? clone $query : $combined->unionAll(clone $query);
        }
        if ($combined === null) {
            throw new \LogicException('Los controles de auditoría deben producir una consulta.');
        }
        /** @var LengthAwarePaginator<int, object{check_key: string, entity_type: string, entity_id: int|string, reference: string, created_at: string, expected_cents: int|string|null, actual_cents: int|string|null}> $rawFindings */
        $rawFindings = DB::query()->fromSub($combined, 'findings')
            ->when($check !== '', fn (Builder $query): Builder => $query->where('check_key', $check))
            ->orderByDesc('created_at')->orderBy('check_key')->orderBy('entity_id')->paginate(15)->withQueryString();
        $findings = $rawFindings->through(fn (object $finding): array => [
            'check_key' => $finding->check_key, 'entity_type' => $finding->entity_type, 'entity_id' => (int) $finding->entity_id,
            'reference' => $finding->reference, 'created_at' => $finding->created_at,
            'expected_cents' => $finding->expected_cents === null ? null : (int) $finding->expected_cents,
            'actual_cents' => $finding->actual_cents === null ? null : (int) $finding->actual_cents,
        ]);

        return ['checks' => $checks, 'findings' => $findings, 'checkedAt' => now()->toIso8601String()];
    }

    /** @return array<string, Builder> */
    private function queries(): array
    {
        $totals = DB::table('journal_lines')->select('journal_entry_id')->groupBy('journal_entry_id')
            ->selectRaw('COUNT(*) as line_count')
            ->selectRaw("SUM(CASE WHEN side = 'debit' THEN amount_cents ELSE 0 END) as debits")
            ->selectRaw("SUM(CASE WHEN side = 'credit' THEN amount_cents ELSE 0 END) as credits");
        foreach (['cash' => ['debit', 'credit'], 'contributions' => ['credit'], 'loan_principal' => ['debit', 'credit'], 'interest' => ['credit']] as $account => $sides) {
            foreach ($sides as $side) {
                $totals->selectRaw("SUM(CASE WHEN account = ? AND side = ? THEN amount_cents ELSE 0 END) as {$account}_{$side}", [$account, $side]);
            }
        }
        $allocations = DB::table('transaction_allocations')->select('fund_transaction_id')->groupBy('fund_transaction_id')
            ->selectRaw('SUM(amount_cents) as allocated')
            ->selectRaw('SUM(CASE WHEN contribution_period_id IS NOT NULL THEN amount_cents ELSE 0 END) as contributions')
            ->selectRaw('SUM(capital_cents) as principal, SUM(interest_cents) as interest');
        $entries = DB::table('journal_entries as entry')->leftJoinSub(clone $totals, 'totals', 'totals.journal_entry_id', '=', 'entry.id');
        $transactions = DB::table('fund_transactions as transaction')
            ->leftJoin('journal_entries as entry', 'entry.fund_transaction_id', '=', 'transaction.id')
            ->leftJoinSub(clone $totals, 'totals', 'totals.journal_entry_id', '=', 'entry.id')
            ->leftJoinSub($allocations, 'allocations', 'allocations.fund_transaction_id', '=', 'transaction.id');
        $loans = DB::table('loans as loan')->leftJoin('journal_entries as entry', 'entry.loan_id', '=', 'loan.id')
            ->leftJoinSub(clone $totals, 'totals', 'totals.journal_entry_id', '=', 'entry.id');
        $queries = [];
        $queries['unbalanced_entries'] = $this->finding((clone $entries)->where(fn (Builder $query) => $query
            ->whereRaw('COALESCE(totals.line_count, 0) < 2')->orWhereColumn('totals.debits', '<>', 'totals.credits')),
            'unbalanced_entries', 'entry', 'entry.id', "CONCAT('Asiento #', entry.id)", 'entry.created_at', 'COALESCE(totals.debits, 0)', 'COALESCE(totals.credits, 0)');
        $queries['missing_transaction_entries'] = $this->finding((clone $transactions)->where('transaction.status', 'approved')->whereNull('entry.id'),
            'missing_transaction_entries', 'transaction', 'transaction.id', 'transaction.reference', 'transaction.created_at');
        $queries['unexpected_transaction_entries'] = $this->finding((clone $transactions)->where('transaction.status', '<>', 'approved')->whereNotNull('entry.id'),
            'unexpected_transaction_entries', 'transaction', 'transaction.id', 'transaction.reference', 'transaction.created_at');
        $queries['allocation_mismatch'] = $this->finding((clone $transactions)->whereRaw('transaction.amount_cents <> COALESCE(allocations.allocated, 0)'),
            'allocation_mismatch', 'transaction', 'transaction.id', 'transaction.reference', 'transaction.created_at', 'transaction.amount_cents', 'COALESCE(allocations.allocated, 0)');
        $queries['transaction_posting_mismatch'] = $this->finding((clone $transactions)->where('transaction.status', 'approved')->whereNotNull('entry.id')
            ->where(fn (Builder $query) => $query->whereRaw('COALESCE(totals.cash_debit, 0) <> transaction.amount_cents')
                ->orWhereRaw('COALESCE(totals.debits, 0) <> transaction.amount_cents')->orWhereRaw('COALESCE(totals.cash_credit, 0) <> 0')
                ->orWhereRaw('COALESCE(totals.contributions_credit, 0) <> COALESCE(allocations.contributions, 0)')
                ->orWhereRaw('COALESCE(totals.loan_principal_credit, 0) <> COALESCE(allocations.principal, 0)')
                ->orWhereRaw('COALESCE(totals.interest_credit, 0) <> COALESCE(allocations.interest, 0)')),
            'transaction_posting_mismatch', 'transaction', 'transaction.id', 'transaction.reference', 'transaction.created_at');
        $queries['missing_loan_entries'] = $this->finding((clone $loans)->whereIn('loan.status', ['disbursed', 'superseded'])->whereNull('entry.id'),
            'missing_loan_entries', 'loan', 'loan.id', "CONCAT('Préstamo #', loan.id)", 'loan.created_at');
        $queries['loan_posting_mismatch'] = $this->finding((clone $loans)->whereNotNull('entry.id')->where(fn (Builder $query) => $query
            ->whereNotIn('loan.status', ['disbursed', 'superseded'])->orWhereRaw('COALESCE(totals.loan_principal_debit, 0) <> loan.principal_cents')
            ->orWhereRaw('COALESCE(totals.cash_credit, 0) <> loan.principal_cents')->orWhereRaw('COALESCE(totals.debits, 0) <> loan.principal_cents')),
            'loan_posting_mismatch', 'loan', 'loan.id', "CONCAT('Préstamo #', loan.id)", 'loan.created_at');
        $queries['invalid_entry_origins'] = $this->finding((clone $entries)->whereRaw('(CASE WHEN entry.fund_transaction_id IS NULL THEN 0 ELSE 1 END + CASE WHEN entry.loan_id IS NULL THEN 0 ELSE 1 END + CASE WHEN entry.reversal_of_id IS NULL THEN 0 ELSE 1 END) <> 1'),
            'invalid_entry_origins', 'entry', 'entry.id', "CONCAT('Asiento #', entry.id)", 'entry.created_at');
        $queries['invalid_reversals'] = $this->finding(DB::table('journal_entries as entry')->leftJoin('journal_entries as original', 'original.id', '=', 'entry.reversal_of_id')
            ->whereNotNull('entry.reversal_of_id')->where(fn (Builder $query) => $query->whereNull('original.id')->orWhereNotNull('original.reversal_of_id')
            ->orWhere(fn (Builder $query) => $query->whereNull('original.fund_transaction_id')->whereNull('original.loan_id'))
            ->orWhereIn('entry.id', $this->uncompensatedReversals())),
            'invalid_reversals', 'entry', 'entry.id', "CONCAT('Reverso #', entry.id)", 'entry.created_at');
        $missingIds = [];
        foreach (Evidence::query()->select('id', 'path')->lazyById(100) as $evidence) {
            if (! Storage::disk('fund')->exists($evidence->path)) {
                $missingIds[] = $evidence->id;
            }
        }
        $evidenceFindings = $this->finding(DB::table('evidences as evidence')->whereIn('evidence.id', $missingIds)
            ->leftJoin('fund_transactions as transaction', 'transaction.evidence_id', '=', 'evidence.id')->leftJoin('loans as loan', 'loan.evidence_id', '=', 'evidence.id'),
            'missing_evidence', "CASE WHEN transaction.id IS NOT NULL THEN 'transaction' WHEN loan.id IS NOT NULL THEN 'loan' ELSE 'evidence' END as entity_type",
            'COALESCE(transaction.id, loan.id, evidence.id)', 'evidence.original_name', 'evidence.created_at', entityExpression: true);
        $queries['missing_evidence'] = $evidenceFindings;

        return $queries;
    }

    private function uncompensatedReversals(): Builder
    {
        $original = DB::table('journal_entries as reversal')->join('journal_lines as line', 'line.journal_entry_id', '=', 'reversal.reversal_of_id')
            ->select('reversal.id as entry_id', 'line.account', 'line.user_id', 'line.loan_id')
            ->selectRaw("CASE WHEN line.side = 'debit' THEN line.amount_cents ELSE -line.amount_cents END as amount");
        $reverse = DB::table('journal_entries as reversal')->join('journal_lines as line', 'line.journal_entry_id', '=', 'reversal.id')
            ->whereNotNull('reversal.reversal_of_id')->select('reversal.id as entry_id', 'line.account', 'line.user_id', 'line.loan_id')
            ->selectRaw("CASE WHEN line.side = 'debit' THEN line.amount_cents ELSE -line.amount_cents END as amount");
        $deltas = DB::query()->fromSub($original->unionAll($reverse), 'deltas')->select('entry_id')
            ->groupBy('entry_id', 'account', 'user_id', 'loan_id')->havingRaw('SUM(amount) <> 0');

        return DB::query()->fromSub($deltas, 'uncompensated')->select('entry_id');
    }

    /** @param literal-string $entity
     * @param  literal-string  $id
     * @param  literal-string  $reference
     * @param  literal-string  $date
     * @param  literal-string  $expected
     * @param  literal-string  $actual
     */
    private function finding(Builder $query, string $check, string $entity, string $id, string $reference, string $date, string $expected = 'NULL', string $actual = 'NULL', bool $entityExpression = false): Builder
    {
        $query->select([]);
        $query->selectRaw('? as check_key', [$check]);
        if ($entityExpression) {
            $query->selectRaw($entity);
        } else {
            $query->selectRaw('? as entity_type', [$entity]);
        }

        return $query->selectRaw("{$id} as entity_id, {$reference} as reference, {$date} as created_at, {$expected} as expected_cents, {$actual} as actual_cents");
    }
}
