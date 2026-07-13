<?php
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::post('invoices/{invoice}/payments',[PaymentController::class, 'store'])->name('payments.store');
Route::get('payments/{payment}/receipt', [PaymentController::class, 'viewReceipt'])
    ->name('payments.view-receipt');