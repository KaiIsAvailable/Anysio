<?php

use App\Http\Controllers\PropertyController;
use Illuminate\Support\Facades\Route;

Route::get('properties/bulk-edit', [\App\Http\Controllers\PropertyBulkController::class, 'index'])->name('properties.bulk-edit');
Route::post('properties/bulk-edit', [\App\Http\Controllers\PropertyBulkController::class, 'store'])->name('properties.bulk-save');
Route::get('properties/bulk-operations/{requestKey}', [\App\Http\Controllers\PropertyBulkController::class, 'progress'])->whereUuid('requestKey')->name('properties.bulk-progress');
Route::get('properties/bulk-operations/{requestKey}/complete', [\App\Http\Controllers\PropertyBulkController::class, 'complete'])->whereUuid('requestKey')->name('properties.bulk-complete');

Route::resource('properties', PropertyController::class);
Route::patch('properties/{property}/restore', [PropertyController::class, 'restore'])->name('properties.restore');
