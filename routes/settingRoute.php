<?php
use App\Http\Controllers\SettingsController; // Or wherever your controller is
use Illuminate\Support\Facades\Route;

Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::match(['put', 'patch'], '/settings', [SettingsController::class, 'update'])->name('settings.update');
Route::post('/settings/{invoice}/calculate-penalty', [SettingsController::class, 'calculatePenaltyPreview'])->name('setting.calculate-penalty');
Route::get('/settings/user-role-permissions', [SettingsController::class, 'userRolePermissions'])->name('settings.user-role-permissions');
Route::put('/settings/user-role-permissions', [SettingsController::class, 'updateRolePermissions'])->name('settings.user-role-permissions.update');