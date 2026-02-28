<?php
use App\Http\Controllers\MaintenanceController;
use Illuminate\Support\Facades\Route;

// AJAX endpoint to get assets by lease ID
Route::get('maintenance/assets-by-lease', [MaintenanceController::class, 'assetsByLease'])
    ->name('maintenance.assets-by-lease');

// Photo serving route
Route::get('maintenance/photo/{filename}', [MaintenanceController::class, 'showPhoto'])
    ->name('maintenance.photo');

// Standard resource routes (exclude destroy since deletion is not allowed)
Route::resource('maintenance', MaintenanceController::class)->except(['destroy']);