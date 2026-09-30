<?php

namespace App\Http\Controllers;

use App\Actions\Fund\FundBalances;
use App\Enums\JournalAccount;
use App\Enums\LoanStatus;
use App\Models\FundSetting;
use App\Models\FundTransaction;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FundController extends Controller
{
    public function index(Request $request, FundBalances $balances): Response
    {
        $user = $request->user();
        $treasurer = FundSetting::current()->isTreasurer($user);
        $loans = Loan::query()->when(! $treasurer, fn ($query) => $query->where('user_id', $user->id))
            ->whereIn('status', [LoanStatus::Reserved, LoanStatus::Disbursed])->with('user:id,name')->latest('id')->paginate(15, ['*'], 'loans')
            ->through(function (Loan $loan) use ($balances): Loan {
                $loan->setAttribute('outstanding_cents', $balances->account(JournalAccount::LoanPrincipal, loanId: $loan->id));

                return $loan;
            });
        $transactions = FundTransaction::query()->when(! $treasurer, fn ($query) => $query->where('user_id', $user->id))
            ->with('user:id,name')->latest('id')->paginate(15, ['*'], 'transactions');

        return Inertia::render('fund/Index', [
            'isTreasurer' => $treasurer,
            'isAdministrator' => FundSetting::current()->isAdministrator($user),
            'balances' => $treasurer ? $balances->summary() : null,
            'transactions' => $transactions,
            'loans' => $loans,
            'contributedCents' => $balances->account(JournalAccount::Contributions, $user->id),
            'paidPeriods' => DB::table('transaction_allocations as allocation')
                ->join('fund_transactions as transaction', 'transaction.id', '=', 'allocation.fund_transaction_id')
                ->join('contribution_periods as period', 'period.id', '=', 'allocation.contribution_period_id')
                ->where('transaction.user_id', $user->id)->where('transaction.status', 'approved')->whereNull('transaction.superseded_by_id')
                ->orderByDesc('period.month')->select('period.month', 'allocation.amount_cents')->paginate(12, ['*'], 'periods'),
        ]);
    }
}
