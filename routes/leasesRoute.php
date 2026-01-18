<?php
use App\Http\Controllers\LeaseController;
use Illuminate\Support\Facades\Route;

Route::get('leases/tenant-search', [LeaseController::class, 'tenantSearch'])->name('leases.tenant-search');
Route::resource('leases', LeaseController::class);
