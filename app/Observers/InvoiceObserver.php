<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Services\NotificationService;

class InvoiceObserver
{
    public function __construct(protected NotificationService $notificationService) {}

    public function created(Invoice $invoice): void
    {
        // 不再判断类型，直接发送
    }

    /**
     * Handle the Payment "deleted" event.
     */
    public function deleted(Invoice $invoice): void
    {
        //
    }

    /**
     * Handle the Payment "restored" event.
     */
    public function restored(Invoice $invoice): void
    {
        //
    }

    /**
     * Handle the Payment "force deleted" event.
     */
    public function forceDeleted(Invoice $invoice): void
    {
        //
    }
}
