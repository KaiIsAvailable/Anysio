<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Services\NotificationService;

class PaymentObserver
{
    public function __construct(protected NotificationService $notificationService) {}

    public function created(Payment $payment): void
    {
        // 不再判断类型，直接发送
        $this->notificationService->send(
            Auth::id(),
            'invoice_generated',
            "New Invoice: {$payment->invoice_no}",
            [
                'payment_id'  => $payment->id,
                'entity_name' => 'Invoice Ref: ' . $payment->invoice_no,
                'details'     => "A new invoice (Type: {$payment->payment_type}) has been generated.",
                'url'         => '',
            ],
            [$payment->tenant->user_id, Auth::id()]
        );
    }

    public function updated(Payment $payment): void
    {
        // 检查状态是否从 unpaid 变为 paid
        if ($payment->wasChanged('status') && $payment->status === 'paid') {
            
            $this->notificationService->send(
                Auth::id() ?? 0,
                'payment_received',
                "Payment Received: {$payment->invoice_no}",
                [
                    'payment_id'  => $payment->id,
                    'amount'      => $payment->amount_paid / 100,
                    'entity_name' => 'Payment for ' . $payment->invoice_no,
                    'url'         => '',
                ],
                [$payment->tenant->user_id, Auth::id()]
            );
        }
    }

    /**
     * Handle the Payment "deleted" event.
     */
    public function deleted(Payment $payment): void
    {
        //
    }

    /**
     * Handle the Payment "restored" event.
     */
    public function restored(Payment $payment): void
    {
        //
    }

    /**
     * Handle the Payment "force deleted" event.
     */
    public function forceDeleted(Payment $payment): void
    {
        //
    }
}
