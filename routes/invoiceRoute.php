<?php
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::resource('invoices', InvoiceController::class);
Route::post('/leases/{lease}/invoices/generate',[InvoiceController::class, 'generateInvoice'])->name('invoices.generate');
Route::delete('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');