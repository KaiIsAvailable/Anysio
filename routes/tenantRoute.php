<?php
use App\Http\Controllers\TenantsController;
use Illuminate\Support\Facades\Route;

Route::get('tenants/bulk-preview', fn () => redirect()->route('admin.tenants.bulk-edit'))->name('tenants.bulk-preview');
Route::get('tenants/bulk-edit', [TenantsController::class, 'bulkEdit'])->name('tenants.bulk-edit');
Route::post('tenants/bulk-edit', [TenantsController::class, 'bulkSave'])->name('tenants.bulk-save');
Route::get('tenants/bulk-operations/{requestKey}', [TenantsController::class, 'bulkProgress'])->whereUuid('requestKey')->name('tenants.bulk-progress');
Route::get('tenants/bulk-operations/{requestKey}/complete', [TenantsController::class, 'bulkComplete'])->whereUuid('requestKey')->name('tenants.bulk-complete');
Route::get('tenants/{tenant}/bulk-file/{field}', [TenantsController::class, 'bulkFile'])
    ->whereIn('field', ['icPhoto', 'companyLogo', 'ssmCertificate'])->name('tenants.bulk-file');

Route::get('/tenants/ic_photo/{filename}', [TenantsController::class, 'showIcPhoto'])
    ->name('tenants.ic_photo');
Route::get('tenants/{tenant}/view-ic', [TenantsController::class, 'viewIc'])->name('tenants.view-ic');
Route::resource('tenants', TenantsController::class);
