<?php
use App\Http\Controllers\LeaseController;
use Illuminate\Support\Facades\Route;

Route::resource('leases', LeaseController::class);