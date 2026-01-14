<?php
use App\Http\Controllers\TenantsController;
use Illuminate\Support\Facades\Route;

Route::resource('tenants', TenantsController::class);
Route::get('/tenant/dashboard', [TenantsController::class, 'dashboard'])
        ->name('tenants.dashboard');