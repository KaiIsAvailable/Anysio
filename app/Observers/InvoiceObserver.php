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
        // Load related user if not already loaded
        $invoice->loadMissing('user');
        $senderId = Auth::id() ?? $invoice->created_by;
        /*
        |--------------------------------------------------------------------------
        | 1. Notify Customer (OwnerAdmin / AgentAdmin)
        |--------------------------------------------------------------------------
        */

        $this->notificationService->send(
            $senderId,
            'invoice_received',
            'You have received a new invoice.',
            [
                'id'      => $invoice->id,
                'name'    => $invoice->invoice_no,
                'details' => 'Amount: RM ' . number_format($invoice->total_amount / 100, 2),
                'status'  => ucfirst($invoice->status),
                'url'     => route('admin.invoices.show', $invoice),
            ],
            [
                $invoice->user_id,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 2. Notify Platform Admin
        |--------------------------------------------------------------------------
        */

        $adminIds = User::whereIn('role', [
                'super-admin',
                'admin',
            ])
            ->pluck('id')
            ->toArray();

        if (!empty($adminIds)) {

            $this->notificationService->send(
                $senderId,
                'invoice_generated',
                'Invoice Generated',
                [
                    'id'      => $invoice->id,
                    'name'    => $invoice->invoice_no,
                    'details' => 'Amount: RM ' . number_format($invoice->total_amount / 100, 2),
                    'status'  => ucfirst($invoice->status),
                    'url'     => route('admin.invoices.show', $invoice),
                ],
                $adminIds
            );
        }
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
