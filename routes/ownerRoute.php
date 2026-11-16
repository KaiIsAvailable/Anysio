<?php
use App\Http\Controllers\OwnersController;
use App\Models\Owners;
use Illuminate\Support\Facades\Route;

Route::get('owners/bulk-edit', [\App\Http\Controllers\OwnerBulkController::class, 'index'])->name('owners.bulk-edit');
Route::post('owners/bulk-edit', [\App\Http\Controllers\OwnerBulkController::class, 'store'])->name('owners.bulk-save');
Route::get('owners/bulk-operations/{requestKey}', [\App\Http\Controllers\OwnerBulkController::class, 'progress'])->whereUuid('requestKey')->name('owners.bulk-progress');
Route::get('owners/bulk-operations/{requestKey}/complete', [\App\Http\Controllers\OwnerBulkController::class, 'complete'])->whereUuid('requestKey')->name('owners.bulk-complete');
Route::get('owners/{owner}/bulk-file/{field}', [\App\Http\Controllers\OwnerBulkController::class, 'file'])->whereIn('field', ['companyLogo', 'ssmCertificate'])->name('owners.bulk-file');

Route::resource('owners', OwnersController::class);
Route::get('/owner/dashboard', [OwnersController::class, 'dashboard'])->name('owners.dashboard');
Route::patch('/oener/{id}/restore', [OwnersController::class, 'restore'])->name('owners.restore');
Route::get('/owners/{owner}/company-certificate', [OwnersController::class, 'companyCertificate'])->name('owners.company-certificate');
