<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillDepositInvoiceLinks extends Command
{
    protected $signature = 'invoices:backfill-deposit-links
        {--lease= : Limit to one lease ID}
        {--apply : Save the unambiguous links; otherwise preview only}';

    protected $description = 'Preview or backfill missing deposit invoice item links to lease charges';

    public function handle(): int
    {
        if (!Schema::hasColumn('invoice_items', 'lease_charge_id')) {
            $this->error('Run the invoice item lease_charge_id migration first.');
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'Applying deposit links.' : 'Preview only. No records will be changed.');
        $matched = 0;
        $skipped = 0;

        $leases = DB::table('leases')
            ->when($this->option('lease'), fn ($q, $id) => $q->where('id', $id))
            ->orderBy('id');

        foreach ($leases->lazyById(200) as $lease) {
            DB::transaction(function () use ($lease, $apply, &$matched, &$skipped) {
                // Serialize with normal invoice creation and refund approval for this lease.
                DB::table('leases')->where('id', $lease->id)->lockForUpdate()->first();
                $items = DB::table('invoice_items as item')
                    ->join('invoices as invoice', 'invoice.id', '=', 'item.invoice_id')
                    ->join('fee_types as fee', 'fee.id', '=', 'item.fee_type_id')
                    ->where('invoice.lease_id', $lease->id)
                    ->whereNull('invoice.deleted_at')->where('invoice.status', '!=', 'void')
                    ->where('fee.category', 'deposit')->where('item.amount', '>', 0)
                    ->select('item.*', 'invoice.invoice_no')->lockForUpdate()->get();
                $charges = DB::table('lease_charges')->where('lease_id', $lease->id)
                    ->whereNull('deleted_at')->lockForUpdate()->get();

                foreach ($items->whereNull('lease_charge_id') as $item) {
                    $candidates = $charges->filter(fn ($charge) =>
                        $charge->fee_type_id === $item->fee_type_id
                        && (int) $charge->amount === (int) $item->amount);
                    $reason = null;
                    if ($candidates->count() !== 1) {
                        $reason = $candidates->isEmpty() ? 'no matching charge' : 'multiple matching charges';
                    } else {
                        $charge = $candidates->first();
                        $competing = $items->filter(fn ($other) =>
                            $other->id !== $item->id && (
                                $other->lease_charge_id === $charge->id
                                || ($other->fee_type_id === $item->fee_type_id
                                    && (int) $other->amount === (int) $item->amount)));
                        if ($competing->isNotEmpty()) $reason = 'another invoice item could use this charge';
                    }

                    if ($reason) {
                        $skipped++;
                        $this->warn("SKIP lease={$lease->id} invoice={$item->invoice_no} item={$item->id}: {$reason}");
                        continue;
                    }

                    if ($apply) {
                        // Only repair the relationship. Do not rewrite money, payments or refund snapshots.
                        DB::table('invoice_items')->where('id', $item->id)->whereNull('lease_charge_id')
                            ->update(['lease_charge_id' => $charge->id]);
                    }
                    $matched++;
                    $this->line("LINK lease={$lease->id} invoice={$item->invoice_no} item={$item->id} charge={$charge->id}");
                }
            }, 3);
        }

        $this->info(($apply ? 'Linked' : 'Would link').": {$matched}; skipped: {$skipped}.");
        $this->line('Refund snapshots are unchanged. Review affected draft/pending refunds before approval; revoke existing approval before recalculating.');
        return self::SUCCESS;
    }
}
