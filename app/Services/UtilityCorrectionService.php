<?php
namespace App\Services;

use App\Models\{User, Utility, UtilityAllocation, UtilityCorrection, Invoice, InvoiceItem};
use Illuminate\Support\Facades\{Auth, DB, Gate, Validator};
use Illuminate\Validation\ValidationException;

/** Explicit financial correction: retain the source, void the full invoice and reissue its items. */
class UtilityCorrectionService
{
    private function authorize(User $manager, Utility $reading): void
    {
        app(UtilityTariffManagementService::class)->authorizeCustom($manager, 'utilities.correct invoices');
        app(UtilityBillingAccess::class)->meter($manager, $reading->utilityService);
        abort_unless(Gate::any(['super-admin', 'utilities.correct invoices']), 403);
    }

    public function currentItem(Utility $reading, ?UtilityAllocation $allocation = null): ?InvoiceItem
    {
        $latest = UtilityCorrection::where('utility_id', $reading->id)->where('utility_allocation_id', $allocation?->id)
            ->where('status', 'issued')->latest('id')->first();
        return $latest?->replacementItem ?? ($allocation ? $allocation->invoiceItem : $reading->invoiceItem);
    }

    public function fingerprint(Invoice $invoice): string
    {
        return hash('sha256', json_encode([$invoice->getAttributes(), $invoice->items()->orderBy('id')->get()->map->getAttributes()->all()]));
    }

    public function propose(User $manager, Utility $reading, array $input): UtilityCorrection
    {
        $this->authorize($manager, $reading);
        $data = Validator::make($input, ['allocation_id' => ['nullable', 'ulid'],
            'amount' => ['required', 'numeric', 'between:0,21474836.47', 'decimal:0,2'],
            'reason' => ['required', 'string', 'min:5', 'max:2000']])->validate();
        return DB::transaction(function () use ($manager, $reading, $data) {
            User::whereKey($manager->id)->lockForUpdate()->firstOrFail();
            $allocation = empty($data['allocation_id']) ? null : $reading->allocations()->whereKey($data['allocation_id'])->firstOrFail();
            if ($reading->utilityService->billing_scope === 'shared' && !$allocation) {
                throw ValidationException::withMessages(['correction' => 'Select the issued tenant share to correct. Original source and allocation history remain unchanged.']);
            }
            $item = $this->currentItem($reading, $allocation);
            if (!$item || !$item->invoice || $item->invoice->status === 'void') {
                throw ValidationException::withMessages(['correction' => 'This charge has no current issued invoice. Edit unbilled readings or allocations instead.']);
            }
            $invoice = $item->invoice;
            abort_unless($invoice->user_id === $manager->id && app(LeaseManagementResolver::class)->resolve($invoice->lease)?->id === $manager->id, 403);
            $amount = UtilityDecimal::scaled($data['amount'], 2);
            if ($amount === (int) $item->amount) throw ValidationException::withMessages(['amount' => 'The corrected amount must differ from the current invoice item.']);
            return UtilityCorrection::create(['management_user_id' => $manager->id, 'utility_id' => $reading->id,
                'utility_allocation_id' => $allocation?->id, 'original_invoice_item_id' => $item->id,
                'created_by' => Auth::id(), 'reason' => $data['reason'], 'difference' => $amount - $item->amount,
                'original_snapshot' => ['charge' => $reading->calculation_details, 'allocation' => $allocation?->snapshot,
                    'amount' => $item->amount, 'invoice_hash' => $this->fingerprint($invoice)],
                'replacement_snapshot' => ['final_amount' => $amount, 'method' => 'authorized_financial_correction',
                    'reason' => $data['reason'], 'source_tariff' => data_get($reading->calculation_details, 'tariff'),
                    'proposed_at' => now()->toIso8601String()], 'status' => 'proposed']);
        });
    }

