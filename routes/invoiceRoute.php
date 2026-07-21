<?php
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::resource('invoices', InvoiceController::class);
Route::post('/leases/{lease}/invoices/generate',[InvoiceController::class, 'generateInvoice'])->name('invoices.generate');
Route::post('/invoices/{invoice}/reject-payment',[InvoiceController::class, 'rejectPayment'])->name('invoices.reject-payment');
Route::delete('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');