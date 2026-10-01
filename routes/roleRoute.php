<?php

use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::resource('roles', RoleController::class);