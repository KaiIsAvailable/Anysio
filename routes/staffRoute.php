<?php
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::resource('staff', StaffController::class);