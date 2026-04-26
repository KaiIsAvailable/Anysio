<?php
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::resource('userManagement', UserManagementController::class);
Route::get('display-receipt/{filename}', [UserManagementController::class, 'displayReceipt'])
    ->name('receipt.display')
    ->where('filename', '.*');
Route::patch('payments/{payment}/approve', [UserManagementController::class, 'approve'])->name('payments.approve');
Route::patch('payments/{payment}/reject', [UserManagementController::class, 'reject'])->name('payments.reject');