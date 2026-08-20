<?php
use App\Http\Controllers\ExcelImportController;
use Illuminate\Support\Facades\Route;

Route::post('import/{type}', [ExcelImportController::class, 'store'])->name('import');
Route::post('imports/revert', [ExcelImportController::class, 'revertImport'])->name('import.revert');
Route::post('imports/confirm', [ExcelImportController::class, 'confirmImport'])->name('import.confirm');