<?php

use App\Actions\Fund\FundTransactions;
use App\Models\FundTransaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('transaction details include the evidence metadata and allow an inline pdf preview', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $periodId = (int) DB::table('contribution_periods')->value('id');
    $transactionId = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId,
        'reference' => 'PREVIEW-1',
        'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00',
        'period_ids' => [$periodId],
        'installment_ids' => [],
    ], UploadedFile::fake()->create('respaldo.pdf', 1, 'application/pdf'));
    $evidenceId = FundTransaction::findOrFail($transactionId)->evidence_id;

    $this->actingAs($member)
        ->get(route('fund.transactions.show', $transactionId))
        ->assertInertia(fn (Assert $page) => $page
            ->component('fund/Transaction')
            ->where('evidence.mime', 'application/pdf')
            ->where('evidence.original_name', 'respaldo.pdf'));

    $this->actingAs($member)
        ->get(route('fund.evidences.preview', $evidenceId))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeaderContains('Content-Disposition', 'inline')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('members cannot preview another member evidence and non-previewable evidence is refused', function () {
    Storage::fake('fund');
    [$treasurer, $member, $bankId] = prepareFund();
    $other = User::factory()->create();
    $periodId = (int) DB::table('contribution_periods')->value('id');
    $transactionId = app(FundTransactions::class)->register($member, (string) Str::uuid(), [
        'bank_id' => $bankId,
        'reference' => 'PRIVATE-PREVIEW',
        'transaction_date' => now('America/Guayaquil')->toDateString(),
        'amount' => '25.00',
        'period_ids' => [$periodId],
        'installment_ids' => [],
    ], UploadedFile::fake()->create('respaldo.pdf', 1, 'application/pdf'));
    $evidenceId = FundTransaction::findOrFail($transactionId)->evidence_id;
    $orphanEvidenceId = DB::table('evidences')->insertGetId([
        'user_id' => $member->id,
        'uploaded_by_id' => $member->id,
        'path' => 'evidences/no-preview.txt',
        'original_name' => 'no-preview.txt',
        'mime' => 'text/plain',
        'size' => 1,
        'sha256' => hash('sha256', 'x'),
        'created_at' => now(),
    ]);
    DB::table('fund_transactions')->where('id', $transactionId)->update(['evidence_id' => $orphanEvidenceId]);

    $this->actingAs($other)
        ->get(route('fund.evidences.preview', $evidenceId))
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('fund.evidences.preview', $orphanEvidenceId))
        ->assertUnsupportedMediaType();
});
