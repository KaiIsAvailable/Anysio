<?php

use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::resource('units', UnitController::class);
Route::patch('units/{id}/restore', [UnitController::class, 'restore'])->name('units.restore');