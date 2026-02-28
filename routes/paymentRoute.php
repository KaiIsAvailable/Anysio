<?php
use App\Http\Controllers\PaymentsController;
use Illuminate\Support\Facades\Route;

Route::resource('payments', PaymentsController::class);
// 1. 生成下个月租金账单 (针对某个租客)
Route::post('/tenants/{lease}/payments/generate-rent', [PaymentsController::class, 'generateMonthlyInvoice'])
    ->name('payments.generateMonthlyInvoice');

// 2. 创建其他杂费账单 (Others 按钮提交到这里)
Route::post('/tenants/{tenant}/payments/storeManualInvoice', [PaymentsController::class, 'storeManualInvoice'])
    ->name('payments.storeManualInvoice');

// 3. 确认支付 (点 Pay 按钮提交到这里，使用 PATCH 因为是更新现有记录的状态)
Route::patch('/payments/{payment}/process', [PaymentsController::class, 'processPayment'])
    ->name('payments.processPayment');

Route::patch('/payments/{payment}/update', [PaymentsController::class, 'update'])
    ->name('payments.update');

Route::delete('/payments/{payment}/voidPayment', [PaymentsController::class, 'voidPayment'])
    ->name('payments.voidPayment');