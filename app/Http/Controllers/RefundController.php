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
            return $this->actionError($request, 'You have no permission.');
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
            return $this->actionError($request, 'You have no permission.');
        }
        return $this->runAction(fn () => $this->refundService->saveAsDraft($lease, $validated, $items), 'Refund settlement saved as draft successfully.', $request);
    }

    /**
     * Submit a draft refund settlement for pending approval.
     */
    public function pending(Request $request)
    {
        if (Gate::denies('super-admin') && Gate::denies('leases.set as pending')) {
            return $this->actionError($request, 'You have no permission.');
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
            return $this->actionError($request, 'You have no permission.');
        }
        return $this->runAction(fn () => $this->refundService->submitForPending($lease, $validated, $items), 'Refund settlement submitted for approval successfully.', $request);
    }

    public function reject(Request $request)
    {
        if (Gate::denies('super-admin') && Gate::denies('leases.reject refund')) {
            return $this->actionError($request, 'You have no permission.');
        }
        if ($request->filled('lease_id') && Refund::where('lease_id', $request->input('lease_id'))->latest()->first()?->status !== \App\Enums\RefundStatus::PENDING) {
            return $this->actionError($request, 'Only a pending refund can be returned to Draft.');
        }
        if (!$request->filled('transition_reason')) return $this->actionError($request, 'Enter a reason for returning the refund to Draft.');
        $validated = $request->validate([
            'lease_id' => 'required|exists:leases,id',
            'transition_reason' => 'required|string|max:1000',
            ...$this->checkoutMetadataRules(),
        ]);

        $lease = Lease::findOrFail($validated['lease_id']);

        // Call your service method to change status back to draft
        return $this->runAction(fn () => $this->refundService->rejectRefund($lease, $validated), 'Refund returned to Draft.', $request);
    }

    public function revokeApproval(Request $request)
    {
        if (Gate::denies('super-admin') && Gate::denies('leases.revoke refund approval')) {
            return $this->actionError($request, 'You have no permission.');
        }
        if (!$request->filled('refund_id')) return $this->actionError($request, 'There is no approved refund to revoke.');
        if (Refund::find($request->input('refund_id'))?->status !== \App\Enums\RefundStatus::APPROVED) return $this->actionError($request, 'Only an approved, unpaid refund can have approval revoked.');
        if (!$request->filled('transition_reason')) return $this->actionError($request, 'Enter a reason for revoking refund approval.');
        $validated = $request->validate([
            'refund_id' => 'required|exists:refunds,id',
            'transition_reason' => 'required|string|max:1000',
        ]);
        return $this->runAction(fn () => $this->refundService->revokeApproval(
            Refund::findOrFail($validated['refund_id']), $validated['transition_reason']
        ), 'Refund approval revoked. The settlement is back in Draft.', $request);
    }

    /**
     * Admin approves the refund (on behalf of tenant or review).
     */
    public function approve(Request $request)
    {
        if (Gate::denies('super-admin') && Gate::denies('leases.approve refund')) {
            return $this->actionError($request, 'You have no permission.');
        }
        if (!$request->filled('refund_id')) return $this->actionError($request, 'Save and submit the refund as Pending before approving it.');
        $validated = $request->validate([
            'refund_id' => 'required|exists:refunds,id',
            'settlement_fingerprint' => 'required|string|size:64',
            ...$this->checkoutMetadataRules(),
        ]);

        $refund = Refund::findOrFail($validated['refund_id']);
        $effectiveUser = get_effective_user();

        // Call approve service method
        return $this->runAction(fn () => $this->refundService->approveRefund($refund, $effectiveUser, $validated), 'Refund approved successfully.', $request);
    }

    /**
     * Process payout/disbursement for an approved refund.
     */
    public function disburse(Request $request)
    {
        if (Gate::denies('super-admin') && Gate::denies('leases.proceed refund')) {
            return $this->actionError($request, 'You have no permission.');
        }
        if (!$request->filled('refund_id')) return $this->actionError($request, 'Approve a refund before proceeding to payout.');
        $refund = Refund::findOrFail($request->input('refund_id'));
        if ($refund->status !== \App\Enums\RefundStatus::APPROVED) return $this->actionError($request, 'Only an approved, unpaid refund can proceed to payout.');
        $hasPayout = $refund->net_amount_cents > 0;
        $validated = $request->validate([
            'refund_id' => 'required|exists:refunds,id',
            'settlement_fingerprint' => 'required|string|size:64',
            'checked_out_at' => ['required', 'date'],
            'refund_document_id' => [
                'required',
                Rule::exists('document_templates', 'id')->where(
                    fn ($query) => $query
                        ->where('category', 'refund')
                        ->where('status', 'active')
                ),
            ],
            'bank_detail_id' => ($hasPayout ? 'required' : 'nullable').'|exists:bank_details,id',
            'method' => $hasPayout ? 'required|string' : 'nullable|string',
            'reference' => 'nullable|string',
        ]);

        $refund = Refund::findOrFail($validated['refund_id']);
        $refund->loadMissing('tenant.user');
        $bankBelongsToTenant = !$hasPayout || $refund->tenant?->user?->bankDetails()
            ->whereKey($validated['bank_detail_id'] ?? null)
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
            'settlement_fingerprint' => $validated['settlement_fingerprint'],
            'amount_cents' => $refund->net_amount_cents, // Use the exact calculated net cents from the database
            'checked_out_at' => $validated['checked_out_at'],
            'refund_document_id' => $validated['refund_document_id'],
            'bank_detail_id' => $hasPayout ? $validated['bank_detail_id'] : null,
            'method' => $hasPayout ? $validated['method'] : 'settlement',
            'reference' => $validated['reference'] ?? null,
            'status' => 'successful',
        ];

        return $this->runAction(fn () => $this->refundService->processDisbursement($refund, $disbursementData, $effectiveUser), 'Refund disbursement processed successfully.', $request);
    }

    private function runAction(callable $action, string $message, ?Request $request = null)
    {
        $request ??= request();
        try {
            $action();
            if ($request->expectsJson()) {
                session()->forget('error');
                session()->flash('success', $message);
                return response()->json(['success' => true, 'message' => $message]);
            }
            return back()->with('success', $message);
        } catch (ValidationException $exception) {
            return $this->actionError($request, implode(' ', \Illuminate\Support\Arr::flatten($exception->errors())), $exception->errors());
        }
    }

    private function actionError(Request $request, string $message, array $errors = [])
    {
        session()->forget(['success', 'status']);
        session()->flash('error', $message);
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message, 'errors' => $errors,
                'session_status' => ['type' => 'error', 'message' => $message]],
                $message === 'You have no permission.' ? 403 : 422);
        }
        return back()->withErrors($errors)->withInput()->with('error', $message);
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
