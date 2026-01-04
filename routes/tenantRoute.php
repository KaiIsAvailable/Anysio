<?php
use App\Http\Controllers\TenantsController;
use Illuminate\Support\Facades\Route;

Route::resource('tenants', TenantsController::class);