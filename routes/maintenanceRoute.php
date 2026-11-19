<?php
use App\Http\Controllers\{TenantTicketController, TicketMsgController};
use Illuminate\Support\Facades\Route;

// Maintenance and conversations have separate module permissions and share data-scope checks.
Route::prefix('maintenance')->name('maintenance.')->middleware(\App\Http\Middleware\AuthorizeTicketModule::class.':maintenance.tab')->group(function () {
    Route::get('/history', [\App\Http\Controllers\MaintenanceController::class, 'history'])->name('history.index');
    Route::get('/history/{type}/{id}', [\App\Http\Controllers\MaintenanceController::class, 'history'])->whereIn('type', ['property', 'unit', 'room'])->name('history');
    Route::get('/work-orders', [\App\Http\Controllers\MaintenanceController::class, 'index'])->name('work-orders.index');
    Route::post('/work-orders', [\App\Http\Controllers\MaintenanceController::class, 'storeFromList'])->name('work-orders.store-from-list');
    Route::get('/work-orders/{maintenance}/edit', [\App\Http\Controllers\MaintenanceController::class, 'edit'])->name('work-orders.edit');
    Route::get('/work-orders/{maintenance}/assign', [\App\Http\Controllers\MaintenanceController::class, 'assignment'])->name('work-orders.assignment');
    Route::get('/work-orders/{maintenance}/complete', [\App\Http\Controllers\MaintenanceController::class, 'completion'])->name('work-orders.completion');
    Route::put('/work-orders/{maintenance}', [\App\Http\Controllers\MaintenanceController::class, 'update'])->name('work-orders.update');
    Route::get('/work-orders/{maintenance}', [\App\Http\Controllers\MaintenanceController::class, 'show'])->name('work-orders.show');
    Route::post('/work-orders/{maintenance}/assign', [\App\Http\Controllers\MaintenanceController::class, 'assign'])->name('work-orders.assign');
    Route::post('/work-orders/{maintenance}/complete', [\App\Http\Controllers\MaintenanceController::class, 'complete'])->name('work-orders.complete');
    Route::get('/tickets/{ticket}/maintenance-context', [\App\Http\Controllers\MaintenanceController::class, 'context'])->name('work-orders.context');
    Route::post('/tickets/{ticket}/work-orders', [\App\Http\Controllers\MaintenanceController::class, 'store'])->name('work-orders.store');
});

Route::prefix('maintenance')->name('maintenance.')->middleware(\App\Http\Middleware\AuthorizeTicketModule::class.':service-request-tickets')->group(function () {
    Route::get('/', [TenantTicketController::class, 'index'])->name('index');
    Route::get('/unread', [TenantTicketController::class, 'unread'])->name('unread');
    Route::get('/{ticket}', [TenantTicketController::class, 'show'])->name('show');
    Route::post('/{ticket}/messages', [TenantTicketController::class, 'reply'])->name('reply');
    Route::post('/{ticket}/read', [TenantTicketController::class, 'markRead'])->name('markRead');
    Route::get('/{ticket}/attachments/{message}', [TicketMsgController::class, 'attachment'])->name('attachment');
});
