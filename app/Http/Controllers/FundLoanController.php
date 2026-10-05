<?php

namespace App\Http\Controllers;

use App\Actions\Fund\FundBalances;
use App\Actions\Fund\FundContributions;
use App\Actions\Fund\FundLoans;
use App\Enums\JournalAccount;
use App\Enums\LoanStatus;
use App\Http\Requests\Fund\FundCommandRequest;
use App\Http\Requests\Fund\FundDisbursementRequest;
use App\Http\Requests\Fund\FundLoanRequest;
use App\Models\Bank;
use App\Models\FundSetting;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FundLoanController extends Controller
{
    public function index(Request $request, FundBalances $balances, FundContributions $contributions): Response
    {
        $treasurer = FundSetting::current()->isTreasurer($request->user());
        $validated = $request->validate(['status' => ['sometimes', 'nullable', 'in:reserved,disbursed,cancelled,superseded']]);
        $status = $request->query->has('status') ? ($validated['status'] ?? '') : LoanStatus::Disbursed->value;
        $paidInstallmentIds = $contributions->paidInstallmentIds();
        $today = now('America/Guayaquil')->toDateString();

        return Inertia::render('fund/Loans', [
            'loans' => Loan::query()->when(! $treasurer, fn ($query) => $query->where('user_id', $request->user()->id))->when($status !== '', fn ($query) => $query->where('status', $status))->with('user:id,name')->withExists(['installments as has_overdue_payment' => fn ($query) => $query->where('due_on', '<', $today)->whereNotIn('id', $paidInstallmentIds)])->latest('id')->paginate(15)->withQueryString()->through(function (Loan $loan) use ($balances): Loan {
                $loan->setAttribute('outstanding_cents', $balances->account(JournalAccount::LoanPrincipal, loanId: $loan->id));
                $loan->setAttribute('has_overdue_payment', $loan->status === LoanStatus::Disbursed && (bool) $loan->getAttribute('has_overdue_payment'));

                return $loan;
            }),
            'isTreasurer' => $treasurer,
            'filters' => ['status' => $status],
            'reservedLoans' => $treasurer ? Loan::query()->where('status', LoanStatus::Reserved)->with('user:id,name')
                ->orderBy('created_at')->orderBy('id')->paginate(10, ['*'], 'reserved_page')->withQueryString() : null,
        ]);
    }

    public function create(Request $request, FundBalances $balances): Response
    {
        abort_unless(FundSetting::current()->isTreasurer($request->user()), 403);

        return Inertia::render('fund/LoanCreate', [
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'availableCents' => $balances->summary()['available'],
        ]);
    }

    public function show(Request $request, Loan $loan, FundContributions $contributions, FundBalances $balances, FundLoans $loans): Response
    {
        $treasurer = FundSetting::current()->isTreasurer($request->user());
        abort_unless($treasurer || $loan->user_id === $request->user()->id, 403);

        return Inertia::render('fund/Loan', ['loan' => $loan->load('user:id,name', 'installments'), 'canCorrect' => $treasurer && $loan->status === LoanStatus::Disbursed && ! $loans->hasDependentPayments($loan), 'outstandingCents' => $balances->account(JournalAccount::LoanPrincipal, loanId: $loan->id), 'paidInstallmentIds' => $contributions->paidInstallmentIds(), 'isTreasurer' => $treasurer, 'banks' => $treasurer ? Bank::query()->where('active', true)->orderBy('name')->get(['id', 'name']) : []]);
    }

    public function correction(Request $request, Loan $loan, FundLoans $loans): Response
    {
        abort_unless(FundSetting::current()->isTreasurer($request->user()), 403);
        abort_unless($loan->status === LoanStatus::Disbursed, 409, 'Solo se corrige un desembolso vigente.');

        return Inertia::render('fund/LoanCorrection', [
            'loan' => $loan->load('user:id,name'),
            'banks' => Bank::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'hasDependentPayments' => $loans->hasDependentPayments($loan),
        ]);
    }

    public function store(FundLoanRequest $request, FundLoans $loans): RedirectResponse
    {
        $id = $loans->reserve($request->user(), (string) $request->validated('idempotency_key'), ['user_id' => (int) $request->validated('user_id'), 'amount' => (string) $request->validated('amount'), 'monthly_rate' => (string) $request->validated('monthly_rate'), 'term_months' => (int) $request->validated('term_months')]);

        return to_route('fund.loans.show', $id);
    }

    public function disburse(FundDisbursementRequest $request, Loan $loan, FundLoans $loans): RedirectResponse
    {
        $id = $loans->disburse($request->user(), (string) $request->validated('idempotency_key'), $loan->id, $this->disbursementData($request), $request->file('evidence'));

        return to_route('fund.loans.show', $id);
    }

    public function cancel(FundCommandRequest $request, Loan $loan, FundLoans $loans): RedirectResponse
    {
        $loans->cancel($request->user(), (string) $request->validated('idempotency_key'), $loan->id, (string) $request->validated('reason'));

        return to_route('fund.loans.show', $loan);
    }

    public function correct(FundDisbursementRequest $request, Loan $loan, FundLoans $loans): RedirectResponse
    {
        $id = $loans->correct($request->user(), (string) $request->validated('idempotency_key'), $loan->id, [...$this->disbursementData($request), 'monthly_rate' => (string) $request->validated('monthly_rate'), 'term_months' => (int) $request->validated('term_months'), 'reason' => (string) $request->validated('reason')], $request->file('evidence'));

        return to_route('fund.loans.show', $id);
    }

    /** @return array{bank_id: int, reference: string, transaction_date: string, amount: string} */
    private function disbursementData(FundDisbursementRequest $request): array
    {
        return ['bank_id' => (int) $request->validated('bank_id'), 'reference' => (string) $request->validated('reference'), 'transaction_date' => (string) $request->validated('transaction_date'), 'amount' => (string) $request->validated('amount')];
    }
}
