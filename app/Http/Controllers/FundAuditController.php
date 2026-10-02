<?php

namespace App\Http\Controllers;

use App\Actions\Fund\AuditControls;
use App\Actions\Fund\FundAuditTrail;
use App\Models\FundSetting;
use App\Models\FundTransaction;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FundAuditController extends Controller
{
    public function index(Request $request, FundAuditTrail $trail): Response
    {
        $this->authorizeAuditor($request);
        $filters = $request->validate([
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')],
            'type' => ['sometimes', 'nullable', 'in:transaction,loan,fund,period,bank,user,invitation'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        return Inertia::render('fund/Audit', ['events' => $trail->events($filters),
            'filters' => array_merge(['from' => '', 'to' => '', 'type' => '', 'search' => ''], array_map(fn ($value) => $value ?? '', $filters))]);
    }

    public function controls(Request $request, AuditControls $controls): Response
    {
        $this->authorizeAuditor($request);
        $filters = $request->validate(['check' => ['sometimes', 'nullable', Rule::in(array_keys(AuditControls::CHECKS))]]);
        $check = $filters['check'] ?? '';

        return Inertia::render('fund/AuditControls', [...$controls->run($check), 'selectedCheck' => $check]);
    }

    public function event(Request $request, int $event, FundAuditTrail $trail): Response
    {
        $this->authorizeAuditor($request);
        $record = DB::table('operation_events')->where('id', $event)->first();
        abort_if($record === null, 404);

        return Inertia::render('fund/AuditRecord', [...$trail->record($record->subject_type, (int) $record->subject_id), 'selectedEvent' => $event, 'returnTo' => $this->returnTo($request)]);
    }

    public function transaction(Request $request, FundTransaction $transaction, FundAuditTrail $trail): Response
    {
        $this->authorizeAuditor($request);

        return Inertia::render('fund/AuditRecord', [...$trail->record('transaction', $transaction->id), 'selectedEvent' => null, 'returnTo' => $this->returnTo($request)]);
    }

    public function loan(Request $request, Loan $loan, FundAuditTrail $trail): Response
    {
        $this->authorizeAuditor($request);

        return Inertia::render('fund/AuditRecord', [...$trail->record('loan', $loan->id), 'selectedEvent' => null, 'returnTo' => $this->returnTo($request)]);
    }

    public function entry(Request $request, int $entry, FundAuditTrail $trail): Response
    {
        $this->authorizeAuditor($request);

        return Inertia::render('fund/AuditRecord', [...$trail->record('entry', $entry), 'selectedEvent' => null, 'returnTo' => $this->returnTo($request)]);
    }

    private function authorizeAuditor(Request $request): void
    {
        abort_unless(FundSetting::current()->isAuditor($request->user()), 403);
    }

    private function returnTo(Request $request): string
    {
        $value = $request->query('return_to');
        $parts = is_string($value) ? parse_url($value) : false;
        if ($parts !== false && ! isset($parts['host']) && ! isset($parts['scheme']) && in_array($parts['path'] ?? '', [
            route('fund.audit.index', absolute: false), route('fund.audit.controls', absolute: false),
        ], true)) {
            return $value;
        }

        return route('fund.audit.index', absolute: false);
    }
}
