<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Lease;
use App\Models\UserManagement;
use App\Services\SettingService;
use Illuminate\Support\Facades\Log;

class UpdateLeaseStatuses extends Command
{
    protected $signature = 'leases:update-statuses';
    protected $description = 'Automatically update lease statuses for all accounts using their custom settings';

    public function handle(SettingService $settingService)
    {
        $now = now();

        UserManagement::chunk(100, function ($userManagements) use ($settingService, $now) {
            foreach ($userManagements as $userMgmt) {
                
                // 1. Get custom settings for this account
                $settings = $settingService->getEffectiveSettings($userMgmt->user_id);
                $days = (int) data_get($settings, 'pending_renewal_config.value.days', 90);

                // ==========================================
                // PREVIEW & LOG: Pending Renewal Leases
                // ==========================================
                $pendingLeasesQuery = Lease::whereHas('tenant', function ($query) use ($userMgmt) {
                        $query->where('created_by', $userMgmt->user_id);
                    })
                    ->whereIn('status', ['New', 'Renew'])
                    ->where('end_date', '>', $now)
                    ->where('end_date', '<=', $now->copy()->addDays($days));

                // Grab the actual records to log them
                $pendingLeases = $pendingLeasesQuery->get();

                Log::channel('testing')->info('Leases matching Pending Renewal criteria', [
                    'user_management_id' => $userMgmt->id,
                    'user_id' => $userMgmt->user_id,
                    'threshold_days' => $days,
                    'matched_lease_count' => $pendingLeases->count(),
                    'leases' => $pendingLeases->map(fn($l) => [
                        'id' => $l->id,
                        'end_date' => $l->end_date,
                        'status' => $l->status,
                    ])->toArray(),
                ]);

                // Now actually perform the update
                $pendingLeasesQuery->update(['is_pending_renewal' => true]);


                // ==========================================
                // PREVIEW & LOG: Expired / Ended Leases
                // ==========================================
                $endedLeasesQuery = Lease::whereHas('tenant', function ($query) use ($userMgmt) {
                        $query->where('created_by', $userMgmt->user_id);
                    })
                    ->whereIn('status', ['New', 'Renew'])
                    ->where('end_date', '<', $now);

                // Grab the actual records to log them
                $endedLeases = $endedLeasesQuery->get();

                Log::channel('testing')->info('Leases matching Expired (End) criteria', [
                    'user_management_id' => $userMgmt->id,
                    'user_id' => $userMgmt->user_id,
                    'matched_lease_count' => $endedLeases->count(),
                    'leases' => $endedLeases->map(fn($l) => [
                        'id' => $l->id,
                        'end_date' => $l->end_date,
                        'status' => $l->status,
                    ])->toArray(),
                ]);

                // Now actually perform the update
                $endedLeasesQuery->update(['status' => 'End']);
            }
        });

        Log::channel('testing')->info('Ran UpdateLeaseStatuses cron successfully for all accounts.');
        $this->info("Lease statuses checked, logged, and updated successfully.");
    }
}