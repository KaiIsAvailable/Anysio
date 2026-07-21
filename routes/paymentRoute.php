<?php
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::post('invoices/{invoice}/payments',[PaymentController::class, 'store'])->name('payments.store');
Route::get('payments/{payment}/receipt', [PaymentController::class, 'viewReceipt'])->name('payments.view-receipt');
Route::patch('/payments/{payment}/approve', [PaymentController::class, 'approve'])->name('payments.approve');
Route::patch('/payments/{payment}/reject', [PaymentController::class, 'reject'])->name('payments.reject');