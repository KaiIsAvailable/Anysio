<?php
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::resource('userManagement', UserManagementController::class);