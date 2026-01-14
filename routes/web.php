<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // 管理员/代理后台 (只有等级 >= 3 的人能进)
    Route::name('admin.')
        ->prefix('admin')
        ->middleware('can:owner-admin') 
        ->group(function () {
            require __DIR__.'/ownerRoute.php';
            require __DIR__.'/tenantRoute.php';
            require __DIR__.'/roomRoute.php';
            require __DIR__.'/customerServiceRoute.php';
            require __DIR__.'/userManagementRoute.php';
        });

    // 用户/业主前台 (只有等级 >= 2 的人能进)
    Route::name('user.')
        ->prefix('user')
        ->group(function () {
            // 这里只放业主和租客自己要看的东西
            require __DIR__.'/ownerRoute.php'; 
            require __DIR__.'/tenantRoute.php';
        });
});

require __DIR__.'/auth.php';
