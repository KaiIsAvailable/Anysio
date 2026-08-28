<?php

use App\Http\Controllers\{ProfileController, TenantsController, TicketController, WelcomeController, UserController};
use Illuminate\Support\Facades\{Artisan, Route, File, DB, Storage};
use Illuminate\Http\Request;
use App\Models\{User, Tenants, EmergencyContact};

// 1. 公开路由
Route::get('/', function () {return view('welcome');})->name('welcome');
Route::get('/', [WelcomeController::class, 'index']);

Route::middleware(['auth', 'can:super-admin'])->group(function () {
    Route::post('/user/verify-password', [UserController::class, 'verifyPassword'])->name('user.verify-password');
});

// 2. 登录后的基础路由
Route::middleware(['auth', 'verified'])->group(function () {
    
    //Route::get('/dashboard', function () {
    //    return view('dashboard');
    //})->name('dashboard');
    require __DIR__.'/dashboardRoute.php';

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
        require __DIR__.'/leasesRoute.php';
        require __DIR__.'/maintenanceRoute.php';
        require __DIR__.'/propertyRoute.php';
        require __DIR__.'/unitRoute.php';
        require __DIR__.'/roomRoute.php';
        require __DIR__.'/roomAssetRoute.php';
        require __DIR__.'/ownerRoute.php';
        require __DIR__.'/tenantRoute.php';
        require __DIR__.'/staffRoute.php';
        require __DIR__.'/userManagementRoute.php';
        require __DIR__.'/customerServiceRoute.php';
        require __DIR__.'/documentTemplateRoute.php';
        require __DIR__.'/packageRoute.php';
        require __DIR__.'/auditLogRoute.php';
        require __DIR__.'/notificationRoute.php';
        require __DIR__.'/invoiceRoute.php';
        require __DIR__.'/paymentRoute.php';
        require __DIR__.'/feeTypeRoute.php';
        require __DIR__.'/excelImportRoute.php';

        // --- 只有管理员 (owner-admin) 权限能进的路由 ---
        Route::middleware('can:owner-admin')->group(function () {
            // 特定功能路由
            Route::get('ticket-messages/{ticket}', [TicketController::class, 'getNewMessages'])->name('customerService.newMessages');
        });
    });
});

//run seeder
Route::get('/run-seeders-xyz', function (Request $request) {
    $redirectTo = $request->input('redirect', route('dashboard'));
    $seeder = $request->input('seeder'); 

    try {
        if (empty($seeder)) {
            return redirect()->to($redirectTo)->with('error', 'Error: Please choose a seeder file to run.');
        }

        Artisan::call('db:seed', [
            '--class' => $seeder,
            '--force' => true
        ]);
        
        $message = "Seeder [{$seeder}] executed successfully!";

        return redirect()->to($redirectTo)->with('success', $message);
    } catch (\Exception $e) {
        return redirect()->to($redirectTo)->with('error', 'Error: ' . $e->getMessage());
    }
})->name('run.seeders');

//run migrate
Route::get('/run-migrations-xyz', function (Request $request) {
    try {
        Artisan::call('migrate', ['--force' => true]);
        
        $redirectTo = $request->input('redirect', route('dashboard'));
        
        return redirect()->to($redirectTo)->with('success', 'Migrations executed successfully!');
    } catch (\Exception $e) {
        $redirectTo = $request->input('redirect', route('dashboard'));
        
        return redirect()->to($redirectTo)->with('error', 'Migration Error: ' . $e->getMessage());
    }
})->name('run.migrations');

Route::get('/debug-revert/{filename}', function ($filename) {
    $filePath = 'imports/' . $filename;

    if (Storage::disk('local')->exists($filePath)) {
        $data = json_decode(Storage::disk('local')->get($filePath), true);

        DB::transaction(function () use ($data) {
            if (!empty($data['emergency_contacts'])) {
                EmergencyContact::whereIn('id', $data['emergency_contacts'])->delete();
            }
            if (!empty($data['tenants'])) {
                Tenants::whereIn('id', $data['tenants'])->delete();
            }
            if (!empty($data['users'])) {
                User::whereIn('id', $data['users'])->delete();
            }
        });

        Storage::disk('local')->delete($filePath);

        return "Successfully reverted import batch and cleaned up database!";
    }

    return "Session file not found.";
});

// 4. 认证相关路由 (Login, Register 等)
require __DIR__.'/auth.php';