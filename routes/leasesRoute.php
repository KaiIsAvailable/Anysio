<?php
use App\Http\Controllers\LeaseController;
use Illuminate\Support\Facades\Route;

// 1. 先定义所有【具体的、不带变量】的路由
Route::get('leases/tenant-search', [LeaseController::class, 'tenantSearch'])->name('leases.tenant-search');

// 2. 定义【特定动作】的路由（必须在 resource 之前，防止 {lease} 吞掉 action）
Route::get('leases/{lease}/view-cert', [LeaseController::class, 'viewCert'])
    ->name('leases.view-cert')
    ->middleware('auth');

Route::post('leases/{lease}/upload-stamping', [LeaseController::class, 'uploadStamping'])
    ->name('leases.upload-stamping');

// 3. 定义【关联查询】路由
Route::get('get-units/{propertyId}', [LeaseController::class, 'getUnits'])->name('get-units');
Route::get('get-rooms/{unitId}', [LeaseController::class, 'getRooms'])->name('get-rooms');

Route::get('/leases/{lease}/payments-table', [LeaseController::class, 'getPaymentsTableOnly']);

Route::get('/leases/{lease}/refresh-payments', [LeaseController::class, 'refreshPaymentsTable'])
    ->name('leases.refresh-payments');

// 4. 最后才放 Resource 路由
Route::resource('leases', LeaseController::class);
