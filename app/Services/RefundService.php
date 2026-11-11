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

    public function settlementFingerprint(Refund $refund): string
    {
        $fields = ['status', 'deposit_collected_cents', 'wallet_refund_cents', 'gross_amount_cents',
            'auto_deduction_cents', 'manual_deduction_cents', 'deduction_amount_cents', 'net_amount_cents'];
        $values = [];
        foreach ($fields as $field) {
            $value = $refund->$field;
            $values[$field] = $value instanceof \BackedEnum ? $value->value : $value;
        }
        $values['items'] = $refund->items()->orderBy('id')->get()
            ->map(fn ($item) => $item->only(['id', 'invoice_id', 'fee_type_id', 'type', 'qty', 'amount_cents']))->all();
        return hash('sha256', json_encode($values));
    }

    private function checkReviewedSettlement(Refund $refund, array $data): void
    {
        if (isset($data['settlement_fingerprint']) && !hash_equals($this->settlementFingerprint($refund), $data['settlement_fingerprint'])) {
            throw ValidationException::withMessages(['refund' => 'The settlement changed since you opened it. Reopen the refund, review the latest figures and try again.']);
        }
    }

    public function getWalletRefundCents(Lease $lease, ?string $refundId = null, bool $lock = false): int
    {
        $userId = $lease->tenant?->user_id;
        return $userId ? app(WalletService::class)->refundableBalance($userId, $refundId, $lock) : 0;
    }

    public function assertInvoicePaymentAllowed(Invoice $invoice, bool $onlyIncludedInvoices = false): \Illuminate\Database\Eloquent\Collection
    {
        $refunds = $this->lockActiveForInvoice($invoice);
        if ($refunds->contains(fn ($refund) => $refund->status === RefundStatus::PROCESSING || ($refund->status === RefundStatus::APPROVED
            && (!$onlyIncludedInvoices || $refund->items()->where('invoice_id', $invoice->id)->exists())))) {
            throw ValidationException::withMessages([
                'refund' => 'This invoice is approved for deduction in the refund and cannot be paid separately.',
            ]);
        }
        return $refunds;
    }

    /** Lock affected active settlements before changing an invoice balance. */
    public function lockActiveForInvoice(Invoice $invoice, bool $includeCompleted = false): \Illuminate\Database\Eloquent\Collection
    {
        if (!$invoice->lease_id) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        // A renewed lease can hold deposits collected on an earlier lease.
        $leaseIds = [(string) $invoice->lease_id];
        $frontier = $leaseIds;
        while ($frontier) {
            $frontier = Lease::whereIn('parent_lease_id', $frontier)
                ->whereNotIn('id', $leaseIds)->pluck('id')->all();
            $leaseIds = array_merge($leaseIds, $frontier);
        }

        $statuses = [RefundStatus::DRAFT, RefundStatus::PENDING, RefundStatus::APPROVED, RefundStatus::PROCESSING];
        if ($includeCompleted) $statuses[] = RefundStatus::PAID;
        return Refund::whereIn('lease_id', $leaseIds)
            ->whereIn('status', $statuses)
            ->orderBy('id')->lockForUpdate()->get();
    }

    public function assertInvoiceCreationAllowed(Lease $lease, bool $adjustment = false): \Illuminate\Database\Eloquent\Collection
    {
        $refunds = $this->lockActiveForInvoice(new Invoice(['lease_id' => $lease->id]), true);
        foreach ($refunds as $refund) {
            $message = match ($refund->status) {
                RefundStatus::PENDING => 'Return the refund to Draft before adding invoices.',
                RefundStatus::APPROVED => 'Manual invoices cannot be added after the checkout refund has been approved. Revoke approval first.',
                RefundStatus::PROCESSING => 'The refund payout is processing. Financial changes are locked.',
                RefundStatus::PAID => $adjustment ? null : 'This refund is complete. Add a linked post-refund adjustment instead of changing the completed settlement.',
                default => null,
            };
            if ($message) throw ValidationException::withMessages(['refund' => $message]);
        }
        return $refunds;
    }

    public function assertInvoiceVoidAllowed(Invoice $invoice): \Illuminate\Database\Eloquent\Collection
    {
        $refunds = $this->lockActiveForInvoice($invoice, true);
        foreach ($refunds as $refund) {
            $message = match ($refund->status) {
                RefundStatus::PENDING => 'Return the refund to Draft before voiding an invoice.',
                RefundStatus::APPROVED => 'Revoke refund approval before voiding an invoice.',
                RefundStatus::PROCESSING => 'The refund payout is processing. Financial changes are locked.',
                RefundStatus::PAID => $invoice->settlement_refund_id === $refund->id ? null
                    : 'This invoice belongs to a completed refund and cannot be voided. Record an approved adjustment instead.',
                default => null,
            };
            if ($message) throw ValidationException::withMessages(['refund' => $message]);
        }
        return $refunds;
    }

    /** Refresh invoice deductions without changing any manually entered items. */
    public function refreshSettlement(Refund $refund): bool
    {
        return DB::transaction(function () use ($refund) {
            $refund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if (!in_array($refund->status, [RefundStatus::DRAFT, RefundStatus::PENDING, RefundStatus::APPROVED], true)) {
                return false;
            }

            $lease = $refund->lease()->firstOrFail();
            $items = $refund->items()->get();
            $automatic = $items->filter(fn ($item) => $item->origin === 'automatic_invoice'
                || ($item->type === RefundItemType::UTILITY
                    && str_starts_with((string) $item->description, 'Unpaid Invoice Auto-Deduction:')));
            $manual = $items->reject(fn ($item) => $automatic->contains('id', $item->id));
            $currentItems = $this->getCurrentUnpaidInvoiceItems($lease);
            $deposit = $this->getDepositCollectedCents($lease);
            $walletCents = $this->getWalletRefundCents($lease, $refund->id, true);
            $autoTotal = array_sum(array_column($currentItems, 'amount_cents'));
            $manualTotal = (int) $manual->filter(fn ($item) => $this->isDeductionType($item->type->value))
                ->sum('amount_cents');
            $totals = [
                'deposit_collected_cents' => $deposit,
                'wallet_refund_cents' => $walletCents,
                'gross_amount_cents' => $deposit + $walletCents,
                'auto_deduction_cents' => $autoTotal,
                'manual_deduction_cents' => $manualTotal,
                'deduction_amount_cents' => $autoTotal + $manualTotal,
                'net_amount_cents' => max(0, $deposit + $walletCents - $autoTotal - $manualTotal),
            ];
            $signature = fn ($rows) => collect($rows)->map(fn ($item) => [
                'invoice_id' => $item['invoice_id'] ?? null,
                'invoice_item_id' => $item['invoice_item_id'] ?? null,
                'amount_cents' => (int) ($item['amount_cents'] ?? 0),
            ])->sortBy(fn ($item) => json_encode($item))->values()->all();
            $itemsChanged = $signature($automatic->toArray()) !== $signature($currentItems);
            $refund->fill($totals);
            $changed = $itemsChanged || $refund->isDirty(array_keys($totals));
            if (!$changed) {
                return false;
            }

            if ($itemsChanged) {
                $refund->items()->whereIn('id', $automatic->modelKeys())->delete();
                foreach ($currentItems as $item) {
                    $refund->items()->create($item);
                }
            }
            if ($refund->status === RefundStatus::APPROVED) {
                if ($lease->tenant?->user_id) {
                    app(WalletService::class)->releaseRefund($lease->tenant->user_id, $refund->id);
                }
                $refund->fill(['status' => RefundStatus::PENDING, 'approved_by' => null, 'approved_at' => null]);
            }
            $refund->save();

            return true;
        });
    }

    public function saveAsDraft(Lease $lease, array $data, array $items): Refund {
        $actualUser = Auth::user();                  
        $effectiveUser = get_effective_user();        

        return DB::transaction(function () use ($lease, $data, $items, $actualUser, $effectiveUser) {
            $lease = Lease::whereKey($lease->id)->lockForUpdate()->firstOrFail();
            $refund = Refund::where('lease_id', $lease->id)
                ->latest('created_at')->lockForUpdate()->first();
            if ($refund && $refund->status !== RefundStatus::DRAFT) {
                throw ValidationException::withMessages(['refund' => 'Only a Draft refund can be edited or submitted. Return it to Draft first; completed refunds cannot be reopened.']);
            }
            
            // 🌟 0. Automatically set the lease status to 'Check Out' when a refund draft starts/updates
            if (strtolower($lease->status) !== 'check out' || $lease->is_pending_renewal) {
                $lease->update(['status' => 'Check Out', 'is_pending_renewal' => false]);
            }
            $this->updateCheckoutMetadata($lease, $data);

            $depositCents = $this->getDepositCollectedCents($lease);
            $walletCents = $this->getWalletRefundCents($lease, $refund?->id, true);
            $automaticItems = $this->getCurrentUnpaidInvoiceItems($lease);
            $manualDeductionCents = collect($items)
                ->filter(fn ($item) => $this->isDeductionType($item['type'] ?? null))
                ->sum(fn ($item) => (int) ($item['amount_cents'] ?? 0));
            $autoDeductionCents = collect($automaticItems)
                ->sum(fn (array $item) => $item['amount_cents']);
            $deductionCents = $autoDeductionCents + $manualDeductionCents;
            $grossCents = $depositCents + $walletCents;
            $netCents = max(0, $grossCents - $deductionCents);
            $items = array_merge($items, $automaticItems);

            // 2. Find existing draft or create new header...
            if ($refund) {
                $refund->update([
                    'type' => $data['type'] ?? $refund->type,
                    'deposit_collected_cents' => $depositCents,
                    'wallet_refund_cents' => $walletCents,
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
                    'wallet_refund_cents' => $walletCents,
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
        }, 3);
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
            ->lockForUpdate()
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

    public function getDepositCollectedCents(Lease $lease): int
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
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', '!=', Invoice::STATUS_VOID)
            ->whereNull('invoices.deleted_at')
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
            if (strtolower($lease->status) !== 'check out' || $lease->is_pending_renewal) {
                $lease->update(['status' => 'Check Out', 'is_pending_renewal' => false]);
            }

            // 3. Lock and transition refund status to PENDING
            if ($refund->status === RefundStatus::DRAFT) {
                $refund->update([
                    'status' => RefundStatus::PENDING,
                ]);
            }

            return $refund;
        }, 3);
    }

    public function rejectRefund(Lease $lease, array $data = []): Refund
    {
        return DB::transaction(function () use ($lease, $data) {
            $refund = $lease->refunds()->latest()->lockForUpdate()->first();
            if (!$refund || $refund->status !== RefundStatus::PENDING) {
                throw ValidationException::withMessages(['refund' => 'Only a pending refund can be returned to Draft.']);
            }
            $this->returnToDraft($refund, (string) ($data['transition_reason'] ?? ''), 'Returned to Draft');
            $this->updateCheckoutMetadata($lease, $data);
            return $refund;
        });
    }

    public function revokeApproval(Refund $refund, string $reason): Refund
    {
        return DB::transaction(function () use ($refund, $reason) {
            $refund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status !== RefundStatus::APPROVED) {
                throw ValidationException::withMessages(['refund' => 'Only an approved, unpaid refund can have approval revoked.']);
            }
            $this->returnToDraft($refund, $reason, 'Approval revoked');
            return $refund;
        });
    }

    private function returnToDraft(Refund $refund, string $reason, string $action): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['transition_reason' => 'Enter a reason for returning or revoking the refund.']);
        }
        $lease = $refund->lease;
        if ($lease?->tenant?->user_id) app(WalletService::class)->releaseRefund($lease->tenant->user_id, $refund->id);
        $note = sprintf('[%s] %s by user %s: %s', now()->toDateTimeString(), $action, Auth::id() ?? 'system', trim($reason));
        $refund->update([
            'status' => RefundStatus::DRAFT, 'approved_by' => null, 'approved_at' => null,
            'internal_notes' => trim(($refund->internal_notes ?? '')."\n".$note),
        ]);
    }

    public function approveRefund(Refund $refund, $user, array $data = []): Refund {
        $approved = DB::transaction(function () use ($refund, $user, $data) {
            $lockedRefund = Refund::where('id', $refund->id)->lockForUpdate()->first();

            if ($lockedRefund->status !== RefundStatus::PENDING) {
                throw ValidationException::withMessages(['status' => 'Only pending refunds can be approved.']);
            }

            if ($this->refreshSettlement($lockedRefund)) {
                return null;
            }
            $this->checkReviewedSettlement($lockedRefund, $data);

            $lease = $lockedRefund->lease()->lockForUpdate()->firstOrFail();
            $this->updateCheckoutMetadata($lease, $data);

            $lockedRefund->update([
                'status' => RefundStatus::APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);
            if ($lockedRefund->wallet_refund_cents > 0) {
                app(WalletService::class)->reserveForRefund($lease->tenant->user_id, $lockedRefund->id, $lockedRefund->wallet_refund_cents);
            }

            return $lockedRefund;
        }, 3);
        if (!$approved) {
            throw ValidationException::withMessages(['status' => 'Invoice or wallet amounts changed the settlement. Review the updated amounts and approve again.']);
        }
        return $approved;
    }

    public function processDisbursement(Refund $refund, array $disbursementData, $user): RefundDisbursement {
        $disbursement = DB::transaction(function () use ($refund, $disbursementData, $user) {
            $lockedRefund = Refund::where('id', $refund->id)->lockForUpdate()->first();

            if ($lockedRefund->status !== RefundStatus::APPROVED) {
                throw ValidationException::withMessages(['status' => 'Refund must be approved prior to disbursement payout.']);
            }
            $payoutStatus = $disbursementData['status'] ?? DisbursementStatus::SUCCESSFUL->value;
            $payoutStatus = $payoutStatus instanceof \BackedEnum ? $payoutStatus->value : $payoutStatus;
            if ($payoutStatus !== DisbursementStatus::SUCCESSFUL->value) {
                throw ValidationException::withMessages(['refund' => 'Only a confirmed successful payout can finalize the settlement. No funds were moved.']);
            }

            if ($this->refreshSettlement($lockedRefund)) {
                return null;
            }
            $this->checkReviewedSettlement($lockedRefund, $disbursementData);

            if ((int) $lockedRefund->net_amount_cents < 0) {
                throw ValidationException::withMessages([
                    'amount_cents' => 'The refund net amount cannot be negative before disbursement.',
                ]);
            }

            $lease = $lockedRefund->lease()->lockForUpdate()->firstOrFail();
            $this->updateCheckoutMetadata($lease, $disbursementData, true);
            $paymentDate = now()->toDateString();
            $lockedRefund->update(['status' => RefundStatus::PROCESSING]);
            if ($lockedRefund->wallet_refund_cents > 0) {
                app(WalletService::class)->settleRefund($lease->tenant->user_id, $lockedRefund->id, $lockedRefund->wallet_refund_cents);
            }

            $this->invoiceService->settleOutstandingInvoicesFromDeposit(
                $lease,
                (int) $lockedRefund->deposit_collected_cents,
                $paymentDate,
                $paymentDate,
                (int) $lockedRefund->wallet_refund_cents
            );

            $disbursement = $lockedRefund->disbursements()->create([
                'amount_cents' => $lockedRefund->net_amount_cents,
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
                        'status' => 'Check Out',
                        'is_pending_renewal' => false,
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
        }, 3);
        if (!$disbursement) {
            throw ValidationException::withMessages(['status' => 'Invoice or wallet amounts changed the settlement. It has been returned for approval; review the updated amounts before payment.']);
        }
        return $disbursement;
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

        if ($updates || (strtolower((string) $lease->status) === 'check out' && $lease->is_pending_renewal)) {
            $updates['status'] = 'Check Out';
            $updates['is_pending_renewal'] = false;
            $lease->update($updates);
        }
    }
}
