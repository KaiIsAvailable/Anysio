<?php

namespace App\Services;

use App\Models\{Invoice, Lease, FeeType, User};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private readonly PaymentProcessor $paymentProcessor,
        private readonly WalletService $walletService,
        private readonly DocumentSequenceService $documentSequenceService,
    ) {}

    /**
     * Create an invoice for a lease containing multiple charge items.
     */
    public function createInvoice(
        Lease $lease,
        array $charges,
        string $type = 'lease'
    ): Invoice {
        return DB::transaction(function () use ($lease, $charges, $type) {
            if (empty($charges)) {
                throw new \InvalidArgumentException('Invoice must contain at least one charge.');
            }

            $owner = $this->getLeaseOwner($lease);
            ['validated_items' => $validatedCharges, 'total_cents' => $totalCents] = $this->processAndValidateItems($owner, $charges);

            $invoiceNo = $this->documentSequenceService->generateInvoiceNumber($owner);

            // Period & Due Date Calculation
            $period = Carbon::parse($lease->start_date)->startOfMonth();
            $dueDate = Carbon::parse($lease->start_date);

            if (!empty($lease->due_day)) {
                $dueDate->day(min((int) $lease->due_day, $dueDate->daysInMonth));
            }

            // Create Invoice Header
            $invoice = Invoice::create([
                'lease_id'       => $lease->id,
                'invoice_no'     => $invoiceNo,
                'type'           => $type,
                'period'         => $period->toDateString(),
                'due_date'       => $dueDate->toDateString(),
                'total_amount'   => $totalCents,
                'amount_paid'    => 0,
                'amount_balance' => $totalCents,
                'status'         => 'unpaid',
            ]);

            // Create Invoice Items
            $this->saveInvoiceItems($invoice, $validatedCharges);

            return $invoice->load('items.feeType');
        });
    }

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

            $owner = $this->getLeaseOwner($lease);
            ['validated_items' => $items, 'total_cents' => $totalCents] = $this->processAndValidateItems($owner, $data['items']);

            $invoiceNo = $this->documentSequenceService->generateInvoiceNumber($owner);

            $invoice = Invoice::create([
                'lease_id'       => $lease->id,
                'invoice_no'     => $invoiceNo,
                'type'           => 'manual',
                'period'         => Carbon::parse($data['period'])->startOfMonth()->toDateString(),
                'due_date'       => $data['due_date'],
                'total_amount'   => $totalCents,
                'amount_paid'    => 0,
                'amount_balance' => $totalCents,
                'status'         => 'unpaid',
                'remarks'        => $data['remarks'] ?? null,
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

    /**
     * Calculate the next ungenerated rent billing period.
     */
    public function nextBillingPeriod(Lease $lease): ?Carbon
    {
        $existing = Invoice::forLease($lease->id)
            ->where('type', 'rent')
            ->where('status', '!=', 'void')
            ->pluck('period')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m'))
            ->toArray();

        $cursor = Carbon::parse($lease->start_date)->startOfMonth();
        $end = Carbon::parse($lease->end_date)->startOfMonth();

        while ($cursor->lte($end)) {
            if (!in_array($cursor->format('Y-m'), $existing)) {
                return $cursor->copy();
            }
            $cursor->addMonth();
        }

        return null;
    }

    /**
     * Determine whether another recurring rent invoice can be generated.
     */
    public function canGenerateInvoice(Lease $lease): bool
    {
        return !is_null($this->nextBillingPeriod($lease));
    }

    // =========================================================================
    // Private Helper Methods
    // =========================================================================

    /**
     * Retrieve and validate the owner of the given lease.
     */
    private function getLeaseOwner(Lease $lease): User
    {
        $owner = $lease->getOwner();

        if (!$owner instanceof User) {
            throw new \RuntimeException('Unable to determine the owner of this lease.');
        }

        return $owner;
    }

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