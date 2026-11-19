<?php
use App\Http\Controllers\FeeTypeController;
use Illuminate\Support\Facades\Route;

Route::patch('fee-types/{fee_type}/status', [FeeTypeController::class, 'changeStatus'])->name('fee-types.status');
Route::put('fee-types/batch', [FeeTypeController::class, 'updateBatch'])->name('fee-types.batch');
Route::resource('fee-types', FeeTypeController::class)->only(['index', 'store', 'update', 'destroy']);
