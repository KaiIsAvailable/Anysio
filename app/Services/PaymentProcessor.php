<?php

namespace App\Services;

use App\Models\{Invoice, Transaction, DocumentTemplate, UserManagement};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;

class PaymentProcessor
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly DocumentSequenceService $documentSequenceService,
        private readonly InvoicePenaltyService $invoicePenaltyService,
        private readonly SettingService $settingService,
    ) {}

    public function process(Invoice $invoice, array $data, InvoiceService $invoiceService, ?int &$overpaymentCents = null, bool $automatic = false, ?\App\Models\User $issuer = null, bool $settlement = false): Transaction
    {
        $overpaymentCents = 0;
        return DB::transaction(function () use ($invoice, $data, $invoiceService, &$overpaymentCents, $automatic, $issuer, $settlement) {
            // Serialize duplicate submissions and always use the latest approved balance.
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($locked->amount_balance <= 0 || $locked->isVoid()) {
                throw ValidationException::withMessages(['invoice' => 'This invoice is already settled or void. No payment has been recorded.']);
            }
            if ($automatic) app(RefundService::class)->assertInvoicePaymentAllowed($locked, onlyIncludedInvoices: true);
            return $this->processLocked($locked, $data, $invoiceService, $overpaymentCents, $automatic, $issuer, $settlement);
        });
    }

    private function processLocked(Invoice $invoice, array $data, InvoiceService $invoiceService, ?int &$overpaymentCents, bool $automatic, ?\App\Models\User $issuer, bool $settlement): Transaction
    {
        $currentUser = $issuer ?? get_effective_user();
        if (!$currentUser) {
            throw new \RuntimeException('Authenticated user required to generate invoices.');
        }

        // Fetch effective settings to check payment collection policy
        $settings = $this->settingService->getEffectiveSettings($currentUser);
        $allowUnpaidPenalty = (bool) data_get($settings, 'payment_config.value.allow_unpaid_penalty', false);

        $rawPaidCents = (int) round(($data['amount_paid'] ?? 0) * 100);
        $walletOnly = strtolower(trim($data['payment_method'] ?? '')) === 'wallet';
        $useWallet = filter_var($data['use_wallet'] ?? false, FILTER_VALIDATE_BOOLEAN);
        // Defend service callers too: a method label alone must never create a Wallet receipt.
        if ($walletOnly && (!$useWallet || (float) ($data['amount_paid'] ?? 0) != 0 || filled($data['transaction_ref'] ?? null))) {
            throw ValidationException::withMessages(['payment_method' => 'Wallet payments require Apply Wallet, zero external payment and no external reference.']);
        }
        if (!$walletOnly && $rawPaidCents <= 0) {
            throw ValidationException::withMessages(['amount_paid' => 'An external payment method requires a positive external payment amount.']);
        }
        $tenant = $invoice->lease?->tenant;
        $walletDeductionCents = 0;

        // Never trust a client-supplied amount or an earlier preview for collection.
        // Payout applies the reviewed settlement; it must not introduce a new, unapproved charge.
        $penaltyDetails = $settlement ? null : $this->invoicePenaltyService->quote($invoice, $data['payment_date'], lockFeeType: true);
        $penaltyAmount = isset($penaltyDetails['amount']) ? (float) $penaltyDetails['amount'] : 0;
        $penaltyAmountCents = (int) round($penaltyAmount * 100);
        $processingFeeCents = ($automatic || $settlement || $useWallet || !$invoice->lease_id || $invoice->type === 'processing_fee')
            ? 0 : $this->processingFeeCents($settings, $data);
        if ($processingFeeCents > 0 && $rawPaidCents <= $processingFeeCents) {
            throw ValidationException::withMessages(['processing_fee' => 'Amount paid must cover the processing fee and a payment toward the invoice.']);
        }
        
        // Include all charges before deciding whether any payment is excess.
        $totalRequiredCents = $invoice->amount_balance + $penaltyAmountCents + $processingFeeCents;
        // A genuine external overpayment can fund earlier invoices in this same transaction.
        // The automatic loop already orders candidates and may skip blocked invoices.
        if (!$automatic && !($rawPaidCents > $totalRequiredCents && $this->settingService->allowsAutomaticWalletPayment($currentUser))) {
            $this->assertNoEarlierOutstanding($invoice, $allowUnpaidPenalty);
        }

        // Optional wallet deduction for the invoice & penalty
        if ($useWallet && $tenant) {
            $walletBalanceCents = $automatic
                ? $this->walletService->automaticSpendableBalance(\App\Models\Wallet::where('user_id', $tenant->user_id)->lockForUpdate()->firstOrFail())
                : $this->walletService->getBalance($tenant->user_id);
            $walletDeductionCents = min($walletBalanceCents, $totalRequiredCents);

            if ($walletDeductionCents > 0) {
                $this->walletService->debit(
                    $tenant->user_id,
                    $walletDeductionCents,
                    'invoice_payment',
                    $invoice->id,
                    "Wallet payment applied to invoice {$invoice->invoice_no} and penalties",
                    automatic: $automatic
                );
            }
        }

        if ($walletOnly && $walletDeductionCents <= 0) {
            throw ValidationException::withMessages(['use_wallet' => 'No Wallet funds are available for this invoice.']);
        }

        $totalPaidCents = $rawPaidCents + $walletDeductionCents;

        if ($totalPaidCents <= 0) {
             throw new HttpResponseException(
                back()->with('error', 'The payment amount cannot be zero.')
            );
        }

        // Enforce full payment ONLY if allow_unpaid_penalty is false
        if (!$allowUnpaidPenalty && $penaltyAmountCents > 0 && $totalPaidCents < $totalRequiredCents) {
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

        // The processing fee is fully collected with this external payment.
        // Its own invoice/receipt keeps it out of the rent balance and wallet excess.
        if ($processingFeeCents > 0) {
            $this->collectProcessingFee($invoice, $processingFeeCents, $data, $currentUser, $template);
        }
        $poolCents = $totalPaidCents - $processingFeeCents;
        $penaltyAppliedCents = 0;
        $penaltyTransaction = null;
        $transaction1 = null;

        // Always generate the penalty invoice if a penalty applies
        $penaltyInvoice = null;
        if ($penaltyAmount > 0) {
            $penaltyInvoice = $invoiceService->createManualInvoice($invoice->lease, [
                'parent_id' => $invoice->id,
                'type'      => 'penalty',
                'period'    => $invoice->period,
                'due_date'  => app(LatePenaltyCalculator::class)->day()->toDateString(),
                // Updated remarks string referencing the parent/original invoice number
                'remarks'   => sprintf('Late penalty for invoice %s', $invoice->invoice_no),
                'items'     => [
                    [
                        'fee_type_id' => $penaltyDetails['fee_type_id'],
                        'description' => $penaltyDetails['title'] ?? 'Late Payment Penalty',
                        'amount'      => $penaltyAmount,
                    ]
                ]
            ], $currentUser);
        }

        if ($allowUnpaidPenalty) {
            // ==========================================
            // STRATEGY A: PAY ORIGINAL INVOICE FIRST
            // ==========================================
            
            // 1. Process Original Invoice First
            $appliedCents = min($poolCents, $invoice->amount_balance);
            $poolCents -= $appliedCents;

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
                    'approved_by'          => Auth::id() ?? ($automatic ? $currentUser->id : null),
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

            // 2. Process Penalty Second with any remaining pool amount
            if ($penaltyInvoice && $penaltyAmount > 0) {
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
                        'approved_by'          => Auth::id() ?? ($automatic ? $currentUser->id : null),
                        'remarks'              => 'Payment for late penalty',
                    ]);
                }

                $penaltyNewPaid = $penaltyInvoice->amount_paid + $penaltyAppliedCents;
                $penaltyNewBalance = max(0, $penaltyInvoice->total_amount - $penaltyNewPaid);
                $penaltyInvoice->update([
                    'amount_paid'    => $penaltyNewPaid,
                    'amount_balance' => $penaltyNewBalance,
                    'status'         => $this->resolveStatus($penaltyInvoice->total_amount, $penaltyNewBalance),
                ]);
            }

            $excessCents = $poolCents;

        } else {
            // ==========================================
            // STRATEGY B: PAY PENALTY FIRST (DEFAULT)
            // ==========================================

            // 1. Process Penalty First
            if ($penaltyInvoice && $penaltyAmount > 0) {
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
                        'approved_by'          => Auth::id() ?? ($automatic ? $currentUser->id : null),
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

            // 2. Process Original Invoice Second
            $appliedCents = min($poolCents, $invoice->amount_balance);
            $poolCents -= $appliedCents;
            $excessCents = $poolCents;

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
                    'approved_by'          => Auth::id() ?? ($automatic ? $currentUser->id : null),
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
            $overpaymentCents = $excessCents;
        }

        // Return the primary transaction created (prioritize invoice transaction, fallback to penalty transaction)
        return $transaction1 ?? $penaltyTransaction;
    }

    private function processingFeeCents(array $settings, array $data): int
    {
        if (!filter_var(data_get($settings, 'processing_fee_config.is_active', false), FILTER_VALIDATE_BOOLEAN)
            || !in_array($data['payment_method'], data_get($settings, 'processing_fee_config.value.applicable_payment_method', []), true)) {
            return 0;
        }
        $defaultCents = max(0, (int) round((float) data_get($settings, 'processing_fee_config.value.processing amount', 0) * 100));
        if ($defaultCents === 0) return 0;

        if (filter_var($data['processing_fee_cancelled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            if (!\Illuminate\Support\Facades\Gate::any(['super-admin', 'invoice.remove processing fee'])) {
                throw ValidationException::withMessages(['processing_fee' => 'You have no permission to remove the processing fee.']);
            }
            return 0;
        }

        if (!array_key_exists('processing_fee_amount', $data)) return $defaultCents;
        // Validate service callers as well as HTTP requests.
        \Illuminate\Support\Facades\Validator::make($data, [
            'processing_fee_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ])->validate();
        $amountCents = (int) round((float) $data['processing_fee_amount'] * 100);
        if ($amountCents !== $defaultCents && !\Illuminate\Support\Facades\Gate::any(['super-admin', 'invoice.edit processing fee'])) {
            throw ValidationException::withMessages(['processing_fee' => 'You have no permission to edit the processing fee. Refresh the payment form if the settings have changed.']);
        }
        return $amountCents;
    }

    private function collectProcessingFee(Invoice $parent, int $amountCents, array $data, \App\Models\User $issuer, DocumentTemplate $receiptTemplate): void
    {
        // Serialize creation of this management account's fee type, even across invoices.
        \App\Models\User::whereKey($issuer->id)->lockForUpdate()->firstOrFail();
        $feeType = \App\Models\FeeType::firstOrCreate([
            'user_id' => $issuer->id, 'name' => 'Processing Fee', 'category' => 'service',
        ], ['is_system' => false, 'is_active' => true]);
        if (!$feeType->is_active || !$this->settingService->isFeeTypeActive($feeType, userId: $issuer->id)) {
            throw ValidationException::withMessages(['processing_fee' => 'The Processing Fee fee type is inactive. Enable it before collecting this fee.']);
        }

        // Build a paid child directly: this fee must not trigger another wallet payment.
        $feeInvoice = Invoice::create([
            'parent_id' => $parent->id,
            'context' => $parent->context,
            'billable_type' => $parent->billable_type,
            'billable_id' => $parent->billable_id,
            'lease_id' => $parent->lease_id,
            'document_template_id' => $parent->document_template_id,
            'invoice_no' => $this->documentSequenceService->generateInvoiceNumber($issuer),
            'type' => 'processing_fee',
            'period' => $parent->period,
            'due_date' => $data['payment_date'],
            'total_amount' => $amountCents,
            'amount_paid' => $amountCents,
            'amount_balance' => 0,
            'status' => 'paid',
            'remarks' => 'Processing fee for invoice '.$parent->invoice_no,
        ]);
        $feeInvoice->items()->create([
            'fee_type_id' => $feeType->id,
            'description' => 'Processing Fee ('.$data['payment_method'].')',
            'amount' => $amountCents,
        ]);
        Transaction::create([
            'invoice_id' => $feeInvoice->id,
            'amount_paid' => $amountCents,
            'amount_applied' => $amountCents,
            'amount_excess' => 0,
            'payment_method' => $data['payment_method'],
            'transaction_ref' => $data['transaction_ref'] ?? null,
            'receipt_no' => $this->documentSequenceService->generateReceiptNumber($issuer),
            'document_template_id' => $receiptTemplate->id,
            'payment_date' => $data['payment_date'],
            'approved_by' => Auth::id(),
            'remarks' => 'Payment for processing fee',
        ]);
    }

    private function assertNoEarlierOutstanding(Invoice $invoice, bool $allowUnpaidPenalty = false): void
    {
        if (!$invoice->lease_id) {
            return;
        }

        $hasEarlier = Invoice::forLease($invoice->lease_id)
            ->unpaid()
            ->where('id', '!=', $invoice->id)
            ->when($allowUnpaidPenalty, function ($query) use ($invoice) {
                // Separated penalties do not block rent collection. The linked
                // rental also must not prevent payment of its own penalty.
                $query->where(fn ($types) => $types->whereNull('type')->orWhere('type', '!=', 'penalty'));
                if ($invoice->type === 'penalty' && $invoice->parent_id) {
                    $query->where('id', '!=', $invoice->parent_id);
                }
            })
            ->where(function ($query) use ($invoice) {
                $query->where('period', '<', $invoice->period?->toDateString())
                    ->orWhere(function ($q) use ($invoice) {
                        $q->where('period', '=', $invoice->period?->toDateString())
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
