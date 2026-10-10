<?php
use App\Http\Controllers\TenantsController;
use Illuminate\Support\Facades\Route;


Route::get('/tenant/dashboard', [TenantsController::class, 'dashboard'])
    ->name('dashboard');

Route::get('/tenant/leases', [TenantsController::class, 'leases'])
    ->name('leases.index');

Route::get('/tenant/leases/{lease}', [TenantsController::class, 'leaseShow'])
    ->name('leases.show');

Route::get('/tenant/invoices', [TenantsController::class, 'invoices'])
    ->name('invoices.index');
Route::middleware('can:is-tenant')->prefix('tenant/customer-service')->name('customerService.')->group(function () {
    Route::get('/', [\App\Http\Controllers\TenantTicketController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\TenantTicketController::class, 'store'])->name('store');
    Route::get('/{ticket}', [\App\Http\Controllers\TenantTicketController::class, 'show'])->name('show');
    Route::post('/{ticket}/messages', [\App\Http\Controllers\TenantTicketController::class, 'reply'])->name('reply');
    Route::get('/{ticket}/messages/{message}/attachment', [\App\Http\Controllers\TicketMsgController::class, 'attachment'])->name('attachment');
});
