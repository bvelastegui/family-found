<?php

namespace App\Http\Controllers;

use App\Actions\Fund\FundBalances;
use App\Enums\LoanStatus;
use App\Enums\TransactionStatus;
use App\Models\FundSetting;
use App\Models\FundTransaction;
use App\Models\Loan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FundTreasuryController extends Controller
{
    public function index(Request $request, FundBalances $balances): Response
    {
        abort_unless(FundSetting::current()->isTreasurer($request->user()), 403);

        return Inertia::render('fund/Treasury', [
            'pending' => FundTransaction::query()->where('status', TransactionStatus::Pending)->with('user:id,name')
                ->orderBy('created_at')->orderBy('id')->paginate(15),
            'reservedLoans' => Loan::query()->where('status', LoanStatus::Reserved)->with('user:id,name')
                ->orderBy('created_at')->orderBy('id')->limit(6)->get(),
            'balances' => $balances->summary(),
        ]);
    }
}
