<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantsController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// 1. 公开路由
Route::get('/', function () {
    return view('welcome');
});

// 2. 登录后的基础路由
Route::middleware(['auth', 'verified'])->group(function () {
    
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/email/verify/status', function (Request $request) {
        return response()->json([
            'verified' => $request->user()?->hasVerifiedEmail() ?? false,
        ]);
    })->name('verification.status');

    // 个人资料管理
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // 3. 核心业务路由 (统一前缀 admin)
    Route::name('admin.')->prefix('admin')->group(function () {
        
        // --- 所有人(登录后)都能访问的基础资源 ---
        require __DIR__.'/paymentRoute.php';
        require __DIR__.'/leasesRoute.php';
        require __DIR__.'/maintenanceRoute.php';
        require __DIR__.'/roomRoute.php'; // 如果普通用户也能看房

        // --- 只有管理员 (owner-admin) 权限能进的路由 ---
        Route::middleware('can:owner-admin')->group(function () {
            
            // 基础资源管理
            require __DIR__.'/ownerRoute.php';
            require __DIR__.'/tenantRoute.php';
            require __DIR__.'/staffRoute.php';
            require __DIR__.'/userManagementRoute.php';
            
            // 客服系统相关
            require __DIR__.'/customerServiceRoute.php';
            
            // 特定功能路由
            Route::get('tenants/ic-photo/{filename}', [TenantsController::class, 'showIcPhoto'])->name('tenants.ic_photo');
            Route::get('ticket-messages/{ticket}', [TicketController::class, 'getNewMessages'])->name('customerService.newMessages');
        });
    });
});

// 4. 认证相关路由 (Login, Register 等)
require __DIR__.'/auth.php';