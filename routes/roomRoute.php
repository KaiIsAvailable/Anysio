<?php

use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::resource('rooms', RoomController::class);

// Room Assets (only for Room module)
Route::post('rooms/{room}/assets', [RoomController::class, 'assetStore'])->name('rooms.assets.store');
Route::patch('rooms/{room}/assets/{asset}', [RoomController::class, 'assetUpdate'])->name('rooms.assets.update');
Route::delete('rooms/{room}/assets/{asset}', [RoomController::class, 'assetDestroy'])->name('rooms.assets.destroy');
