<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/invoices/statement', [InvoiceController::class, 'statement'])->name('invoices.statement');
Route::get('/leases/{lease}/invoice-statement', [InvoiceController::class, 'statement'])->name('leases.invoice-statement');
Route::get('/invoices/rows', [InvoiceController::class, 'indexRows'])->name('invoices.rows');
Route::resource('invoices', InvoiceController::class);
Route::get('/leases/{lease}/invoice-rows', [InvoiceController::class, 'leaseRows'])->name('invoices.lease-rows');
Route::get('/invoices/{invoice}/document-data', [InvoiceController::class, 'documentData'])->name('invoices.document-data');
Route::get('/invoices/{invoice}/receipts/{transaction}/document-data', [InvoiceController::class, 'receiptData'])->name('invoices.receipt-data');
Route::post('/invoices/{invoice}/payment', [InvoiceController::class, 'recordPayment'])->name('invoices.payment');
Route::post('/leases/{lease}/invoices/manual', [InvoiceController::class, 'storeManualInvoice'])->name('invoices.store-manual');
Route::post('/leases/{lease}/invoices/generate', [InvoiceController::class, 'generateInvoice'])->name('invoices.generate');
Route::post('/invoices/{invoice}/reject-payment', [InvoiceController::class, 'rejectPayment'])->name('invoices.reject-payment');
Route::patch('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
Route::post('/invoices/generate-auto-invoice/{lease}', [InvoiceController::class, 'generateAutoInvoice'])->name('invoices.generate-auto-invoice');
