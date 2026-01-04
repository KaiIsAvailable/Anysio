<?php
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::resource('rooms', RoomController::class);