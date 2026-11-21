<?php
use App\Http\Controllers\SettingsController; // Or wherever your controller is
use Illuminate\Support\Facades\Route;

Route::get('/settings', [SettingsController::class, 'index'])->middleware(\App\Http\Middleware\ResolveSettingsAccount::class)->name('settings.index');
Route::put('/settings/accounts/{user}/permissions', [\App\Http\Controllers\AccountPermissionController::class, 'update'])
    ->middleware('can:super-admin')->name('settings.account-permissions.update');
Route::match(['put', 'patch'], '/settings', [SettingsController::class, 'update'])->middleware(\App\Http\Middleware\ResolveSettingsAccount::class)->name('settings.update');
Route::post('/settings/{invoice}/calculate-penalty', [SettingsController::class, 'calculatePenaltyPreview'])->name('setting.calculate-penalty');
Route::get('/settings/user-role-permissions', [SettingsController::class, 'userRolePermissions'])->middleware(\App\Http\Middleware\ResolveSettingsAccount::class)->name('settings.user-role-permissions');
Route::put('/settings/user-role-permissions', [SettingsController::class, 'updateRolePermissions'])->middleware(\App\Http\Middleware\ResolveSettingsAccount::class)->name('settings.user-role-permissions.update');
