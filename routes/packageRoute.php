<?php
use App\Http\Controllers\RefCodePackageController;
use Illuminate\Support\Facades\Route;

Route::resource('packages', RefCodePackageController::class);
Route::patch('packages/{id}/restore', [RefCodePackageController::class, 'restore'])->name('packages.restore');