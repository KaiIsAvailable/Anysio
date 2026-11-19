<?php

use App\Http\Controllers\UtilityController;
use Illuminate\Support\Facades\Route;

Route::get('/utilities', [UtilityController::class, 'index'])->name('utilities.index');
Route::get('/utilities/tariffs', [\App\Http\Controllers\UtilityTariffController::class, 'index'])->name('utilities.tariffs');
Route::post('/utilities/tariffs', [\App\Http\Controllers\UtilityTariffController::class, 'store'])->name('utilities.tariffs.store');
Route::patch('/utilities/tariffs/{tariff}', [\App\Http\Controllers\UtilityTariffController::class, 'toggle'])->name('utilities.tariffs.toggle');
Route::post('/utilities/tariffs/{tariff}/assign', [\App\Http\Controllers\UtilityTariffController::class, 'assign'])->name('utilities.tariffs.assign');
Route::get('/utilities/charges/{utility}', [\App\Http\Controllers\UtilityBillingController::class, 'show'])->name('utilities.charges.show');
Route::post('/utilities/charges/{utility}/allocations', [\App\Http\Controllers\UtilityBillingController::class, 'allocate'])->name('utilities.allocations.store');
Route::delete('/utilities/charges/{utility}/allocations', [\App\Http\Controllers\UtilityBillingController::class, 'revoke'])->name('utilities.allocations.revoke');
Route::post('/utilities/charges/{utility}/adjustments', [\App\Http\Controllers\UtilityBillingController::class, 'adjust'])->name('utilities.adjustments.store');
Route::post('/utilities/charges/{utility}/corrections', [\App\Http\Controllers\UtilityBillingController::class, 'proposeCorrection'])->name('utilities.corrections.store');
Route::post('/utilities/charges/{utility}/corrections/{correction}/confirm', [\App\Http\Controllers\UtilityBillingController::class, 'confirmCorrection'])->name('utilities.corrections.confirm');
Route::post('/utilities/services/{utilityService}/events', [\App\Http\Controllers\UtilityBillingController::class, 'event'])->name('utilities.events.store');
Route::get('/utilities/history', [UtilityController::class, 'history'])->name('utilities.history');
Route::get('/utilities/readings', [UtilityController::class, 'readings'])->name('utilities.readings');
Route::post('/utilities/readings', [UtilityController::class, 'saveReadings'])->name('utilities.readings.save');
Route::post('/utilities/readings/invoices', [UtilityController::class, 'generateReadingInvoices'])->name('utilities.readings.invoices.generate');
Route::delete('/utilities/readings/meters', [UtilityController::class, 'deleteReadingMeters'])->name('utilities.readings.meters.delete');
Route::get('/utilities/services/{utilityService}/edit', [UtilityController::class, 'edit'])->name('utilities.edit');
Route::patch('/utilities/services/{utilityService}', [UtilityController::class, 'update'])->name('utilities.update');
Route::delete('/utilities/services/{utilityService}', [UtilityController::class, 'destroy'])->name('utilities.destroy');
