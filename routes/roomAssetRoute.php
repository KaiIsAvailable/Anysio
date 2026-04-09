<?php

use App\Http\Controllers\RoomAssetController;
use Illuminate\Support\Facades\Route;

Route::resource('roomAsset', RoomAssetController::class);
Route::patch('roomAsset/{id}/restore', [RoomAssetController::class, 'restore'])->name('roomAsset.restore');