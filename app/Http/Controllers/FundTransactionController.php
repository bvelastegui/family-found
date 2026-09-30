<?php

namespace App\Http\Controllers;

use App\Actions\Fund\FundContributions;
use App\Actions\Fund\FundTransactions;
use App\Enums\LoanStatus;
use App\Enums\TransactionStatus;
use App\Http\Requests\Fund\FundCommandRequest;
use App\Http\Requests\Fund\FundTransactionRequest;
use App\Models\Bank;
use App\Models\ContributionPeriod;
use App\Models\Evidence;
use App\Models\FundSetting;
use App\Models\FundTransaction;
use App\Models\Loan;
use App\Models\LoanInstallment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FundTransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $treasurer = FundSetting::current()->isTreasurer($request->user());
        $status = $request->query('status');
        $type = $request->query('type');
        abort_unless(in_array($status, [null, 'pending', 'approved', 'rejected'], true) && in_array($type, [null, 'contribution', 'loan'], true), 404);
        $transactions = FundTransaction::query()->when(! $treasurer, fn ($query) => $query->where('user_id', $request->user()->id))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($type, function ($query) use ($type): void {
                $query->whereExists(fn ($subquery) => $subquery->selectRaw('1')->from('transaction_allocations as allocation')
                    ->whereColumn('allocation.fund_transaction_id', 'fund_transactions.id')
                    ->whereNotNull($type === 'contribution' ? 'allocation.contribution_period_id' : 'allocation.loan_installment_id'));
            })
            ->with('user:id,name')->latest('id')->paginate(15)->withQueryString();
        $approvals = DB::table('operation_events as event')
            ->join('users as actor', 'actor.id', '=', 'event.actor_id')
            ->where('event.subject_type', 'transaction')->where('event.event', 'transaction.approved')
            ->whereIn('event.subject_id', $transactions->getCollection()->pluck('id'))
            ->get(['event.subject_id', 'actor.name as actor_name', 'event.created_at'])->keyBy('subject_id');
        $transactions->through(function (FundTransaction $transaction) use ($approvals): FundTransaction {
            $transaction->setAttribute('approved_by', $approvals[$transaction->id]->actor_name ?? null);
            $transaction->setAttribute('approved_at', $approvals[$transaction->id]->created_at ?? null);

            return $transaction;
        });

        return Inertia::render('fund/Transactions', [
            'transactions' => $transactions, 'filters' => ['status' => $status ?? '', 'type' => $type ?? ''],
            'isTreasurer' => $treasurer,
        ]);
    }

    public function create(Request $request, FundContributions $contributions): Response
    {
        $user = $request->user();
        $periods = ContributionPeriod::query()->where('month', '<=', now('America/Guayaquil')->endOfYear()->toDateString())->orderBy('month')->get();
        $paid = $contributions->paidPeriodIds($user);
        $loans = Loan::query()->where('user_id', $user->id)->where('status', LoanStatus::Disbursed)->with('installments')->get();
        $paidInstallments = $contributions->paidInstallmentIds();

        return Inertia::render('fund/TransactionForm', [
            'banks' => Bank::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'periods' => $periods->map(fn ($period) => ['id' => $period->id, 'month' => $period->month->format('Y-m'), 'amount_cents' => $period->amount_cents, 'paid' => in_array($period->id, $paid, true)]),
            'loans' => $loans->map(fn (Loan $loan): array => ['id' => $loan->id, 'installments' => $loan->installments->map(fn (LoanInstallment $installment): array => ['id' => $installment->id, 'number' => $installment->number, 'amount_cents' => $installment->capital_cents + $installment->interest_cents, 'paid' => in_array($installment->id, $paidInstallments, true)])->all()])->all(),
            'hasPendingContribution' => FundTransaction::query()->where('pending_contributor_id', $user->id)->exists(),
        ]);
    }

    public function store(FundTransactionRequest $request, FundTransactions $transactions): RedirectResponse
    {
        $id = $transactions->register($request->user(), (string) $request->validated('idempotency_key'), $request->transactionData(), $request->file('evidence'));

        return to_route('fund.transactions.show', $id);
    }

    public function show(Request $request, FundTransaction $transaction): Response
    {
        abort_unless($transaction->user_id === $request->user()->id || FundSetting::current()->isTreasurer($request->user()), 403);
        $allocations = DB::table('transaction_allocations as a')
            ->leftJoin('contribution_periods as p', 'p.id', '=', 'a.contribution_period_id')
            ->leftJoin('loan_installments as i', 'i.id', '=', 'a.loan_installment_id')
            ->where('a.fund_transaction_id', $transaction->id)
            ->select('a.id', 'a.amount_cents', 'a.contribution_period_id', 'a.loan_installment_id', 'a.capital_cents', 'a.interest_cents', 'p.month', 'i.number as installment_number', 'i.loan_id')->get();
        $events = DB::table('operation_events as event')
            ->join('users as actor', 'actor.id', '=', 'event.actor_id')
            ->where('event.subject_type', 'transaction')
            ->where('event.subject_id', $transaction->id)
            ->orderBy('event.id')
            ->get(['event.id', 'event.event', 'event.actor_id', 'actor.name as actor_name', 'event.created_at', 'event.data']);

        $treasurer = FundSetting::current()->isTreasurer($request->user());

        return Inertia::render('fund/Transaction', ['transaction' => $transaction, 'allocations' => $allocations, 'events' => $events, 'isTreasurer' => $treasurer, 'banks' => $treasurer ? Bank::query()->where('active', true)->orderBy('name')->get(['id', 'name']) : []]);
    }

    public function edit(Request $request, FundTransaction $transaction, FundContributions $contributions): Response
    {
        abort_unless(FundSetting::current()->isTreasurer($request->user()), 403);
        abort_unless($transaction->status === TransactionStatus::Approved && $transaction->superseded_by_id === null, 409);
        $assignments = DB::table('transaction_allocations')->where('fund_transaction_id', $transaction->id)->get();
        $periods = ContributionPeriod::query()->where('month', '<=', now('America/Guayaquil')->endOfYear()->toDateString())->orderBy('month')->get();
        $paidPeriods = $contributions->paidPeriodIds($transaction->user, $transaction->id);
        $loans = Loan::query()->where('user_id', $transaction->user_id)->where('status', LoanStatus::Disbursed)->with('installments')->get();
        $paidInstallments = $contributions->paidInstallmentIds($transaction->id);

        return Inertia::render('fund/TransactionCorrection', [
            'transaction' => $transaction,
            'banks' => Bank::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'periods' => $periods->map(fn (ContributionPeriod $period): array => [
                'id' => $period->id, 'month' => $period->month->format('Y-m'), 'amount_cents' => $period->amount_cents,
                'paid' => in_array($period->id, $paidPeriods, true),
            ]),
            'loans' => $loans->map(fn (Loan $loan): array => [
                'id' => $loan->id, 'installments' => $loan->installments->map(fn (LoanInstallment $installment): array => [
                    'id' => $installment->id, 'number' => $installment->number,
                    'amount_cents' => $installment->capital_cents + $installment->interest_cents,
                    'paid' => in_array($installment->id, $paidInstallments, true),
                ])->all(),
            ])->all(),
            'selectedPeriodIds' => $assignments->whereNotNull('contribution_period_id')->pluck('contribution_period_id')->map(fn ($id): int => (int) $id)->values()->all(),
            'selectedInstallmentIds' => $assignments->whereNotNull('loan_installment_id')->pluck('loan_installment_id')->map(fn ($id): int => (int) $id)->values()->all(),
        ]);
    }

    public function approve(FundCommandRequest $request, FundTransaction $transaction, FundTransactions $transactions): RedirectResponse
    {
        $transactions->approve($request->user(), (string) $request->validated('idempotency_key'), $transaction->id);

        return to_route('fund.transactions.show', $transaction);
    }

    public function reject(FundCommandRequest $request, FundTransaction $transaction, FundTransactions $transactions): RedirectResponse
    {
        $transactions->reject($request->user(), (string) $request->validated('idempotency_key'), $transaction->id, (string) $request->validated('reason'));

        return to_route('fund.transactions.show', $transaction);
    }

    public function correct(FundTransactionRequest $request, FundTransaction $transaction, FundTransactions $transactions): RedirectResponse
    {
        $id = $transactions->correct($request->user(), (string) $request->validated('idempotency_key'), $transaction->id, $request->transactionData(), $request->file('evidence'));

        return to_route('fund.transactions.show', $id);
    }

    public function evidence(Request $request, Evidence $evidence): BinaryFileResponse
    {
        abort_unless($evidence->user_id === $request->user()->id || FundSetting::current()->isTreasurer($request->user()), 403);
        abort_unless(FundTransaction::query()->where('evidence_id', $evidence->id)->exists() || Loan::query()->where('evidence_id', $evidence->id)->exists(), 404);

        return response()->download(Storage::disk('fund')->path($evidence->path), $evidence->original_name, ['Content-Type' => $evidence->mime]);
    }
}
