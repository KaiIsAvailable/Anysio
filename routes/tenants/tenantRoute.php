<?php
use App\Http\Controllers\TenantsController;
use Illuminate\Support\Facades\Route;


Route::get('/tenant/dashboard', [TenantsController::class, 'dashboard'])
    ->name('dashboard');

Route::get('/tenant/leases', [TenantsController::class, 'leases'])
    ->name('leases.index');

Route::get('/tenant/leases/{lease}', [TenantsController::class, 'leaseShow'])
    ->name('leases.show');

