<?php
use App\Http\Controllers\{TenantTicketController, TicketMsgController};
use Illuminate\Support\Facades\Route;

// Maintenance shares the tenant conversation flow, with explicit manager-only entry points.
Route::prefix('maintenance')->name('maintenance.')->middleware('can:maintenance-tickets')->group(function () {
    Route::get('/', [TenantTicketController::class, 'index'])->name('index');
    Route::get('/{ticket}', [TenantTicketController::class, 'show'])->name('show');
    Route::post('/{ticket}/messages', [TenantTicketController::class, 'reply'])->name('reply');
    Route::get('/{ticket}/attachments/{message}', [TicketMsgController::class, 'attachment'])->name('attachment');
});