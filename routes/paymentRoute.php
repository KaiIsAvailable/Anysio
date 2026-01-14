<?php
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::resource('payments', PaymentController::class);