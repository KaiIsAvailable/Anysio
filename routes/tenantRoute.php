<?php
use App\Http\Controllers\TenantsController;
use Illuminate\Support\Facades\Route;

Route::resource('tenants', TenantsController::class);
Route::get('/tenant/dashboard', [TenantsController::class, 'dashboard'])
        ->name('tenants.dashboard');

// Payment Routes associated with Tenants
use App\Http\Controllers\PaymentsController;
Route::post('tenants/{tenant}/payments', [PaymentsController::class, 'store'])->name('tenants.payments.store');
Route::patch('payments/{payment}/status', [PaymentsController::class, 'updateStatus'])->name('payments.updateStatus');
Route::delete('payments/{payment}/void', [PaymentsController::class, 'destroy'])->name('payments.void');
Route::get('payments/{payment}/receipt', [PaymentsController::class, 'showReceipt'])->name('payments.receipt');