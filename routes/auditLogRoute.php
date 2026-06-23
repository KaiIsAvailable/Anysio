<?php
use App\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index');