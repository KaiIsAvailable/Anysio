<?php
use App\Http\Controllers\FeeTypeController;
use Illuminate\Support\Facades\Route;

Route::resource('fee-types', FeeTypeController::class);