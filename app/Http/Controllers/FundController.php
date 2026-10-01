<?php

namespace App\Http\Controllers;

use App\Actions\Fund\FundBalances;
use App\Actions\Fund\FundContributions;
use App\Enums\JournalAccount;
use App\Enums\LoanStatus;
use App\Enums\TransactionStatus;
use App\Models\ContributionPeriod;
use App\Models\FundSetting;
use App\Models\FundTransaction;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FundController extends Controller
{
    public function index(Request $request, FundBalances $balances, FundContributions $contributions): Response
    {
        $user = $request->user();
        $fund = FundSetting::query()->find(1);
        $treasurer = $fund?->isTreasurer($user) ?? false;
        $paidPeriods = $contributions->paidPeriodIds($user);
        $periods = ContributionPeriod::query()->where('month', '<=', now('America/Guayaquil')->endOfYear()->toDateString())
            ->orderBy('month')->get();
        $next = null;
        $previousMonth = null;
        foreach ($periods as $period) {
            if ($previousMonth !== null && $period->month->format('Y-m') !== $previousMonth->addMonth()->format('Y-m')) {
                break;
            }
            if (! in_array($period->id, $paidPeriods, true)) {
                $next = $period;
                break;
            }
            $previousMonth = $period->month;
        }
        $ownPending = FundTransaction::query()->where('user_id', $user->id)->where('status', TransactionStatus::Pending)
            ->latest('id')->limit(4)->get(['id', 'amount_cents', 'created_at', 'pending_contributor_id']);
        $paidInstallments = $contributions->paidInstallmentIds();
        $upcoming = DB::table('loan_installments as installment')
            ->join('loans as loan', 'loan.id', '=', 'installment.loan_id')
            ->where('loan.user_id', $user->id)->where('loan.status', LoanStatus::Disbursed->value)
            ->whereNotIn('installment.id', $paidInstallments)
            ->orderBy('installment.due_on')->orderBy('installment.id')->limit(3)
            ->select('loan.id as loan_id', 'installment.id', 'installment.due_on')
            ->selectRaw('installment.capital_cents + installment.interest_cents as amount_cents')->get();

        return Inertia::render('Dashboard', [
            'isTreasurer' => $treasurer,
            'isAdministrator' => $fund?->isAdministrator($user) ?? false,
            'balances' => $treasurer ? $balances->summary() : null,
            'contributedCents' => $balances->account(JournalAccount::Contributions, $user->id),
            'nextContribution' => $next ? ['month' => $next->month->format('Y-m'), 'amount_cents' => $next->amount_cents] : null,
            'pendingContributionId' => $ownPending->first(fn (FundTransaction $transaction): bool => $transaction->pending_contributor_id !== null)?->id,
            'pendingTransactions' => $ownPending,
            'upcomingInstallments' => $upcoming,
            'hasLoans' => Loan::query()->where('user_id', $user->id)->exists(),
            'pendingReviewCount' => $treasurer ? FundTransaction::query()->where('status', TransactionStatus::Pending)->count() : 0,
        ]);
    }
}
