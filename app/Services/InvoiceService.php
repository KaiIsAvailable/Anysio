<?php

namespace App\Services;

use App\Models\{Invoice, Lease, FeeType, User, DocumentTemplate};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class InvoiceService
{
    public function __construct(
        private readonly PaymentProcessor $paymentProcessor,
        private readonly WalletService $walletService,
        private readonly DocumentSequenceService $documentSequenceService,
    ) {}

    /**
     * Owner manually creates an invoice containing multiple items after the lease exists.
     */
    public function createManualInvoice(
            Lease $lease,
            array $data
        ): Invoice {
            return DB::transaction(function () use ($lease, $data) {
                if (empty($data['items'])) {
                    throw new \InvalidArgumentException('Invoice must contain at least one item.');
                }

                // Use the currently authenticated user instead of the lease owner
                $currentUser = Auth::user(); 

                if (!$currentUser) {
                    throw new \RuntimeException('Authenticated user required to generate invoices.');
                }

                $template = DocumentTemplate::where('user_id', $currentUser->id)
                    ->where('category', 'invoice')
                    ->where('status', 'active')
                    ->first();

                // Pass the current user to sequence generation and item validation
                ['validated_items' => $items, 'total_cents' => $totalCents] = $this->processAndValidateItems($currentUser, $data['items']);

                $invoiceNo = $this->documentSequenceService->generateInvoiceNumber($currentUser);

                $invoice = Invoice::create([
                    'user_id'              => $currentUser->id,
                    'billable_type'        => User::class,
                    'billable_id'          => $currentUser->id,
                    'lease_id'             => $lease->id,
                    'document_template_id' => $template?->id, // Assign template if found
                    'invoice_no'           => $invoiceNo,
                    'type'                 => 'manual',
                    'period'               => Carbon::parse($data['period'])->startOfMonth()->toDateString(),
                    'due_date'             => $data['due_date'],
                    'total_amount'         => $totalCents,
                    'amount_paid'          => 0,
                    'amount_balance'       => $totalCents,
                    'status'               => 'unpaid',
                    'remarks'              => $data['remarks'] ?? null,
                ]);

                $this->saveInvoiceItems($invoice, $items);

                return $invoice->load('items.feeType');
            });
        }

    /**
     * Record a payment against an invoice.
     */
    public function recordPayment(Invoice $invoice, array $data): void
    {
        DB::transaction(function () use ($invoice, $data) {
            $this->paymentProcessor->process($invoice, $data);
        });
    }

    /**
     * Soft-cancel an invoice while preserving the audit trail.
     */
    public function voidInvoice(Invoice $invoice, string $reason): void
    {
        abort_if(
            !$invoice->isVoidable(),
            422,
            'Only unpaid or partial invoices can be voided.'
        );

        $remarks = $invoice->remarks ? $invoice->remarks . "\n" : '';
        $remarks .= '[VOIDED ' . now()->format('Y-m-d H:i') . '] ' . $reason;

        $invoice->update([
            'status'  => 'void',
            'remarks' => $remarks,
        ]);
    }

    /**
     * Mark all overdue invoices.
     */
    public function markOverdueInvoices(): int
    {
        return Invoice::query()
            ->whereIn('status', ['unpaid', 'partial'])
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
    }

    // =========================================================================
    // Private Helper Methods
    // =========================================================================
    /**
     * Validate raw charge/item arrays and map them into cents and active fee types.
     */
    private function processAndValidateItems(User $owner, array $rawItems): array
    {
        $totalCents = 0;
        $validatedItems = [];

        foreach ($rawItems as $item) {
            if (empty($item['fee_type_id']) || !isset($item['amount'])) {
                throw new \InvalidArgumentException('Each invoice item must contain fee_type_id and amount.');
            }

            $amountCents = (int) round(((float) $item['amount']) * 100);

            if ($amountCents <= 0) {
                throw new \InvalidArgumentException('Invoice item amount must be greater than zero.');
            }

            $feeType = FeeType::query()
                ->where('id', $item['fee_type_id'])
                ->where('user_id', $owner->id)
                ->where('is_active', true)
                ->firstOrFail();

            $validatedItems[] = [
                'fee_type'     => $feeType,
                'amount_cents' => $amountCents,
                'description'  => $item['description'] ?? $feeType->name,
            ];

            $totalCents += $amountCents;
        }

        if ($totalCents <= 0) {
            throw new \InvalidArgumentException('Invoice total must be greater than zero.');
        }

        return [
            'validated_items' => $validatedItems,
            'total_cents'     => $totalCents,
        ];
    }

    /**
     * Persist validated items to the invoice relationship.
     */
    private function saveInvoiceItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $item) {
            /** @var FeeType $feeType */
            $feeType = $item['fee_type'];

            $invoice->items()->create([
                'fee_type_id' => $feeType->id,
                'description' => $item['description'],
                'amount'      => $item['amount_cents'],
            ]);
        }
    }
}