<?php

namespace App\Services;

use App\Models\{Invoice, FeeType};
use Illuminate\Validation\ValidationException;

class InvoicePenaltyService
{
    public function __construct(private SettingService $settings, private LeaseManagementResolver $management) {}

    /** Read-only quote. Collection must call this again while holding the parent invoice lock. */
    public function quote(Invoice $invoice, $paymentDate = null, bool $lockFeeType = false): ?array
    {
        if ($invoice->amount_balance <= 0 || $invoice->isVoid() || $invoice->type === 'penalty'
            || !$invoice->due_date || !$invoice->lease_id) return null;

        $invoice->loadMissing(['lease.leasable', 'items.feeType']);
        $manager = $invoice->lease ? $this->management->resolve($invoice->lease) : null;
        if (!$manager) {
            throw ValidationException::withMessages(['penalty' => 'The invoice management account is unavailable or ambiguous. Please verify its management relationship before recording payment.']);
        }

        $penalty = $this->settings->calculateLatePenalty(
            $manager->id, $invoice->due_date, $invoice->amount_balance / 100, $paymentDate, $invoice->items
        );
        if (($penalty['amount'] ?? 0) <= 0) return $penalty;

        // Legacy children have no reliable period coverage. Never infer it from their amount,
        // or rebill voided/deleted penalties. Normal collection settles parent + child atomically.
        if (Invoice::withTrashed()->where('parent_id', $invoice->id)->where('type', 'penalty')->exists()) {
            throw ValidationException::withMessages(['penalty' => 'This invoice already has a penalty invoice. Review the existing penalty and its covered dates before collecting another penalty. No automatic recalculation has been made.']);
        }

        // FeeTypeSeeder defines this charge as a shared system service fee.
        // Never borrow a private fee from another management account.
        $fees = FeeType::where('name', 'Late Payment Penalty')
            ->where('category', 'service')->where('is_system', true)
            ->when($lockFeeType, fn ($query) => $query->lockForUpdate())
            ->limit(2)->get();
        if ($fees->count() !== 1 || !$fees->first()->is_active
            || !$this->settings->isFeeTypeActive($fees->first(), userId: $manager->id)) {
            throw ValidationException::withMessages(['penalty' => 'Late payment penalty cannot be collected because its system fee type is missing, inactive or duplicated. Ask the system administrator to verify one active shared Late Payment Penalty fee in the Service category and enable it for this management account, then refresh the payment preview. No payment has been recorded.']);
        }
        $penalty['fee_type_id'] = $fees->first()->id;

        return $penalty;
    }
}
