<?php
namespace App\Services;

use App\Models\{Invoice, InvoiceItem, Lease, FeeType};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class InvoiceService
{
    public function __construct(
        private readonly PaymentProcessor $paymentProcessor,
        private readonly WalletService    $walletService,
    ) {}

    /** Called by LeaseObserver when a lease is created/renewed */
    public function createInvoice(Lease $lease, Carbon $period): Invoice
    {
        return DB::transaction(function () use ($lease, $period) {
            $rentFeeType = FeeType::where('user_id', Auth::id())
                ->where('name', 'Rent')->firstOrFail();

            $invoice = Invoice::create([
                'lease_id'     => $lease->id,
                'invoice_no'   => $this->generateInvoiceNo('RENT'),
                'type'         => 'rent',
                'period'       => $period->startOfMonth()->toDateString(),
                'due_date'     => $period->copy()->day($lease->due_day ?? 1)->toDateString(),
                'total_amount' => (int) round($lease->rent_price * 100),
                'amount_balance' => (int) round($lease->rent_price * 100),
                'status'       => 'unpaid',
            ]);

            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'fee_type_id' => $rentFeeType->id,
                'description' => 'Rent — ' . $period->format('F Y'),
                'amount'      => $invoice->total_amount,
            ]);

            return $invoice;
        });
    }

    /** Owner manually creates an invoice with multiple line items */
    public function createManualInvoice(Lease $lease, array $data): Invoice
    {
        return DB::transaction(function () use ($lease, $data) {
            $totalCents = collect($data['items'])
                ->sum(fn($item) => (int) round($item['amount'] * 100));

            $invoice = Invoice::create([
                'lease_id'       => $lease->id,
                'invoice_no'     => $this->generateInvoiceNo('INV'),
                'type'           => 'manual',
                'period'         => Carbon::parse($data['period'])->startOfMonth()->toDateString(),
                'due_date'       => $data['due_date'],
                'total_amount'   => $totalCents,
                'amount_balance' => $totalCents,
                'status'         => 'unpaid',
                'remarks'        => $data['remarks'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'fee_type_id' => $item['fee_type_id'] ?? null,
                    'description' => $item['description'],
                    'amount'      => (int) round($item['amount'] * 100),
                ]);
            }

            return $invoice;
        });
    }

    /** Record a payment against an invoice */
    public function recordPayment(Invoice $invoice, array $data): void
    {
        DB::transaction(function () use ($invoice, $data) {
            $this->paymentProcessor->process($invoice, $data);
        });
    }

    /** Soft-cancel an invoice, preserving audit trail */
    public function voidInvoice(Invoice $invoice, string $reason): void
    {
        abort_if(! $invoice->isVoidable(), 422, 'Only unpaid or partial invoices can be voided.');

        $invoice->update([
            'status'  => 'void',
            'remarks' => ($invoice->remarks ? $invoice->remarks . "\n" : '') .
                         '[VOIDED ' . now()->format('Y-m-d H:i') . '] ' . $reason,
        ]);
    }

    /** Called by scheduler — marks all overdue invoices */
    public function markOverdueInvoices(): int
    {
        return Invoice::query()
            ->whereIn('status', ['unpaid', 'partial'])
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
    }

    public function generateInvoiceNo(string $type): string
    {
        $prefix = 'INV-' . strtoupper($type) . '-' . now()->format('Y');
        $last = Invoice::where('invoice_no', 'like', $prefix . '%')
            ->orderByDesc('invoice_no')
            ->value('invoice_no');

        $next = $last ? (int) substr($last, -5) + 1 : 1;
        return $prefix . now()->format('md') . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    /** Calculate next ungenerated billing period for a lease */
    public function nextBillingPeriod(Lease $lease): ?Carbon
    {
        $existing = Invoice::forLease($lease->id)
            ->where('type', 'rent')
            ->where('status', '!=', 'void')
            ->pluck('period')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m'))
            ->toArray();

        $cursor = Carbon::parse($lease->start_date)->startOfMonth();
        $end    = Carbon::parse($lease->end_date)->startOfMonth();

        while ($cursor->lte($end)) {
            if (! in_array($cursor->format('Y-m'), $existing)) {
                return $cursor->copy();
            }
            $cursor->addMonth();
        }
        return null;
    }

    private function getFeeType(Lease $lease, string $name): FeeType
    {
        $owner = $lease->getOwner();

        return FeeType::where('user_id', $owner->id)
            ->where('name', $name)
            ->firstOrFail();
    }

    public function canGenerateInvoice(Lease $lease): bool
    {
        return !is_null($this->nextBillingPeriod($lease));
    }
}