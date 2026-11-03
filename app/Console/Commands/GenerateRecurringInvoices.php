<?php

namespace App\Console\Commands;

use App\Models\Lease;
use App\Services\{LeaseManagementResolver, RecurringInvoiceService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'invoices:generate-recurring {--dry-run : Read-only preview without invoices, sequences or charge updates} {--lease= : Limit to one lease ID}';
    protected $description = 'Generate only recurring invoices scheduled today; never backfill missed periods';

    public function handle(LeaseManagementResolver $resolver, RecurringInvoiceService $service): int
    {
        $leaseId = $this->option('lease');
        if ($leaseId !== null && trim($leaseId) === '') {
            $this->error('--lease must contain a lease ID; refusing an unrestricted run.');
            return self::FAILURE;
        }
        $count = $errors = $unresolved = 0;
        Lease::where('is_current', true)->whereIn('status', ['New', 'Renew'])
            ->when($leaseId !== null, fn ($q) => $q->whereKey($leaseId))
            ->chunkById(100, function ($leases) use ($resolver, $service, &$count, &$errors, &$unresolved) {
                foreach ($leases as $lease) {
                    try {
                        $issuer = $resolver->resolve($lease);
                        if (!$issuer) {
                            $unresolved++;
                            $this->warn("Skipped {$lease->id}: management account is unresolved or ambiguous.");
                            continue;
                        }
                        $number = $service->generate($lease, $issuer, true, (bool) $this->option('dry-run'));
                        $count += $number;
                        if ($number) $this->line("Lease {$lease->id}: {$number} invoice(s).");
                    } catch (\Throwable $e) {
                        $errors++;
                        Log::error('Recurring invoice generation failed', ['lease_id' => $lease->id, 'exception' => $e]);
                        $this->error("Failed lease {$lease->id}: {$e->getMessage()}");
                    }
                }
            });
        $this->info(($this->option('dry-run') ? 'Dry-run eligible' : 'Generated').": {$count}; unresolved: {$unresolved}; errors: {$errors}.");
        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
