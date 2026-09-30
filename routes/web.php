<?php

use App\Http\Controllers\FundAdministrationController;
use App\Http\Controllers\FundConfigurationController;
use App\Http\Controllers\FundController;
use App\Http\Controllers\FundLoanController;
use App\Http\Controllers\FundTransactionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::prefix('fund')->name('fund.')->group(function () {
        Route::get('/', [FundController::class, 'index'])->name('index');
        Route::get('transactions/create', [FundTransactionController::class, 'create'])->name('transactions.create');
        Route::post('transactions', [FundTransactionController::class, 'store'])->name('transactions.store');
        Route::get('transactions/{transaction}', [FundTransactionController::class, 'show'])->name('transactions.show');
        Route::post('transactions/{transaction}/approve', [FundTransactionController::class, 'approve'])->name('transactions.approve');
        Route::post('transactions/{transaction}/reject', [FundTransactionController::class, 'reject'])->name('transactions.reject');
        Route::post('transactions/{transaction}/correct', [FundTransactionController::class, 'correct'])->name('transactions.correct');
        Route::get('evidences/{evidence}', [FundTransactionController::class, 'evidence'])->name('evidences.show');
        Route::get('contribution-periods', [FundConfigurationController::class, 'periods'])->name('contribution-periods.index');
        Route::post('contribution-periods', [FundConfigurationController::class, 'storePeriod'])->name('contribution-periods.store');
        Route::get('banks', [FundConfigurationController::class, 'banks'])->name('banks.index');
        Route::post('banks', [FundConfigurationController::class, 'storeBank'])->name('banks.store');
        Route::patch('banks/{bank}', [FundConfigurationController::class, 'updateBank'])->name('banks.update');
        Route::get('loans', [FundLoanController::class, 'index'])->name('loans.index');
        Route::post('loans', [FundLoanController::class, 'store'])->name('loans.store');
        Route::get('loans/{loan}', [FundLoanController::class, 'show'])->name('loans.show');
        Route::post('loans/{loan}/disburse', [FundLoanController::class, 'disburse'])->name('loans.disburse');
        Route::post('loans/{loan}/cancel', [FundLoanController::class, 'cancel'])->name('loans.cancel');
        Route::post('loans/{loan}/correct', [FundLoanController::class, 'correct'])->name('loans.correct');
    });
    Route::get('administration/treasurer', [FundAdministrationController::class, 'edit'])->name('administration.treasurer.edit');
    Route::post('administration/treasurer', [FundAdministrationController::class, 'update'])->name('administration.treasurer.update');
});

require __DIR__.'/settings.php';
