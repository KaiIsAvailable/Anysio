<?php
use App\Http\Controllers\AgreementController;
use Illuminate\Support\Facades\Route;

Route::resource('agreements', AgreementController::class);
Route::post('agreements/{agreement}/activate', [AgreementController::class, 'activate'])
      ->name('agreements.activate');