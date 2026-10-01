<?php

use App\Http\Controllers\FundConfigurationController;
use App\Http\Controllers\FundContributionController;
use App\Http\Controllers\FundController;
use App\Http\Controllers\FundInvitationController;
use App\Http\Controllers\FundLoanController;
use App\Http\Controllers\FundNotificationController;
use App\Http\Controllers\FundParticipantController;
use App\Http\Controllers\FundTransactionController;
use App\Http\Controllers\FundTreasuryController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (Request $request) => to_route($request->user() === null ? 'login' : 'dashboard'))->name('home');

Route::middleware(['guest', 'signed'])->group(function () {
    Route::get('invitations/{invitation}', [FundInvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{invitation}', [FundInvitationController::class, 'accept'])->name('invitations.accept');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [FundController::class, 'index'])->name('dashboard');
    Route::prefix('fund')->name('fund.')->group(function () {
        Route::get('/', fn () => to_route('dashboard'))->name('index');
        Route::get('contributions', [FundContributionController::class, 'index'])->name('contributions.index');
        Route::get('treasury', [FundTreasuryController::class, 'index'])->name('treasury.index');
        Route::get('notifications', [FundNotificationController::class, 'index'])->name('notifications.index');
        Route::patch('notifications/read', [FundNotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::patch('notifications/{notification}/read', [FundNotificationController::class, 'read'])->name('notifications.read');
        Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
        Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
        Route::get('treasury/participants', [FundParticipantController::class, 'index'])->name('treasury.participants.index');
        Route::post('treasury/participants/invitations', [FundParticipantController::class, 'invite'])->name('treasury.participants.invite');
        Route::post('treasury/participants/invitations/{invitation}/cancel', [FundParticipantController::class, 'cancel'])->name('treasury.participants.cancel');
        Route::post('treasury/participants', [FundParticipantController::class, 'store'])->name('treasury.participants.store');
        Route::get('treasury/loans/create', [FundLoanController::class, 'create'])->name('treasury.loans.create');
        Route::get('transactions/create', [FundTransactionController::class, 'create'])->name('transactions.create');
        Route::get('transactions', [FundTransactionController::class, 'index'])->name('transactions.index');
        Route::post('transactions', [FundTransactionController::class, 'store'])->name('transactions.store');
        Route::get('transactions/{transaction}/edit', [FundTransactionController::class, 'edit'])->name('transactions.edit');
        Route::get('transactions/{transaction}', [FundTransactionController::class, 'show'])->name('transactions.show');
        Route::post('transactions/{transaction}/approve', [FundTransactionController::class, 'approve'])->name('transactions.approve');
        Route::post('transactions/{transaction}/reject', [FundTransactionController::class, 'reject'])->name('transactions.reject');
        Route::post('transactions/{transaction}/correct', [FundTransactionController::class, 'correct'])->name('transactions.correct');
        Route::get('evidences/{evidence}', [FundTransactionController::class, 'evidence'])->name('evidences.show');
        Route::get('contribution-periods', [FundConfigurationController::class, 'periods'])->name('contribution-periods.index');
        Route::post('contribution-periods', [FundConfigurationController::class, 'storePeriod'])->name('contribution-periods.store');
        Route::post('contribution-periods/range', [FundConfigurationController::class, 'storeRange'])->name('contribution-periods.range');
        Route::get('banks', [FundConfigurationController::class, 'banks'])->name('banks.index');
        Route::post('banks', [FundConfigurationController::class, 'storeBank'])->name('banks.store');
        Route::patch('banks/{bank}', [FundConfigurationController::class, 'updateBank'])->name('banks.update');
        Route::get('loans', [FundLoanController::class, 'index'])->name('loans.index');
        Route::post('loans', [FundLoanController::class, 'store'])->name('loans.store');
        Route::get('loans/{loan}/correction', [FundLoanController::class, 'correction'])->name('loans.correction');
        Route::get('loans/{loan}', [FundLoanController::class, 'show'])->name('loans.show');
        Route::post('loans/{loan}/disburse', [FundLoanController::class, 'disburse'])->name('loans.disburse');
        Route::post('loans/{loan}/cancel', [FundLoanController::class, 'cancel'])->name('loans.cancel');
        Route::post('loans/{loan}/correct', [FundLoanController::class, 'correct'])->name('loans.correct');
    });
});

require __DIR__.'/settings.php';
