<?php
use App\Http\Controllers\LeaseController;
use Illuminate\Support\Facades\Route;

Route::get('leases/tenant-search', [LeaseController::class, 'tenantSearch'])->name('leases.tenant-search');
Route::get('leases/{lease}/view-cert', [LeaseController::class, 'viewCert'])->name('leases.view-cert')->middleware('auth');
Route::get('leases/{lease}/cert-file', [LeaseController::class, 'showCertFile'])->name('leases.cert-file');
Route::post('leases/{lease}/upload-stamping', [LeaseController::class, 'uploadStamping'])->name('leases.upload-stamping');
Route::get('get-units/{propertyId}', [LeaseController::class, 'getUnits'])->name('get-units');
Route::get('get-rooms/{unitId}', [LeaseController::class, 'getRooms'])->name('get-rooms');
Route::get('/leases/{lease}/payments-table', [LeaseController::class, 'getPaymentsTableOnly']);
Route::get('/leases/{lease}/refresh-payments', [LeaseController::class, 'refreshPaymentsTable'])->name('leases.refresh-payments');
Route::resource('leases', LeaseController::class);
