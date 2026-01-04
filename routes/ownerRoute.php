<?php
use App\Http\Controllers\OwnersController;
use App\Models\Owners;
use Illuminate\Support\Facades\Route;

Route::resource('owners', OwnersController::class);