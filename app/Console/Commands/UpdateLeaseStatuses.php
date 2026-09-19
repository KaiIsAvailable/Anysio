<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use app\Models\{Lease};
use Illuminate\Support\Facades\DB;

class UpdateLeaseStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leases:update-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically update lease statuses based on end dates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now();

        // 1. Move New/Renew leases to 'pending_renewal' if within 3 months (90 days) of expiry
        Lease::whereIn('status', ['New', 'Renew'])
            ->where('end_date', '>', $now)
            ->where('end_date', '<=', $now->copy()->addDays(90))
            ->update(['is_pending_renewal' => true]);

        // 2. Move unhandled leases to 'ended' if past their end date
        Lease::whereIn('status', ['New', 'Renew'])
            ->where('end_date', '<', $now)
            ->update(['status' => 'End']);
        
        $this->info('Lease statuses updated successfully.');
    }
}
