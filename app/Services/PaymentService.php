<?php

namespace App\Services;

use App\Models\{Invoice, Payment, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PaymentService
{
    public function __construct(
        protected FileService $fileService,
        protected PaymentProcessor $paymentProcessor,
    ) {}

    /**
     * Tenant submits payment receipt.
     */
    public function submitReceipt(
        Invoice $invoice,
        User $user,
        array $data
    ): Payment {

        return DB::transaction(function () use ($invoice, $user, $data) {

            // Prevent duplicate pending submission
            $existing = Payment::where('invoice_id', $invoice->id)
                ->where('submitted_by', $user->id)
                ->where('status', 'pending')
                ->first();

            if ($existing) {
                throw new \Exception('You already have a pending payment submission.');
            }

            $receiptPath = $this->fileService->upload(
                $data['attachment'],
                $user->id,
                'user_receipt'
            );

            return Payment::create([
                'invoice_id'      => $invoice->id,
                'submitted_by'    => $user->id,
                'receipt_path'    => $receiptPath,
                'transaction_ref' => $data['transaction_ref'] ?? null,
                'status'          => 'pending',
            ]);
        });
    }

    /**
     * Admin approves payment.
     */
    public function approve(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {

            $invoice = $payment->invoice;

            $this->paymentProcessor->process($invoice, [
                'amount_paid'     => $invoice->amount_balance / 100,
                'payment_method'  => 'bank_transfer',
                'transaction_ref' => $payment->transaction_ref,
                'payment_date'    => now(),
            ]);

            $payment->update([
                'status' => 'approved',
                'verified_by' => Auth::id(),
                'verified_at' => now(),
            ]);
        });
    }

    /**
     * Admin rejects payment.
     */
    public function reject(Payment $payment, ?string $remark = null): void
    {
        $payment->update([
            'status' => 'rejected',
            'remark' => $remark,
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ]);
    }
}