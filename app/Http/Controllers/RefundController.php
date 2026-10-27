<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lease;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RefundController extends Controller
{
    public function __construct(
        protected RefundService $refundService
    ) {}

    /**
     * Save the checkout refund settlement as a draft.
     */
    public function storeDraft(Request $request)
    {
        //dd($request->all());
        $validated = $request->validate([
            'lease_id' => 'required|exists:leases,id',
            'type' => 'nullable|string',
            'reason' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'items' => 'array',
            'items.*.fee_type_id' => 'nullable|exists:fee_types,id',
            'items.*.type' => 'nullable|string',
            'items.*.description' => 'nullable|string',
            'items.*.qty' => 'nullable|numeric|min:1',
            'items.*.sell_price' => 'nullable|numeric|min:0',
            'items.*.amount_cents' => 'nullable|numeric|min:0',
            'auto_deductions' => 'array',
        ]);

        $lease = Lease::findOrFail($validated['lease_id']);

        // Process each item row in the array independently
        $items = collect($validated['items'] ?? [])->map(function ($item) {
            // 🌟 1. Ensure qty is properly parsed as an integer (default to 1)
            $qty = isset($item['qty']) ? max(1, (int) $item['qty']) : 1;
            
            // 🌟 2. Get the unit price in Ringgit
            $sellPrice = (float) ($item['sell_price'] ?? ($item['amount'] ?? 0));
            
            // 🌟 3. Calculate total cents correctly: qty * unit_price * 100
            $amountCents = (int) round($qty * $sellPrice * 100);

            return [
                'fee_type_id' => $item['fee_type_id'] ?? null,
                'type' => $item['type'] ?? 'adjustment',
                'description' => $item['description'] ?? '',
                'qty' => $qty,                         // Passes correct quantity (e.g. 5)
                'sell_price' => $sellPrice,
                'amount_cents' => $amountCents,         // Passes correct multiplied total cents (e.g. 5000)
                'lease_charge_id' => $item['lease_charge_id'] ?? null,
                'invoice_item_id' => $item['invoice_item_id'] ?? null,
            ];
        })->toArray();

        // Also include auto-deductions into items if present
        if (!empty($validated['auto_deductions'])) {
            foreach ($validated['auto_deductions'] as $autoInv) {
                $items[] = [
                    'fee_type_id' => null,
                    'type' => 'utility',
                    'description' => 'Unpaid Invoice Auto-Deduction',
                    'qty' => 1,
                    'sell_price' => (float)($autoInv['amount'] ?? 0),
                    'amount_cents' => (int)((float)($autoInv['amount'] ?? 0) * 100),
                    'invoice_item_id' => null,
                ];
            }
        }

        $refund = $this->refundService->saveAsDraft($lease, $validated, $items);

        return redirect()->back()->with('success', 'Refund settlement saved as draft successfully.');
    }

    /**
     * Submit a draft refund settlement for pending approval.
     */
    public function pending(Request $request)
    {
        $validated = $request->validate([
            'lease_id' => 'required|exists:leases,id',
            'refund_id' => 'nullable|exists:refunds,id',
            'type' => 'nullable|string',
            'reason' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'items' => 'array',
            'items.*.fee_type_id' => 'nullable|exists:fee_types,id',
            'items.*.type' => 'nullable|string',
            'items.*.description' => 'nullable|string',
            'items.*.qty' => 'nullable|numeric|min:1',
            'items.*.sell_price' => 'nullable|numeric|min:0',
            'items.*.amount_cents' => 'nullable|numeric|min:0',
            'auto_deductions' => 'array',
        ]);

        $lease = Lease::findOrFail($validated['lease_id']);

        // Process item array row-by-row
        $items = collect($validated['items'] ?? [])->map(function ($item) {
            $qty = isset($item['qty']) ? max(1, (int) $item['qty']) : 1;
            $sellPrice = (float) ($item['sell_price'] ?? ($item['amount'] ?? 0));
            $amountCents = (int) round($qty * $sellPrice * 100);

            return [
                'fee_type_id' => $item['fee_type_id'] ?? null,
                'type' => $item['type'] ?? 'adjustment',
                'description' => $item['description'] ?? '',
                'qty' => $qty,
                'sell_price' => $sellPrice,
                'amount_cents' => $amountCents,
                'lease_charge_id' => $item['lease_charge_id'] ?? null,
                'invoice_item_id' => $item['invoice_item_id'] ?? null,
            ];
        })->toArray();

        // Include auto-deductions if present
        if (!empty($validated['auto_deductions'])) {
            foreach ($validated['auto_deductions'] as $autoInv) {
                $items[] = [
                    'fee_type_id' => null,
                    'type' => 'utility',
                    'description' => 'Unpaid Invoice Auto-Deduction',
                    'qty' => 1,
                    'sell_price' => (float)($autoInv['amount'] ?? 0),
                    'amount_cents' => (int)((float)($autoInv['amount'] ?? 0) * 100),
                    'invoice_item_id' => null,
                ];
            }
        }

        // Call service method to update draft items and switch status to PENDING
        $this->refundService->submitForPending($lease, $validated, $items);

        return redirect()->back()->with('success', 'Refund settlement submitted for approval successfully.');
    }

    public function reject(Request $request)
    {
        $validated = $request->validate([
            'lease_id' => 'required|exists:leases,id',
        ]);

        $lease = Lease::findOrFail($validated['lease_id']);

        // Call your service method to change status back to draft
        $this->refundService->rejectRefund($lease);

        return redirect()->back()->with('success', 'Refund rejected and sent back to draft.');
    }

    /**
     * Admin approves the refund (on behalf of tenant or review).
     */
    public function approve(Request $request)
    {
        $validated = $request->validate([
            'refund_id' => 'required|exists:refunds,id',
        ]);

        $refund = Refund::findOrFail($validated['refund_id']);
        $effectiveUser = get_effective_user();

        // Call approve service method
        $this->refundService->approveRefund($refund, $effectiveUser);

        return redirect()->back()->with('success', 'Refund approved successfully.');
    }

    /**
     * Process payout/disbursement for an approved refund.
     */
    public function disburse(Request $request)
    {
        $validated = $request->validate([
            'refund_id' => 'required|exists:refunds,id',
            'method' => 'required|string',
            'reference' => 'nullable|string',
        ]);

        $refund = Refund::findOrFail($validated['refund_id']);
        $effectiveUser = get_effective_user();

        // Automatically convert the refund's net_amount_cents (stored as integer cents) to currency amount
        $netAmountDecimal = $refund->net_amount_cents / 100;

        $disbursementData = [
            'amount_cents' => $refund->net_amount_cents, // Use the exact calculated net cents from the database
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'status' => 'successful',
        ];

        $this->refundService->processDisbursement($refund, $disbursementData, $effectiveUser);

        return redirect()->back()->with('success', 'Refund disbursement processed successfully.');
    }
}