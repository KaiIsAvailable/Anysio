<?php
use App\Http\Controllers\TenantsController;
use Illuminate\Support\Facades\Route;

Route::get('tenants/ic-photo/{filename}', [App\Http\Controllers\TenantsController::class, 'showIcPhoto'])->name('tenants.ic_photo');
Route::resource('tenants', TenantsController::class);
Route::get('/tenant/dashboard', [TenantsController::class, 'dashboard'])->name('tenants.dashboard');