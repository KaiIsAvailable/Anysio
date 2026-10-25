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
            $qty = isset($item['qty']) ? (int) $item['qty'] : 1;
            $sellPrice = (float) ($item['sell_price'] ?? ($item['amount'] ?? 0));
            
            // Calculate total cents for this specific row (qty * unit price * 100)
            $amountCents = isset($item['amount_cents']) 
                ? (int) $item['amount_cents'] 
                : (int) round($qty * $sellPrice * 100);

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
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string',
            'reference' => 'nullable|string',
        ]);

        $refund = Refund::findOrFail($validated['refund_id']);
        $effectiveUser = get_effective_user();

        $disbursementData = [
            'amount_cents' => (int)($validated['amount'] * 100),
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'status' => 'successful',
        ];

        $this->refundService->processDisbursement($refund, $disbursementData, $effectiveUser);

        return redirect()->back()->with('success', 'Refund disbursement processed successfully.');
    }
}