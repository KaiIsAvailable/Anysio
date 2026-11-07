<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lease;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

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
        if (Gate::denies('super-admin') && Gate::denies('leases.save draft')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }
        //dd($request->all());
        $validated = $request->validate([
            'lease_id' => 'required|exists:leases,id',
            ...$this->checkoutMetadataRules(),
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

        if (!$this->authorizeDeductionChanges($lease, $items)) {
            return redirect()->back()->with('error', 'You have no permission.');
        }
        $refund = $this->refundService->saveAsDraft($lease, $validated, $items);

        return redirect()->back()->with('success', 'Refund settlement saved as draft successfully.');
    }

    /**
     * Submit a draft refund settlement for pending approval.
     */
    public function pending(Request $request)
    {
        if (Gate::denies('super-admin') && Gate::denies('leases.set as pending')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }
        $validated = $request->validate([
            'lease_id' => 'required|exists:leases,id',
            'refund_id' => 'nullable|exists:refunds,id',
            ...$this->checkoutMetadataRules(),
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

        // Call service method to update draft items and switch status to PENDING
        if (!$this->authorizeDeductionChanges($lease, $items)) {
            return redirect()->back()->with('error', 'You have no permission.');
        }
        $this->refundService->submitForPending($lease, $validated, $items);

        return redirect()->back()->with('success', 'Refund settlement submitted for approval successfully.');
    }

    public function reject(Request $request)
    {
        if (Gate::denies('super-admin') && Gate::denies('leases.reject refund')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }
        $validated = $request->validate([
            'lease_id' => 'required|exists:leases,id',
            ...$this->checkoutMetadataRules(),
        ]);

        $lease = Lease::findOrFail($validated['lease_id']);

        // Call your service method to change status back to draft
        $this->refundService->rejectRefund($lease, $validated);

        return redirect()->back()->with('success', 'Refund rejected and sent back to draft.');
    }

    /**
     * Admin approves the refund (on behalf of tenant or review).
     */
    public function approve(Request $request)
    {
        if (Gate::denies('super-admin') && Gate::denies('leases.approve refund')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }
        $validated = $request->validate([
            'refund_id' => 'required|exists:refunds,id',
            ...$this->checkoutMetadataRules(),
        ]);

        $refund = Refund::findOrFail($validated['refund_id']);
        $effectiveUser = get_effective_user();

        // Call approve service method
        $this->refundService->approveRefund($refund, $effectiveUser, $validated);

        return redirect()->back()->with('success', 'Refund approved successfully.');
    }

    /**
     * Process payout/disbursement for an approved refund.
     */
    public function disburse(Request $request)
    {
        if (Gate::denies('super-admin') && Gate::denies('leases.proceed refund')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }
        $validated = $request->validate([
            'refund_id' => 'required|exists:refunds,id',
            'checked_out_at' => ['required', 'date'],
            'refund_document_id' => [
                'required',
                Rule::exists('document_templates', 'id')->where(
                    fn ($query) => $query
                        ->where('category', 'refund')
                        ->where('status', 'active')
                ),
            ],
            'bank_detail_id' => 'required|exists:bank_details,id',
            'method' => 'required|string',
            'reference' => 'nullable|string',
        ]);

        $refund = Refund::findOrFail($validated['refund_id']);
        $refund->loadMissing('tenant.user');
        $bankBelongsToTenant = $refund->tenant?->user?->bankDetails()
            ->whereKey($validated['bank_detail_id'])
            ->exists();

        if (!$bankBelongsToTenant) {
            throw ValidationException::withMessages([
                'bank_detail_id' => 'The selected bank account does not belong to this tenant.',
            ]);
        }

        $effectiveUser = get_effective_user();

        // Automatically convert the refund's net_amount_cents (stored as integer cents) to currency amount
        $netAmountDecimal = $refund->net_amount_cents / 100;

        $disbursementData = [
            'amount_cents' => $refund->net_amount_cents, // Use the exact calculated net cents from the database
            'checked_out_at' => $validated['checked_out_at'],
            'refund_document_id' => $validated['refund_document_id'],
            'bank_detail_id' => $validated['bank_detail_id'],
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'status' => 'successful',
        ];

        $this->refundService->processDisbursement($refund, $disbursementData, $effectiveUser);

        return redirect()->back()->with('success', 'Refund disbursement processed successfully.');
    }

    private function authorizeDeductionChanges(Lease $lease, array $items): bool
    {
        if (Gate::allows('super-admin') || Gate::allows('leases.add deduction item')) {
            return true;
        }

        // Saving or submitting unchanged deductions does not require permission to edit them.
        $draft = $lease->refunds()->where('status', 'draft')->with('items')->first();
        $existingItems = $draft?->items->reject(fn ($item) => $item->origin === 'automatic_invoice'
            || ($item->type?->value === 'utility'
                && str_starts_with((string) $item->description, 'Unpaid Invoice Auto-Deduction:'))) ?? collect();
        $normalize = fn ($rows) => collect($rows)->map(fn ($item) => [
            'fee_type_id' => (string) ($item['fee_type_id'] ?? ''),
            'type' => ($item['type'] ?? null) instanceof \BackedEnum ? $item['type']->value : ($item['type'] ?? 'adjustment'),
            'qty' => (int) ($item['qty'] ?? 1),
            'amount_cents' => (int) ($item['amount_cents'] ?? 0),
        ])->sortBy(fn ($item) => json_encode($item))->values()->all();

        return $normalize($items) === $normalize($existingItems);
    }

    private function checkoutMetadataRules(): array
    {
        return [
            'checked_out_at' => ['nullable', 'date'],
            'refund_document_id' => [
                'nullable',
                Rule::exists('document_templates', 'id')->where(
                    fn ($query) => $query
                        ->where('category', 'refund')
                        ->where('status', 'active')
                ),
            ],
        ];
    }
}
