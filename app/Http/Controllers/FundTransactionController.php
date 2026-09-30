<?php

namespace App\Http\Controllers;

use App\Actions\Fund\FundContributions;
use App\Actions\Fund\FundTransactions;
use App\Enums\LoanStatus;
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
        $events = DB::table('operation_events')->where('subject_type', 'transaction')->where('subject_id', $transaction->id)->orderBy('id')->get();

        $treasurer = FundSetting::current()->isTreasurer($request->user());

        return Inertia::render('fund/Transaction', ['transaction' => $transaction, 'allocations' => $allocations, 'events' => $events, 'isTreasurer' => $treasurer, 'banks' => $treasurer ? Bank::query()->where('active', true)->orderBy('name')->get(['id', 'name']) : []]);
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
