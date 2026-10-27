<?php

use App\Http\Controllers\RefundController;
use Illuminate\Support\Facades\Route;

Route::post('/refunds/draft', [RefundController::class, 'storeDraft'])->name('refunds.store-draft');
Route::post('/refunds/pending', [RefundController::class, 'pending'])->name('refunds.submit-pending');
Route::post('/refunds/approve', [RefundController::class, 'approve'])->name('refunds.approve');
Route::post('/refunds/reject', [RefundController::class, 'reject'])->name('refunds.reject');
Route::post('/refunds/disburse', [RefundController::class, 'disburse'])->name('refunds.disburse');