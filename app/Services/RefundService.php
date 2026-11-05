<?php
namespace App\Services;

use App\Models\{Invoice, InvoiceItem, Lease, Refund, RefundItem, RefundDisbursement, FeeType};
use App\Enums\{RefundStatus, DisbursementStatus, RefundItemType};
use App\FeeTypeCategory;
use Illuminate\Support\Facades\{DB, Log, Auth};
use Illuminate\Validation\ValidationException;

class RefundService {
    public function __construct(
        protected RefundCalculationService $calcService,
        protected InvoiceService $invoiceService
    ) {}

    public function saveAsDraft(Lease $lease, array $data, array $items): Refund {
        $actualUser = Auth::user();                  
        $effectiveUser = get_effective_user();        

        return DB::transaction(function () use ($lease, $data, $items, $actualUser, $effectiveUser) {
            
            // 🌟 0. Automatically set the lease status to 'Check Out' when a refund draft starts/updates
            if (strtolower($lease->status) !== 'check out') {
                $lease->update(['status' => 'Check Out']);
            }
            $this->updateCheckoutMetadata($lease, $data);

            $depositCents = $this->getDepositCollectedCents($lease);
            $automaticItems = $this->getCurrentUnpaidInvoiceItems($lease);
            $manualDeductionCents = collect($items)
                ->filter(fn ($item) => $this->isDeductionType($item['type'] ?? null))
                ->sum(fn ($item) => (int) ($item['amount_cents'] ?? 0));
            $autoDeductionCents = collect($automaticItems)
                ->sum(fn (array $item) => $item['amount_cents']);
            $deductionCents = $autoDeductionCents + $manualDeductionCents;
            $grossCents = $depositCents;
            $netCents = max(0, $grossCents - $deductionCents);
            $items = array_merge($items, $automaticItems);

            // 2. Find existing draft or create new header...
            $refund = Refund::where('lease_id', $lease->id)
                ->where('status', RefundStatus::DRAFT)
                ->first();

            if ($refund) {
                $refund->update([
                    'type' => $data['type'] ?? $refund->type,
                    'deposit_collected_cents' => $depositCents,
                    'auto_deduction_cents' => $autoDeductionCents,
                    'manual_deduction_cents' => $manualDeductionCents,
                    'gross_amount_cents' => $grossCents,
                    'deduction_amount_cents' => $deductionCents,
                    'net_amount_cents' => $netCents,
                    'reason' => $data['reason'] ?? $refund->reason,
                    'internal_notes' => $data['internal_notes'] ?? $refund->internal_notes,
                ]);

                $refund->items()->delete();
            } else {
                $refundNumber = "REF-" . strtoupper(now()->format('Ym')) . "-" . str_pad(Refund::count() + 1, 4, '0', STR_PAD_LEFT);

                $refund = Refund::create([
                    'refund_number' => $refundNumber,
                    'lease_id' => $lease->id,
                    'tenant_id' => $lease->tenant_id,
                    'type' => $data['type'] ?? 'deposit',
                    'deposit_collected_cents' => $depositCents,
                    'auto_deduction_cents' => $autoDeductionCents,
                    'manual_deduction_cents' => $manualDeductionCents,
                    'gross_amount_cents' => $grossCents,
                    'deduction_amount_cents' => $deductionCents,
                    'net_amount_cents' => $netCents,
                    'status' => RefundStatus::DRAFT,
                    'reason' => $data['reason'] ?? 'Draft refund settlement',
                    'internal_notes' => $data['internal_notes'] ?? null,
                    'requested_by' => $actualUser?->id, 
                    'requested_at' => now(),
                ]);
            }

            // 3. Save each item row into refund_items table
            foreach ($items as $item) {
                $feeTypeId = $item['fee_type_id'] ?? null;
                $description = $item['description'] ?? '';
                
                if ($feeTypeId && empty($description)) {
                    $feeType = FeeType::find($feeTypeId);
                    $description = $feeType ? $feeType->name : 'Custom Deduction';
                }

                $qty = (int) ($item['qty'] ?? 1);
                $amountCents = (int) ($item['amount_cents'] ?? 0);
                $unitAmountCents = (int) ($item['unit_amount_cents']
                    ?? round(((float) ($item['sell_price'] ?? 0)) * 100));

                $refund->items()->create([
                    'origin' => $item['origin'] ?? 'manual',
                    'fee_type_id' => $feeTypeId,
                    'type' => $item['type'] ?? RefundItemType::ADJUSTMENT->value,
                    'description' => $description,
                    'qty' => $qty,                    
                    'unit_amount_cents' => $unitAmountCents,
                    'amount_cents' => $amountCents,   
                    'lease_charge_id' => $item['lease_charge_id'] ?? null,
                    'invoice_id' => $item['invoice_id'] ?? null,
                    'invoice_item_id' => $item['invoice_item_id'] ?? null,
                ]);
            }

            return $refund;
        });
    }

