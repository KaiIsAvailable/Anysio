<?php
namespace App\Services;

use App\Models\Lease;
use App\Models\Refund;
use App\Enums\RefundStatus;
use Illuminate\Support\Facades\DB;

class RefundCalculationService {
    /**
     * Calculate the maximum refundable amount in cents for a given lease.
     * 
     * Formula: Paid Refundable Allocations - Reserved/Completed Refunds - Approved Deductions
     */
    public function getMaximumRefundableAmountCents(Lease $lease): int {
        // 1. Total paid amount allocated to refundable items (e.g., security/utility deposits)
        $paidRefundableAllocationsCents = DB::table('transaction_allocations')
            ->join('invoice_items', 'transaction_allocations.invoice_item_id', '=', 'invoice_items.id')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.lease_id', $lease->id)
            ->where('invoice_items.is_refundable', true) // Flag indicating deposit or refundable charge
            ->sum('transaction_allocations.amount_cents');

        // 2. Sum of existing refunds that are approved, paid, pending, or draft
        $existingRefundsCents = Refund::where('lease_id', $lease->id)
            ->whereIn('status', [
                RefundStatus::DRAFT->value,
                RefundStatus::PENDING->value,
                RefundStatus::APPROVED->value,
                RefundStatus::PAID->value,
            ])
            ->sum('net_amount_cents');

        // 3. Maximum refundable limit
        $maxRefundable = $paidRefundableAllocationsCents - $existingRefundsCents;

        return max(0, (int) $maxRefundable);
    }
}