<?php
namespace App\Services;

use App\Models\Invoice;

class InvoiceService
{
    // 获取账单的未支付余额
    public function getBalance(Invoice $invoice): float
    {
        $paid = $invoice->paymentRecords()->sum('amount');
        return (float) ($invoice->total_amount - $paid);
    }

    // 更新账单状态
    public function updateStatus(Invoice $invoice): void
    {
        $balance = $this->getBalance($invoice);
        
        if ($balance <= 0) {
            $invoice->update(['status' => 'paid']);
        } elseif ($balance < $invoice->total_amount) {
            $invoice->update(['status' => 'partial']);
        } else {
            $invoice->update(['status' => 'unpaid']);
        }
    }
}