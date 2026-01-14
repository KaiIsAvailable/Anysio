<?php
use App\Http\Controllers\MaintenanceController;
use Illuminate\Support\Facades\Route;

Route::resource('maintenance', MaintenanceController::class);