<?php
use App\Http\Controllers\OwnersController;
use App\Models\Owners;
use Illuminate\Support\Facades\Route;

Route::resource('owners', OwnersController::class);
Route::get('/owner/dashboard', [OwnersController::class, 'dashboard'])->name('owners.dashboard');
Route::patch('/oener/{id}/restore', [OwnersController::class, 'restore'])->name('owners.restore');