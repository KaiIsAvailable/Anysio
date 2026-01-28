<?php
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

// Custom routes (new-messages route is defined in web.php for priority)
Route::post('customerService/{customerService}/grab', [TicketController::class, 'grab'])->name('customerService.grab');
Route::post('customerService/{customerService}/close', [TicketController::class, 'close'])->name('customerService.close');

// Resource routes
Route::resource('customerService', TicketController::class);