    public function confirm(User $manager, Utility $reading, UtilityCorrection $proposal, array $input): Invoice
    {
        $this->authorize($manager, $reading);
        Validator::make($input, ['acknowledge_void' => ['accepted']])->validate();
        return DB::transaction(function () use ($manager, $reading, $proposal, $input) {
            User::whereKey($manager->id)->lockForUpdate()->firstOrFail();
            $proposal = UtilityCorrection::whereKey($proposal->id)->where('utility_id', $reading->id)
                ->where('management_user_id', $manager->id)->lockForUpdate()->firstOrFail();
            if ($proposal->status !== 'proposed') throw ValidationException::withMessages(['correction' => 'This proposal was already processed.']);
            $item = $proposal->originalItem;
            $invoice = Invoice::whereKey($item->invoice_id)->lockForUpdate()->firstOrFail();
            $allocation = $proposal->utility_allocation_id ? $reading->allocations()->findOrFail($proposal->utility_allocation_id) : null;
            if ($this->currentItem($reading, $allocation)?->id !== $item->id
                || !hash_equals($proposal->original_snapshot['invoice_hash'], $this->fingerprint($invoice))) {
                throw ValidationException::withMessages(['correction' => 'The invoice changed after preview. Create a fresh correction proposal.']);
            }
            $lease = $invoice->lease;
            abort_unless($invoice->user_id === $manager->id && $lease
                && $invoice->context === Invoice::CONTEXT_TENANT && $invoice->billable_type === $lease->tenant?->getMorphClass()
                && $invoice->billable_id === $lease->tenant_id
                && app(LeaseManagementResolver::class)->resolve($lease)?->id === $manager->id, 403);
            if ($invoice->amount_paid > 0) Validator::make($input, ['acknowledge_wallet_credit' => ['accepted']])->validate();
            $refunds = app(RefundService::class)->assertInvoiceCreationAllowed($lease,
                Gate::any(['super-admin', 'leases.create refund adjustment']));
            $completed = $refunds->first(fn ($r) => $r->status === \App\Enums\RefundStatus::PAID);
            $items = $invoice->items()->orderBy('id')->get();
            $total = $items->sum('amount') - $item->amount + $proposal->replacement_snapshot['final_amount'];
            if ($total < 0 || $total > 4294967295) throw ValidationException::withMessages(['amount' => 'The replacement invoice total is outside the supported range.']);
            app(InvoiceService::class)->voidInvoice($invoice, 'Utility correction '.$proposal->id.': '.$proposal->reason);
            do { $number = app(DocumentSequenceService::class)->generateInvoiceNumber($manager); }
            while (Invoice::withTrashed()->where('invoice_no', $number)->exists());
            $replacement = Invoice::create(['context' => $invoice->context, 'billable_type' => $invoice->billable_type,
                'billable_id' => $invoice->billable_id, 'lease_id' => $lease->id, 'user_id' => $manager->id,
                'document_template_id' => $invoice->document_template_id, 'invoice_no' => $number, 'type' => 'adjustment',
                'period' => $invoice->period, 'due_date' => today()->addDays(7), 'total_amount' => $total,
                'amount_paid' => 0, 'amount_balance' => $total, 'status' => $total === 0 ? 'paid' : 'unpaid',
                'remarks' => 'Replaces '.$invoice->invoice_no.'. '.$proposal->reason,
                'settlement_refund_id' => $completed?->id, 'adjustment_reason' => $proposal->reason]);
            foreach ($items as $old) {
                $new = $replacement->items()->create(['fee_type_id' => $old->fee_type_id,
                    'lease_charge_id' => $old->lease_charge_id,
                    'description' => $old->description, 'amount' => $old->id === $item->id ? $proposal->replacement_snapshot['final_amount'] : $old->amount]);
                if ($old->id === $item->id) {
                    $proposal->update(['replacement_invoice_item_id' => $new->id, 'status' => 'issued',
                        'replacement_snapshot' => $proposal->replacement_snapshot + ['confirmed_by' => Auth::id(), 'confirmed_at' => now()->toIso8601String()]]);
                    continue;
                }
                // Preserve the current-invoice link for other water items copied unchanged.
                $prior = UtilityCorrection::where('replacement_invoice_item_id', $old->id)->where('status', 'issued')->first();
                $otherAllocation = UtilityAllocation::where('invoice_item_id', $old->id)->first();
                $otherReading = $prior ? Utility::find($prior->utility_id) : ($otherAllocation ? Utility::find($otherAllocation->utility_id) : Utility::where('invoice_item_id', $old->id)->first());
                if ($otherReading) UtilityCorrection::create(['management_user_id' => $manager->id, 'utility_id' => $otherReading->id,
                    'utility_allocation_id' => $prior?->utility_allocation_id ?? $otherAllocation?->id,
                    'original_invoice_item_id' => $old->id, 'replacement_invoice_item_id' => $new->id, 'created_by' => Auth::id(),
                    'original_snapshot' => ['amount' => $old->amount], 'replacement_snapshot' => ['final_amount' => $new->amount, 'method' => 'unchanged_invoice_carry_forward'],
                    'difference' => 0, 'reason' => 'Carried unchanged during correction '.$proposal->id, 'status' => 'issued']);
            }
            app(InvoiceService::class)->applyWalletToNewInvoice($replacement, $manager);
            foreach ($refunds as $refund) app(RefundService::class)->refreshSettlement($refund);
            return $replacement;
        }, 3);
    }
}
