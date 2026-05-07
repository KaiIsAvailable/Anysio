<?php
use App\Http\Controllers\RefCodePackageController;
use Illuminate\Support\Facades\Route;

Route::resource('packages', RefCodePackageController::class);