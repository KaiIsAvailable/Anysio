<?php
namespace App\Services;

use App\Models\{Invoice, Transaction, DocumentTemplate};
use App\Events\PaymentRecorded;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Exceptions\HttpResponseException;

class PaymentProcessor
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly DocumentSequenceService $documentSequenceService,
    ) {}

    public function process(Invoice $invoice, array $data): Transaction
    {
        $this->assertNoEarlierOutstanding($invoice);

        $currentUser = Auth::user(); 

        if (!$currentUser) {
            throw new \RuntimeException('Authenticated user required to generate invoices.');
        }

        $paidCents    = (int) round($data['amount_paid'] * 100);
        $balanceCents = $invoice->amount_balance;
        $appliedCents = min($paidCents, $balanceCents);
        $excessCents  = max(0, $paidCents - $balanceCents);
        $receiptNo = $this->documentSequenceService->generateReceiptNumber($currentUser);

        $template = DocumentTemplate::where('created_by', $currentUser->id)
                    ->where('category', 'receipt')
                    ->where('status', 'active')
                    ->first();

        $transaction = Transaction::create([
            'invoice_id'           => $invoice->id,
            'amount_paid'          => $paidCents,
            'amount_applied'       => $appliedCents,
            'amount_excess'        => $excessCents,
            'payment_method'       => $data['payment_method'],
            'transaction_ref'      => $data['transaction_ref'] ?? null,
            'receipt_no'           => $receiptNo,
            'document_template_id' => $template?->id,
            'payment_date'         => $data['payment_date'],
            'approved_by'          => Auth::id(),
            'remarks'              => $data['remarks'] ?? null,
        ]);

        // Update invoice balances
        $newPaid    = $invoice->amount_paid + $appliedCents;
        $newBalance = max(0, $invoice->total_amount - $newPaid);

        $invoice->update([
            'amount_paid'    => $newPaid,
            'amount_balance' => $newBalance,
            'status'         => $this->resolveStatus($invoice->total_amount, $newBalance),
        ]);

        // Credit excess to wallet
        if ($excessCents > 0) {
            $tenant = $invoice->lease->tenant;
            $this->walletService->credit(
                $tenant->user_id,
                $excessCents,
                'overpayment_credit',
                $invoice->id,
                "Excess from invoice {$invoice->invoice_no}"
            );
        }

        return $transaction;
    }

    private function assertNoEarlierOutstanding(Invoice $invoice): void
    {
        if (!$invoice->lease_id) {
            return;
        }

        $hasEarlier = Invoice::forLease($invoice->lease_id)
            ->unpaid()
            ->where('id', '!=', $invoice->id)
            ->where(function ($query) use ($invoice) {
                $query->where('period', '<', $invoice->period)
                    ->orWhere(function ($q) use ($invoice) {
                        $q->where('period', '=', $invoice->period)
                            ->where('created_at', '<', $invoice->created_at);
                    });
            })
            ->exists();

        if ($hasEarlier) {
            // This safely returns a redirect response back to the user without breaking your error flow
            throw new HttpResponseException(
                back()->with('error', 'Earlier outstanding invoices must be settled first.')
            );
        }
    }

    private function resolveStatus(int $total, int $balance): string
    {
        if ($balance <= 0)     return 'paid';
        if ($balance < $total) return 'partial';
        return 'unpaid';
    }
}