<?php
use App\Http\Controllers\{TenantTicketController, TicketMsgController};
use Illuminate\Support\Facades\Route;

// Shared module authorization; the service additionally checks each ticket's data scope.
Route::prefix('maintenance')->name('maintenance.')->middleware(\App\Http\Middleware\AuthorizeTicketModule::class.':maintenance-tickets')->group(function () {
    Route::get('/work-orders', [\App\Http\Controllers\MaintenanceController::class, 'index'])->name('work-orders.index');
    Route::get('/work-orders/{maintenance}', [\App\Http\Controllers\MaintenanceController::class, 'show'])->name('work-orders.show');
    Route::post('/work-orders/{maintenance}/assign', [\App\Http\Controllers\MaintenanceController::class, 'assign'])->name('work-orders.assign');
    Route::post('/work-orders/{maintenance}/complete', [\App\Http\Controllers\MaintenanceController::class, 'complete'])->name('work-orders.complete');
    Route::get('/tickets/{ticket}/maintenance-context', [\App\Http\Controllers\MaintenanceController::class, 'context'])->name('work-orders.context');
    Route::post('/tickets/{ticket}/work-orders', [\App\Http\Controllers\MaintenanceController::class, 'store'])->name('work-orders.store');
    Route::get('/', [TenantTicketController::class, 'index'])->name('index');
    Route::get('/{ticket}', [TenantTicketController::class, 'show'])->name('show');
    Route::post('/{ticket}/messages', [TenantTicketController::class, 'reply'])->name('reply');
    Route::post('/{ticket}/read', [TenantTicketController::class, 'markRead'])->name('markRead');
    Route::get('/{ticket}/attachments/{message}', [TicketMsgController::class, 'attachment'])->name('attachment');
});
