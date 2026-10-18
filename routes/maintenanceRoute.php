<?php
use App\Http\Controllers\{TenantTicketController, TicketMsgController};
use Illuminate\Support\Facades\Route;

// Shared module authorization; the service additionally checks each ticket's data scope.
Route::prefix('maintenance')->name('maintenance.')->middleware(\App\Http\Middleware\AuthorizeTicketModule::class.':maintenance-tickets')->group(function () {
    Route::get('/', [TenantTicketController::class, 'index'])->name('index');
    Route::get('/{ticket}', [TenantTicketController::class, 'show'])->name('show');
    Route::post('/{ticket}/messages', [TenantTicketController::class, 'reply'])->name('reply');
    Route::post('/{ticket}/read', [TenantTicketController::class, 'markRead'])->name('markRead');
    Route::get('/{ticket}/attachments/{message}', [TicketMsgController::class, 'attachment'])->name('attachment');
});
