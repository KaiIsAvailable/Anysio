<?php

namespace App\Observers;

use App\Models\Lease;
use Illuminate\Support\Facades\Auth;
use App\Services\NotificationService;

class LeaseObserver
{
    public function __construct(protected NotificationService $notificationService) {}
    public function created(Lease $lease)
    {
        $status = ucfirst($lease->status);
        $title = "{$status} Lease Created: {$lease->leasableName}";

        $this->notificationService->send(
            Auth::id(),
            'lease_created',
            $title,
            [
                'lease_id'    => $lease->id, // 补上这个 ID
                'entity_name' => $lease->leasableName,
                'details'     => 'A new lease has been generated and requires your review.',
                'url'         => route('admin.leases.show', ['lease' => $lease->id]),
                'status'      => ucfirst($lease->status), // 顺便加上状态，方便前端显示 Badge
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
        //
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
