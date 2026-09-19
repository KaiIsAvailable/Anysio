<?php

namespace App\Observers;

use App\Models\Lease;
use Illuminate\Support\Facades\Auth;
use App\Services\NotificationService;
use App\Services\InvoiceService;
use Carbon\Carbon;

class LeaseObserver
{
    public function __construct(protected NotificationService $notificationService, private readonly InvoiceService $invoiceService) {}

    public function created(Lease $lease)
    {
        $status = ucfirst($lease->status);
        $title = "{$status} Lease Created: {$lease->leasableName}";

        $this->notificationService->send(
            Auth::id(),
            'lease_created',
            $title,
            [
                'id'      => $lease->id, // 补上这个 ID
                'name'    => $lease->leasableName,
                'details' => 'A new lease has been generated and requires your review.',
                'status'  => ucfirst($lease->status), // 顺便加上状态，方便前端显示 Badge
                'url'     => route('admin.leases.show', ['lease' => $lease->id]),
            ],
            [
                $lease->tenant->user_id, 
                $lease->ownerUserId,
                Auth::id()
            ] 
        );
    }

    /**
     * Handle the Lease "updated" event.
     */
    public function updated(Lease $lease): void
    {
        // Check if the status field was modified during this update
        if ($lease->wasChanged('status')) {
            $newStatus = strtolower($lease->status);

            // Handle 'pending_renewal' status
            if ($newStatus === 'pending_renewal') {
                $this->sendPendingRenewalNotifications($lease);
            }

            // Handle 'check_out' status (if you want separate logic for check out)
            if ($newStatus === 'check_out') {
                // $this->sendCheckOutNotifications($lease);
            }
        }
    }

    /**
     * Send tailored notifications for pending renewals
     */
    protected function sendPendingRenewalNotifications(Lease $lease)
    {
        $senderId = get_effective_user();
        
        // Get lowercase type (e.g., "property", "unit", or "room") and name
        $type = strtolower($lease->leasableTypeLabel);
        $name = $lease->leasableName;
        
        $leasableIdentifier = "{$type} ({$name})";

        // 1. Notification for the Tenant
        $this->notificationService->send(
            $senderId,
            'lease_pending_renewal',
            'Lease Pending Renewal',
            [
                'id'      => $lease->id,
                'name'    => $name,
                'details' => "Your {$leasableIdentifier} is pending renewal. Please decide do you want to renew.",
                'status'  => ucfirst($lease->status),
                'url'     => route('admin.leases.show', ['lease' => $lease->id]),
            ],
            [$lease->tenant->user_id]
        );

        // 2. Notification for Management / Owner
        if ($lease->ownerUserId) {
            $this->notificationService->send(
                $senderId,
                'lease_pending_renewal_notice',
                'Pending Renewal Notification Sent',
                [
                    'id'      => $lease->id,
                    'name'    => $name,
                    'details' => "Pending Renewal Notification are sent to tenant for {$leasableIdentifier}.",
                    'status'  => ucfirst($lease->status),
                    'url'     => route('admin.leases.show', ['lease' => $lease->id]),
                ],
                [$lease->ownerUserId]
            );
        }
    }

    /**
     * Handle the Lease "deleted" event.
     */
    public function deleted(Lease $lease): void
    {
        //
    }

    /**
     * Handle the Lease "restored" event.
     */
    public function restored(Lease $lease): void
    {
        //
    }

    /**
     * Handle the Lease "force deleted" event.
     */
    public function forceDeleted(Lease $lease): void
    {
        //
    }
}