    private function isDeductionType(?string $type): bool
    {
        return in_array($type, [
            RefundItemType::DAMAGE->value,
            RefundItemType::UTILITY->value,
            RefundItemType::CLEANING->value,
            RefundItemType::ADJUSTMENT->value,
        ], true);
    }

    private function getCurrentUnpaidInvoiceItems(Lease $lease): array
    {
        return $lease->invoices()
            ->with('items')
            ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_VOID])
            ->get()
            ->map(function (Invoice $invoice): array {
                $amountCents = (int) ($invoice->amount_balance !== null
                    ? $invoice->amount_balance
                    : $invoice->total_amount);
                $invoiceItems = $invoice->items;

                return [
                    'origin' => 'automatic_invoice',
                    'fee_type_id' => null,
                    'type' => RefundItemType::UTILITY->value,
                    'description' => 'Unpaid Invoice Auto-Deduction: '
                        . ($invoice->invoice_no ?: $invoice->id),
                    'qty' => 1,
                    'unit_amount_cents' => $amountCents,
                    'amount_cents' => $amountCents,
                    'invoice_id' => $invoice->id,
                    'invoice_item_id' => $invoiceItems->count() === 1
                        ? $invoiceItems->first()->id
                        : null,
                ];
            })
            ->filter(fn (array $item) => $item['amount_cents'] > 0)
            ->values()
            ->all();
    }

    private function getDepositCollectedCents(Lease $lease): int
    {
        $charges = collect();
        $current = $lease;

        while ($current) {
            $current->load(['charges.feeType', 'charges.invoice']);
            $charges = $charges->concat($current->charges);
            $current = $current->parent_lease_id
                ? Lease::find($current->parent_lease_id)
                : null;
        }

        $depositCharges = $charges->filter(function ($charge): bool {
            $category = $charge->feeType?->category;
            $category = $category instanceof \BackedEnum ? $category->value : $category;

            return $charge->charge_type === \App\Models\LeaseCharge::TYPE_REFUNDABLE
                || $category === FeeTypeCategory::DEPOSIT->value;
        });

        $chargeIds = $depositCharges->pluck('id')->filter()->values();
        if ($chargeIds->isEmpty()) {
            return 0;
        }

        // Transaction allocations are the source of truth for the amount
        // actually paid toward each refundable deposit charge.
        $allocatedCents = (int) DB::table('transaction_allocations')
            ->join('invoice_items', 'transaction_allocations.invoice_item_id', '=', 'invoice_items.id')
            ->whereIn('invoice_items.lease_charge_id', $chargeIds)
            ->sum('transaction_allocations.amount_cents');

        if ($allocatedCents > 0) {
            return $allocatedCents;
        }

        // Legacy fallback for records created before transaction allocations
        // were available.
        $paidInvoiceChargeIds = InvoiceItem::query()
            ->whereIn('lease_charge_id', $chargeIds)
            ->whereHas('invoice', fn ($query) => $query->where('status', Invoice::STATUS_PAID))
            ->pluck('lease_charge_id')
            ->filter()
            ->unique();

        return (int) $depositCharges
            ->filter(fn ($charge) => $paidInvoiceChargeIds->contains($charge->id))
            ->sum('amount');
    }

    /**
     * Save the current draft state and transition the status to Pending.
     */
    public function submitForPending(Lease $lease, array $data, array $items): Refund 
    {
        return DB::transaction(function () use ($lease, $data, $items) {
            // 1. First save all latest items & calculate header totals (This already updates the lease status to 'Check Out')
            $refund = $this->saveAsDraft($lease, $data, $items);

            // 2. Just to be completely explicit and safe, ensure lease status is 'Check Out'
            if (strtolower($lease->status) !== 'check out') {
                $lease->update(['status' => 'Check Out']);
            }

            // 3. Lock and transition refund status to PENDING
            if ($refund->status === RefundStatus::DRAFT) {
                $refund->update([
                    'status' => RefundStatus::PENDING,
                ]);
            }

            return $refund;
        });
    }

    public function rejectRefund(Lease $lease, array $data = []): Refund
    {
        return DB::transaction(function () use ($lease, $data) {
            $this->updateCheckoutMetadata($lease, $data);
            $refund = $lease->refunds()->latest()->first();

            if ($refund && $refund->status === RefundStatus::PENDING) {
                $refund->update([
                    'status' => RefundStatus::DRAFT,
                ]);
            }

            return $refund;
        });
    }

    public function approveRefund(Refund $refund, $user, array $data = []): Refund {
        return DB::transaction(function () use ($refund, $user, $data) {
            $lockedRefund = Refund::where('id', $refund->id)->lockForUpdate()->first();

            if ($lockedRefund->status !== RefundStatus::PENDING) {
                throw ValidationException::withMessages(['status' => 'Only pending refunds can be approved.']);
            }

            $lease = $lockedRefund->lease()->lockForUpdate()->firstOrFail();
            $this->updateCheckoutMetadata($lease, $data);

            $lockedRefund->update([
                'status' => RefundStatus::APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            return $lockedRefund;
        });
    }

    public function processDisbursement(Refund $refund, array $disbursementData, $user): RefundDisbursement {
        return DB::transaction(function () use ($refund, $disbursementData, $user) {
            $lockedRefund = Refund::where('id', $refund->id)->lockForUpdate()->first();

            if ($lockedRefund->status !== RefundStatus::APPROVED) {
                throw ValidationException::withMessages(['status' => 'Refund must be approved prior to disbursement payout.']);
            }

            if ((int) $lockedRefund->net_amount_cents < 0) {
                throw ValidationException::withMessages([
                    'amount_cents' => 'The refund net amount cannot be negative before disbursement.',
                ]);
            }

            $lease = $lockedRefund->lease()->lockForUpdate()->firstOrFail();
            $this->updateCheckoutMetadata($lease, $disbursementData, true);
            $paymentDate = now()->toDateString();

            $this->invoiceService->settleOutstandingInvoicesFromDeposit(
                $lease,
                (int) $lockedRefund->deposit_collected_cents,
                $paymentDate,
                $paymentDate
            );

            $disbursement = $lockedRefund->disbursements()->create([
                'amount_cents' => $disbursementData['amount_cents'],
                'bank_detail_id' => $disbursementData['bank_detail_id'] ?? null,
                'method' => $disbursementData['method'],
                'reference' => $disbursementData['reference'] ?? null,
                'status' => $disbursementData['status'] ?? DisbursementStatus::SUCCESSFUL->value,
                'processed_at' => now(),
            ]);

            if ($disbursement->status === DisbursementStatus::SUCCESSFUL) {
                $totalPaid = $lockedRefund->disbursements()
                    ->where('status', DisbursementStatus::SUCCESSFUL->value)
                    ->sum('amount_cents');

                if ($totalPaid >= $lockedRefund->net_amount_cents) {
                    $lockedRefund->update([
                        'status' => RefundStatus::PAID,
                        'paid_by' => $user->id,
                        'paid_at' => now(),
                    ]);

                    $lease->update([
                        'status' => 'refunded',
                    ]);

                    if ($lease->leasable) {
                        $lease->leasable->propagateStatus('maintenance');
                        $lease->leasable->update([
                            'status' => 'maintenance',
                        ]);
                    }
                }
            }

            return $disbursement;
        });
    }

    private function updateCheckoutMetadata(Lease $lease, array $data, bool $required = false): void
    {
        $checkedOutAt = $data['checked_out_at'] ?? null;
        $refundDocumentId = $data['refund_document_id'] ?? null;

        if ($required && (!$checkedOutAt || !$refundDocumentId)) {
            $errors = [];

            if (!$checkedOutAt) {
                $errors['checked_out_at'] = 'A checkout date is required before disbursement.';
            }

            if (!$refundDocumentId) {
                $errors['refund_document_id'] = 'A refund document template is required before disbursement.';
            }

            throw ValidationException::withMessages($errors);
        }

        $updates = [];

        if ($checkedOutAt !== null && $checkedOutAt !== '') {
            $updates['checked_out_at'] = $checkedOutAt;
        }

        if ($refundDocumentId !== null && $refundDocumentId !== '') {
            $updates['refund_document_id'] = $refundDocumentId;
        }

        if ($updates) {
            $updates['status'] = 'Check Out';
            $lease->update($updates);
        }
    }
}