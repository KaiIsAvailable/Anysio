<?php
use App\Http\Controllers\TenantsController;
use Illuminate\Support\Facades\Route;

Route::get('/tenants/ic_photo/{filename}', [TenantsController::class, 'showIcPhoto'])
    ->name('tenants.ic_photo');
Route::get('tenants/{tenant}/view-ic', [TenantsController::class, 'viewIc'])->name('tenants.view-ic');
Route::resource('tenants', TenantsController::class);
Route::get('/tenant/dashboard', [TenantsController::class, 'dashboard'])->name('tenants.dashboard');