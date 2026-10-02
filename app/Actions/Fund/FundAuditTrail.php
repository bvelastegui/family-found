<?php

namespace App\Actions\Fund;

use App\Models\FundTransaction;
use App\Models\Loan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** @phpstan-type EventData array{id: int, title: string, subject_type: string, subject_id: int, created_at: string, actor_name: string, data: array<string, mixed>} */
class FundAuditTrail
{
    public const array EVENT_LABELS = [
        'fund.installed' => 'Fondo instalado', 'treasurer.designated' => 'Tesorero designado', 'auditor.designated' => 'Auditor designado',
        'bank.saved' => 'Banco actualizado', 'contribution.configured' => 'Cuota configurada',
        'transaction.registered' => 'Comprobante registrado', 'transaction.approved' => 'Transferencia aprobada',
        'transaction.rejected' => 'Transferencia rechazada', 'transaction.corrected' => 'Transferencia corregida',
        'loan.reserved' => 'Préstamo reservado', 'loan.disbursed' => 'Préstamo desembolsado', 'loan.cancelled' => 'Reserva cancelada', 'loan.corrected' => 'Desembolso corregido',
        'participant.invited' => 'Invitación enviada', 'participant.created' => 'Participante creado',
        'participant.invitation-accepted' => 'Invitación aceptada', 'participant.invitation-cancelled' => 'Invitación cancelada', 'demo.seeded' => 'Demostración preparada',
    ];

    /** @param array{from?: string|null, to?: string|null, type?: string|null, search?: string|null} $filters
     * @return LengthAwarePaginator<int, array{id: int, title: string, subject_type: string, subject_id: int, created_at: string, actor_name: string, data: array<string, mixed>, participant_name: string|null, reference: string|null, amount_cents: int|null}>
     */
    public function events(array $filters): LengthAwarePaginator
    {
        $query = $this->eventQuery()
            ->leftJoin('fund_transactions as transaction', fn (JoinClause $join) => $join->on('transaction.id', '=', 'event.subject_id')->where('event.subject_type', 'transaction'))
            ->leftJoin('loans as loan', fn (JoinClause $join) => $join->on('loan.id', '=', 'event.subject_id')->where('event.subject_type', 'loan'))
            ->leftJoin('users as subject_user', fn (JoinClause $join) => $join->on('subject_user.id', '=', 'event.subject_id')->where('event.subject_type', 'user'))
            ->leftJoin('fund_invitations as invitation', fn (JoinClause $join) => $join->on('invitation.id', '=', 'event.subject_id')->where('event.subject_type', 'invitation'))
            ->leftJoin('users as participant', fn (JoinClause $join) => $join->on('participant.id', '=', DB::raw('COALESCE(transaction.user_id, loan.user_id, subject_user.id)')))
            ->addSelect('transaction.reference')
            ->selectRaw('COALESCE(participant.name, invitation.email) as participant_name')
            ->selectRaw('COALESCE(transaction.amount_cents, loan.principal_cents) as amount_cents');
        if (! empty($filters['from'])) {
            $query->where('event.created_at', '>=', CarbonImmutable::parse($filters['from'], 'America/Guayaquil')->startOfDay()->utc());
        }
        if (! empty($filters['to'])) {
            $query->where('event.created_at', '<', CarbonImmutable::parse($filters['to'], 'America/Guayaquil')->startOfDay()->addDay()->utc());
        }
        if (! empty($filters['type'])) {
            $query->where('event.subject_type', $filters['type']);
        }
        if (! empty($filters['search'])) {
            $search = '%'.addcslashes(trim($filters['search']), '%_\\').'%';
            $query->where(fn (Builder $query) => $query->where('actor.name', 'like', $search)->orWhere('participant.name', 'like', $search)->orWhere('invitation.email', 'like', $search)->orWhere('transaction.reference', 'like', $search));
        }

        /** @var LengthAwarePaginator<int, object{id: int|string, event: string, subject_type: string, subject_id: int|string, created_at: string, data: string, actor_name: string, participant_name: string|null, reference: string|null, amount_cents: int|string|null}> $rawEvents */
        $rawEvents = $query->orderByDesc('event.created_at')->orderByDesc('event.id')->paginate(20)->withQueryString();

        return $rawEvents->through(fn (object $event): array => [...$this->eventData($event),
            'participant_name' => $event->participant_name, 'reference' => $event->reference,
            'amount_cents' => $event->amount_cents === null ? null : (int) $event->amount_cents,
        ]);
    }

