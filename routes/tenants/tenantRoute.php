<?php
use App\Http\Controllers\TenantsController;
use Illuminate\Support\Facades\Route;

Route::get('/tenant/dashboard', [TenantsController::class, 'dashboard'])->name('dashboard');