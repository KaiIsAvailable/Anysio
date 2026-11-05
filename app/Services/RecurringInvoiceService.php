<?php

namespace App\Services;

use App\Models\{DocumentTemplate, Invoice, Lease, User, Tenants};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringInvoiceService
{
    public function __construct(
        private SettingService $settings,
        private RecurringInvoicePeriod $periods,
        private DocumentSequenceService $sequences,
    ) {}

    public function generate(Lease $lease, User $issuer, bool $automatic, bool $dryRun = false): int
    {
        return DB::transaction(function () use ($lease, $issuer, $automatic, $dryRun) {
            // Both entry points lock the same persistent row before checking invoices.
            $lease = Lease::whereKey($lease->id)->lockForUpdate()->firstOrFail();
            $today = Carbon::today(config('app.timezone'));
            if (!$lease->is_current || !in_array($lease->status, ['New', 'Renew'])
                || !$lease->start_date || $lease->cancelled_at || $lease->checked_out_at || $lease->agreement_ended_at
                || $today->lt($lease->start_date) || ($lease->end_date && $today->gt($lease->end_date))) return 0;

            $config = $this->settings->getEffectiveSettings($issuer->fresh());
            $mode = data_get($config, 'due_date_config.value.recurring_type', 'manual');
            if (!data_get($config, 'due_date_config.is_active', true)) return 0;
            if ($automatic ? !in_array($mode, ['auto', 'both']) : !in_array($mode, ['manual', 'both'])) return 0;
            $offset = (int) data_get($config, 'due_date_config.value.recurring_feq_days', 0);
            $offset *= data_get($config, 'due_date_config.value.recurring_feq', 'before') === 'after' ? 1 : -1;
            $target = $automatic ? $today->copy()->subDays($offset) : $today->copy();
            $groups = [];
            foreach ($lease->charges()->recurring()->with('feeType')->get() as $charge) {
                $period = $this->periods->containing($lease->start_date, $target, $charge->frequency);
                if (!$period || ($automatic && !$period->equalTo($target))) continue;
                $scheduled = $period->copy()->addDays($offset);
                if ($lease->end_date && ($period->gt($lease->end_date) || ($automatic && $scheduled->gt($lease->end_date)))) continue;
                $fee = $charge->feeType;
                if (!$fee || !$fee->is_active || (!$fee->is_system && $fee->user_id !== $issuer->id)
                    || $charge->amount <= 0 || !$this->settings->isFeeTypeActive($fee, $config)) continue;

                // Include void/deleted invoices: an issued period must never be issued again.
                // Legacy recurring invoices stored month-start and omitted lease_charge_id.
                $dates = [$period->toDateString()];
                if (in_array($charge->frequency, ['monthly', 'yearly']) || $period->equalTo($lease->start_date)) {
                    $dates[] = $period->copy()->startOfMonth()->toDateString();
                }
                $exists = DB::table('invoices as i')->join('invoice_items as item', 'item.invoice_id', '=', 'i.id')
                    ->where('i.lease_id', $lease->id)
                    ->where(function ($q) use ($dates) {
                        foreach (array_unique($dates) as $date) $q->orWhereDate('i.period', $date);
                    })
                    ->where(function ($q) use ($charge) {
                        $q->where('item.lease_charge_id', $charge->id)
                            ->orWhere(fn ($q) => $q->whereNull('item.lease_charge_id')->where('item.fee_type_id', $charge->fee_type_id));
                    })->exists();
                if (!$exists) $groups[$period->toDateString()][] = $charge;
            }
            if ($dryRun || !$groups) return count($groups);

            // Serialize sequence allocation across leases for this issuer as well.
            User::whereKey($issuer->id)->lockForUpdate()->firstOrFail();
            $template = DocumentTemplate::where('category', 'invoice')->where('status', 'active')
                ->where(fn ($q) => $q->where('user_id', $issuer->id)->orWhereNull('user_id'))
                ->orderByRaw('user_id IS NULL')->first();
            foreach ($groups as $period => $charges) {
                $total = array_sum(array_map(fn ($charge) => $charge->amount, $charges));
                $invoice = Invoice::create([
                    'billable_type' => Tenants::class, 'billable_id' => $lease->tenant_id,
                    'lease_id' => $lease->id, 'user_id' => $issuer->id,
                    'document_template_id' => $template?->id,
                    'invoice_no' => $this->sequences->generateInvoiceNumber($issuer),
                    'type' => 'rent', 'period' => $period,
                    'due_date' => Carbon::parse($period)->addDays((int) data_get($config, 'due_date_config.value.days', 7))->toDateString(),
                    'total_amount' => $total, 'amount_paid' => 0, 'amount_balance' => $total,
                    'status' => 'unpaid', 'remarks' => 'Recurring charges for '.$period,
                ]);
                foreach ($charges as $charge) {
                    $invoice->items()->create([
                        'fee_type_id' => $charge->fee_type_id, 'lease_charge_id' => $charge->id,
                        'description' => $charge->description, 'amount' => $charge->amount,
                    ]);
                    $charge->update(['next_billing_date' => $this->periods->next($lease->start_date, Carbon::parse($period), $charge->frequency)]);
                }
            }
            return count($groups);
        });
    }
}
