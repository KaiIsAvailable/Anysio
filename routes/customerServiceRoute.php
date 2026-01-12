<?php
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::resource('customerService', TicketController::class);
Route::post('customerService/{id}/grab', [TicketController::class, 'grab'])->name('customerService.grab');