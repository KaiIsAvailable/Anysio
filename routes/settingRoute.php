<?php
use App\Http\Controllers\SettingsController; // Or wherever your controller is
use Illuminate\Support\Facades\Route;

Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::match(['put', 'patch'], '/settings', [SettingsController::class, 'update'])->name('settings.update');