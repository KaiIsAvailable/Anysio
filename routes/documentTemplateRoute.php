<?php
use App\Http\Controllers\DocumentTemplateController;
use Illuminate\Support\Facades\Route;

Route::resource('document-templates', DocumentTemplateController::class);
Route::post('document-templates/{document-templates}/activate', [DocumentTemplateController::class, 'activate'])
      ->name('document-templates.activate');