<?php

namespace App\Services;

use App\Models\{Invoice, Transaction, DocumentTemplate, UserManagement, FeeType};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Exceptions\HttpResponseException;

class PaymentProcessor
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly DocumentSequenceService $documentSequenceService,
    ) {}

    public function process(Invoice $invoice, array $data, InvoiceService $invoiceService): Transaction
    {
        $this->assertNoEarlierOutstanding($invoice);

        $currentUser = get_effective_user();
        if (!$currentUser) {
            throw new \RuntimeException('Authenticated user required to generate invoices.');
        }

        $rawPaidCents = (int) round(($data['amount_paid'] ?? 0) * 100);
        $tenant = $invoice->lease?->tenant;
        $walletDeductionCents = 0;

        // Calculate penalty amount early so wallet deduction can include it
        $penaltyDetails = $data['penalty_details'] ?? null;
        $penaltyAmount = isset($penaltyDetails['amount']) ? (float) $penaltyDetails['amount'] : 0;
        $penaltyAmountCents = (int) round($penaltyAmount * 100);
        
        // Total amount required (Invoice Balance + Penalty Amount)
        $totalRequiredCents = $invoice->amount_balance + $penaltyAmountCents;

        // Optional wallet deduction for the invoice & penalty
        if (!empty($data['use_wallet']) && $tenant) {
            $walletBalanceCents = $this->walletService->getBalance($tenant->user_id);
            $walletDeductionCents = min($walletBalanceCents, $totalRequiredCents);

            if ($walletDeductionCents > 0) {
                $this->walletService->debit(
                    $tenant->user_id,
                    $walletDeductionCents,
                    'invoice_payment',
                    $invoice->id,
                    "Wallet payment applied to invoice {$invoice->invoice_no} and penalties"
                );
            }
        }

        $totalPaidCents = $rawPaidCents + $walletDeductionCents;

        if ($totalPaidCents <= 0) {
             throw new HttpResponseException(
                back()->with('error', 'The payment amount cannot be zero.')
            );
        }

        // Enforce full payment when a penalty is present to prevent leftover invoice balances
        if ($penaltyAmountCents > 0 && $totalPaidCents < $totalRequiredCents) {
            throw new HttpResponseException(
                back()->with('error', 'Full payment (including the late penalty) is required. Partial payments are not allowed when penalties apply.')
            );
        }

        // Fetch active receipt template early
        $templateOwnerId = $invoice->documentTemplate?->user_id ?? $currentUser->id;
        $template = DocumentTemplate::where('user_id', $templateOwnerId)
            ->where('category', 'receipt')
            ->where('status', 'active')
            ->latest('updated_at')
            ->first();

        if (!$template) {
            throw new \RuntimeException('No active receipt template was found for the invoice owner.');
        }

        $poolCents = $totalPaidCents;
        $penaltyAppliedCents = 0;
        $penaltyTransaction = null;

        // 1. PROCESS PENALTY FIRST IF APPLICABLE
        if ($penaltyAmount > 0) {
            $penaltyFeeType = FeeType::where('name', 'Late Payment Panalty')
                ->where('category', 'service')
                ->firstOrFail();

            Log::channel('testing')->info('Processing late penalty', ['amount' => $penaltyAmount]);

            $penaltyInvoice = $invoiceService->createManualInvoice($invoice->lease, [
                'parent_id' => $invoice->id,
                'type'      => 'penalty',
                'period'    => $invoice->period,
                'due_date'  => now()->toDateString(),
                'remarks'   => 'Generated via late penalty policy',
                'items'     => [
                    [
                        'fee_type_id' => $penaltyFeeType->id,
                        'description' => $penaltyDetails['title'] ?? 'Late Payment Panalty',
                        'amount'      => $penaltyAmount,
                    ]
                ]
            ]);

            // Allocate from the pool to the penalty first
            $penaltyAppliedCents = min($poolCents, $penaltyInvoice->amount_balance);
            $poolCents -= $penaltyAppliedCents;

            if ($penaltyAppliedCents > 0) {
                $receiptNo2 = $this->documentSequenceService->generateReceiptNumber($currentUser);

                $penaltyTransaction = Transaction::create([
                    'invoice_id'           => $penaltyInvoice->id,
                    'amount_paid'          => $penaltyAppliedCents, 
                    'amount_applied'       => $penaltyAppliedCents,
                    'amount_excess'        => 0,
                    'payment_method'       => $data['payment_method'],
                    'transaction_ref'      => $data['transaction_ref'] ?? null,
                    'receipt_no'           => $receiptNo2,
                    'document_template_id' => $template->id,
                    'payment_date'         => $data['payment_date'],
                    'approved_by'          => Auth::id(),
                    'remarks'              => 'Payment for late penalty',
                ]);

                $penaltyNewPaid = $penaltyInvoice->amount_paid + $penaltyAppliedCents;
                $penaltyNewBalance = max(0, $penaltyInvoice->total_amount - $penaltyNewPaid);
                $penaltyInvoice->update([
                    'amount_paid'    => $penaltyNewPaid,
                    'amount_balance' => $penaltyNewBalance,
                    'status'         => $this->resolveStatus($penaltyInvoice->total_amount, $penaltyNewBalance),
                ]);
            }
        }

        // 2. PROCESS ORIGINAL INVOICE WITH REMAINING POOL
        $appliedCents = min($poolCents, $invoice->amount_balance);
        $poolCents -= $appliedCents;
        $excessCents = $poolCents; // Any remaining amount after penalty & invoice 1 goes to wallet excess

        $transaction1 = null;
        if ($appliedCents > 0) {
            $receiptNo1 = $this->documentSequenceService->generateReceiptNumber($currentUser);

            $transaction1 = Transaction::create([
                'invoice_id'           => $invoice->id,
                'amount_paid'          => $appliedCents, 
                'amount_applied'       => $appliedCents,
                'amount_excess'        => 0, 
                'payment_method'       => $data['payment_method'],
                'transaction_ref'      => $data['transaction_ref'] ?? null,
                'receipt_no'           => $receiptNo1,
                'document_template_id' => $template->id,
                'payment_date'         => $data['payment_date'],
                'approved_by'          => Auth::id(),
                'remarks'              => $data['remarks'] ?? null,
            ]);

            $newPaid = $invoice->amount_paid + $appliedCents;
            $newBalance = max(0, $invoice->total_amount - $newPaid);
            $invoice->update([
                'amount_paid'    => $newPaid,
                'amount_balance' => $newBalance,
                'status'         => $this->resolveStatus($invoice->total_amount, $newBalance),
            ]);
        }

        // Handle subscription activation logic if applicable...
        if (strtolower($invoice->type ?? '') === 'subscription') {
            $userToActivate = $invoice->user ?? $invoice->lease?->tenant?->user;

            if ($userToActivate) {
                $startDate = null;
                $endDate = null;

                $userManagement = UserManagement::where('user_id', $userToActivate->id)->first();
                $package = $userManagement?->package;

                if ($package) {
                    $currentEndDate = $userManagement->end_date ? \Carbon\Carbon::parse($userManagement->end_date) : null;
                    $startDate = $currentEndDate ? $currentEndDate->copy()->addDay() : now();
                    $priceMode = strtolower($package->price_mode ?? 'monthly');

                    if ($priceMode === 'yearly') {
                        $endDate = $startDate->copy()->addYear();
                    } else {
                        $endDate = $startDate->copy()->addMonth();
                    }
                }

                $updateData = [
                    'subscription_status' => 'active',
                ];

                if ($startDate && $endDate) {
                    $updateData['start_date'] = $startDate->toDateString();
                    $updateData['end_date']   = $endDate->toDateString();
                }

                if ($userManagement) {
                    $userManagement->update($updateData);
                } else {
                    UserManagement::where('user_id', $userToActivate->id)->update($updateData);
                }
            }
        }

        // Credit remaining true excess back to wallet
        if ($excessCents > 0 && $tenant) {
            $this->walletService->credit(
                $tenant->user_id,
                $excessCents,
                'overpayment_credit',
                $invoice->id,
                "Excess from invoice {$invoice->invoice_no}"
            );
        }

        // Return the primary transaction created (prioritize invoice 1 transaction, fallback to penalty transaction if only penalty was paid)
        return $transaction1 ?? $penaltyTransaction;
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
