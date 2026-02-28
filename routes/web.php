<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/email/verify/status', function (Request $request) {
    return response()->json([
        'verified' => $request->user()?->hasVerifiedEmail() ?? false,
    ]);
})->middleware(['auth'])->name('verification.status');

Route::middleware('auth')->group(function () {
    // 个人资料 (通用)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

<<<<<<< HEAD
    // 核心业务路由 (统一前缀 admin)
    Route::name('admin.')->prefix('admin')->group(function () {
        // 基础资源
        require __DIR__.'/ownerRoute.php';
        require __DIR__.'/tenantRoute.php';
        require __DIR__.'/roomRoute.php';
        require __DIR__.'/paymentRoute.php';
        require __DIR__.'/leasesRoute.php';
        require __DIR__.'/maintenanceRoute.php';
        
        
        // 管理员专用 (只有等级 >= 4 的人能进)
        Route::middleware('can:owner-admin')->group(function () {
=======
    Route::name('admin.')
        ->prefix('admin')
        ->group(function () {
            require __DIR__.'/ownerRoute.php';
            require __DIR__.'/tenantRoute.php';
            Route::get('tenants/ic-photo/{filename}', [App\Http\Controllers\TenantsController::class, 'showIcPhoto'])->name('tenants.ic_photo');
            require __DIR__.'/roomRoute.php';
            // AJAX route for real-time messaging - different path to avoid conflict with resource routes
            Route::get('ticket-messages/{ticket}', [App\Http\Controllers\TicketController::class, 'getNewMessages'])->name('customerService.newMessages');
            require __DIR__.'/customerServiceRoute.php';
>>>>>>> b4ca454e3116c944c9274924db72b3e76cafe96e
            require __DIR__.'/userManagementRoute.php';
            require __DIR__.'/customerServiceRoute.php';
            require __DIR__.'/staffRoute.php';
            
        });
    });
});

require __DIR__.'/auth.php';
