<?php

use App\Http\Controllers\PropertyController;
use Illuminate\Support\Facades\Route;

Route::resource('properties', PropertyController::class);
Route::patch('properties/{property}/restore', [PropertyController::class, 'restore'])->name('properties.restore');