    /** @return array{record: array{type: string, id: int, title: string, amount_cents: int|null, status: string|null, participant_name: string|null, reference: string|null, bank_name: string|null, evidence_id: int|null, previous_id: int|null, replacement_id: int|null}, events: list<array{id: int, title: string, subject_type: string, subject_id: int, created_at: string, actor_name: string, data: array<string, mixed>}>, entries: list<array{id: int, created_at: string, actor_name: string, reversal_of_id: int|null, transaction_id: int|null, loan_id: int|null, lines: list<array{id: int, account: string, side: string, amount_cents: int, participant_name: string|null, loan_id: int|null}>}>, allocations: list<array{id: int, amount_cents: int, capital_cents: int, interest_cents: int, month: string|null, loan_id: int|null, number: int|null}>, source: array{type: string, id: int}|null} */
    public function record(string $type, int $id): array
    {
        $titles = ['fund' => 'Administración del fondo', 'period' => 'Cuota #'.$id, 'bank' => 'Banco #'.$id, 'user' => 'Participante #'.$id, 'invitation' => 'Invitación #'.$id];
        $record = ['type' => $type, 'id' => $id, 'title' => $titles[$type] ?? 'Registro #'.$id, 'amount_cents' => null, 'status' => null,
            'participant_name' => null, 'reference' => null, 'bank_name' => null, 'evidence_id' => null, 'previous_id' => null, 'replacement_id' => null];
        $entryIds = [];
        /** @var list<array{id: int, amount_cents: int, capital_cents: int, interest_cents: int, month: string|null, loan_id: int|null, number: int|null}> $allocations */
        $allocations = [];
        /** @var array{type: string, id: int}|null $source */
        $source = null;
        if ($type === 'transaction') {
            $transaction = FundTransaction::query()->with('user:id,name')->findOrFail($id);
            $record = [...$record, 'title' => 'Transferencia #'.$id, 'amount_cents' => $transaction->amount_cents,
                'status' => $transaction->getRawOriginal('status'), 'participant_name' => $transaction->user->name, 'reference' => $transaction->reference,
                'bank_name' => $transaction->bank_name, 'evidence_id' => $transaction->evidence_id,
                'previous_id' => $transaction->corrected_from_id === null ? null : (int) $transaction->corrected_from_id,
                'replacement_id' => $transaction->superseded_by_id === null ? null : (int) $transaction->superseded_by_id];
            $entryIds = [];
            foreach (DB::table('journal_entries')->where('fund_transaction_id', $id)->pluck('id') as $entryId) {
                $entryIds[] = (int) $entryId;
            }
            foreach (DB::table('transaction_allocations as allocation')
                ->leftJoin('contribution_periods as period', 'period.id', '=', 'allocation.contribution_period_id')
                ->leftJoin('loan_installments as installment', 'installment.id', '=', 'allocation.loan_installment_id')
                ->where('allocation.fund_transaction_id', $id)->orderBy('allocation.id')
                ->get(['allocation.id', 'allocation.amount_cents', 'allocation.capital_cents', 'allocation.interest_cents', 'period.month', 'installment.loan_id', 'installment.number']) as $allocation) {
                $allocations[] = ['id' => (int) $allocation->id, 'amount_cents' => (int) $allocation->amount_cents,
                    'capital_cents' => (int) $allocation->capital_cents, 'interest_cents' => (int) $allocation->interest_cents,
                    'month' => $allocation->month === null ? null : (string) $allocation->month, 'loan_id' => $allocation->loan_id === null ? null : (int) $allocation->loan_id,
                    'number' => $allocation->number === null ? null : (int) $allocation->number];
            }
        } elseif ($type === 'loan') {
            $loan = Loan::query()->with('user:id,name')->findOrFail($id);
            $record = [...$record, 'title' => 'Préstamo #'.$id, 'amount_cents' => $loan->principal_cents, 'status' => $loan->getRawOriginal('status'),
                'participant_name' => $loan->user->name, 'reference' => $loan->reference, 'bank_name' => $loan->bank_name,
                'evidence_id' => $loan->evidence_id === null ? null : (int) $loan->evidence_id,
                'replacement_id' => $loan->superseded_by_id === null ? null : (int) $loan->superseded_by_id,
                'previous_id' => ($previousId = Loan::query()->where('superseded_by_id', $id)->value('id')) === null ? null : (int) $previousId];
            $entryIds = [];
            foreach (DB::table('journal_lines')->where('loan_id', $id)->distinct()->pluck('journal_entry_id') as $entryId) {
                $entryIds[] = (int) $entryId;
            }
        } elseif ($type === 'entry') {
            $entry = DB::table('journal_entries')->where('id', $id)->first();
            abort_if($entry === null, 404);
            $record['title'] = 'Asiento #'.$id;
            $entryIds = [$id];
            if ($entry->fund_transaction_id !== null || $entry->loan_id !== null || $entry->reversal_of_id !== null) {
                $source = ['type' => $entry->fund_transaction_id !== null ? 'transaction' : ($entry->loan_id !== null ? 'loan' : 'entry'),
                    'id' => (int) ($entry->fund_transaction_id ?? $entry->loan_id ?? $entry->reversal_of_id)];
            }
        }
        /** @var Collection<int, object{id: int|string, event: string, subject_type: string, subject_id: int|string, created_at: string, data: string, actor_name: string}> $storedEvents */
        $storedEvents = $this->eventQuery()->where('event.subject_type', $type)->where('event.subject_id', $id)
            ->orderBy('event.created_at')->orderBy('event.id')->get();
        $events = [];
        foreach ($storedEvents as $storedEvent) {
            $events[] = $this->eventData($storedEvent);
        }

        return ['record' => $record, 'events' => $events, 'entries' => $this->entries($entryIds), 'allocations' => $allocations, 'source' => $source];
    }

    private function eventQuery(): Builder
    {
        return DB::table('operation_events as event')->join('users as actor', 'actor.id', '=', 'event.actor_id')
            ->select('event.id', 'event.event', 'event.subject_type', 'event.subject_id', 'event.created_at', 'event.data', 'actor.name as actor_name');
    }

    /** @param object{id: int|string, event: string, subject_type: string, subject_id: int|string, created_at: string, data: string, actor_name: string} $event
     * @return array{id: int, title: string, subject_type: string, subject_id: int, created_at: string, actor_name: string, data: array<string, mixed>}
     */
    private function eventData(object $event): array
    {
        $data = array_intersect_key(json_decode($event->data, true, flags: JSON_THROW_ON_ERROR), array_flip([
            'reason', 'amount_cents', 'principal_cents', 'previous_cents', 'previous', 'month', 'date', 'reference', 'email', 'name', 'active',
            'monthly_rate', 'term_months', 'user_id', 'previous_id', 'treasurer_id', 'auditor_id', 'replacement_id', 'reversal_entry_id', 'journal_entry_id', 'evidence_id', 'corrected_from_id',
        ]));
        if (is_array($data['previous'] ?? null)) {
            $data['previous'] = array_intersect_key($data['previous'], array_flip(['name', 'active']));
        }

        return ['id' => (int) $event->id, 'title' => self::EVENT_LABELS[$event->event] ?? 'Operación registrada',
            'subject_type' => $event->subject_type, 'subject_id' => (int) $event->subject_id,
            'created_at' => $event->created_at, 'actor_name' => $event->actor_name, 'data' => $data];
    }

    /**
     * @param  list<int>  $entryIds
     * @return list<array{id: int, created_at: string, actor_name: string, reversal_of_id: int|null, transaction_id: int|null, loan_id: int|null, lines: list<array{id: int, account: string, side: string, amount_cents: int, participant_name: string|null, loan_id: int|null}>}>
     */
    private function entries(array $entryIds): array
    {
        /** @var Collection<int, object{id: int|string, created_at: string, actor_id: int|string, fund_transaction_id: int|string|null, loan_id: int|string|null, reversal_of_id: int|string|null, actor_name: string}> $entries */
        /** @var Collection<int, object{id: int|string, created_at: string, actor_id: int|string, fund_transaction_id: int|string|null, loan_id: int|string|null, reversal_of_id: int|string|null, actor_name: string}> $entries */
        $entries = DB::table('journal_entries as entry')->join('users as actor', 'actor.id', '=', 'entry.actor_id')
            ->where(fn (Builder $query) => $query->whereIn('entry.id', $entryIds)->orWhereIn('entry.reversal_of_id', $entryIds))
            ->orderBy('entry.created_at')->orderBy('entry.id')->get(['entry.id', 'entry.created_at', 'entry.actor_id', 'entry.fund_transaction_id', 'entry.loan_id', 'entry.reversal_of_id', 'actor.name as actor_name']);
        /** @var Collection<int|string, Collection<int, object{id: int|string, journal_entry_id: int|string, account: string, side: string, amount_cents: int|string, user_id: int|string|null, loan_id: int|string|null, participant_name: string|null}>> $lines */
        /** @var Collection<int|string, Collection<int, object{id: int|string, journal_entry_id: int|string, account: string, side: string, amount_cents: int|string, user_id: int|string|null, loan_id: int|string|null, participant_name: string|null}>> $lines */
        $lines = DB::table('journal_lines as line')->leftJoin('users as participant', 'participant.id', '=', 'line.user_id')
            ->whereIn('line.journal_entry_id', $entries->pluck('id'))->orderBy('line.id')
            ->get(['line.id', 'line.journal_entry_id', 'line.account', 'line.side', 'line.amount_cents', 'line.user_id', 'line.loan_id', 'participant.name as participant_name'])->groupBy('journal_entry_id');

        $result = [];
        foreach ($entries as $entry) {
            $entryLines = [];
            foreach ($lines[$entry->id] ?? [] as $line) {
                $entryLines[] = [
                    'id' => (int) $line->id,
                    'account' => (string) $line->account,
                    'side' => (string) $line->side,
                    'amount_cents' => (int) $line->amount_cents,
                    'participant_name' => $line->participant_name === null ? null : (string) $line->participant_name,
                    'loan_id' => $line->loan_id === null ? null : (int) $line->loan_id,
                ];
            }
            $result[] = [
                'id' => (int) $entry->id, 'created_at' => $entry->created_at, 'actor_name' => $entry->actor_name,
                'reversal_of_id' => $entry->reversal_of_id === null ? null : (int) $entry->reversal_of_id,
                'transaction_id' => $entry->fund_transaction_id === null ? null : (int) $entry->fund_transaction_id,
                'loan_id' => $entry->loan_id === null ? null : (int) $entry->loan_id,
                'lines' => $entryLines,
            ];
        }

        return $result;
    }
}
