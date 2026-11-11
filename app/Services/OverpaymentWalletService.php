<?php

namespace App\Services;

use App\Models\{Invoice, Wallet, User};
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;

/** Runs inside the originating invoice creation or overpayment transaction. */
class OverpaymentWalletService
{
    public function settle(Invoice $source, string $paymentDate, InvoiceService $invoices, bool $newInvoice = false, ?User $issuer = null): ?array
    {
        if (!$source->lease_id || !$source->lease?->tenant?->user_id) return null;
        $wallet = Wallet::where('user_id', $source->lease->tenant->user_id)->lockForUpdate()->first();
        if (!$wallet) return null;
        $startingBalance = max(0, (int) $wallet->balance);
        if ($newInvoice && $startingBalance <= 0) return null;
        $summary = ['source_invoice_no' => $source->invoice_no, 'wallet_used_cents' => 0,
            'wallet_remaining_cents' => $startingBalance, 'payments' => [], 'skipped' => [], 'notes' => []];
        $summary['trigger'] = $newInvoice ? 'invoice_created' : 'overpayment';
        $walletService = app(WalletService::class);
        $summary['wallet_available_cents'] = $walletService->automaticSpendableBalance($wallet);
        $summary['wallet_reserved_cents'] = $startingBalance - $summary['wallet_available_cents'];
        if ($wallet->reserved_refund_id && $walletService->automaticSpendableBalance($wallet) <= 0) {
            $summary['notes'][] = 'The wallet balance is reserved for an approved checkout refund. No automatic wallet payment was made.';
        }

        $candidates = Invoice::forLease($source->lease_id)->unpaid()->where('amount_balance', '>', 0)
            ->where('type', '!=', Invoice::TYPE_SUBSCRIPTION)
            ->where(fn ($query) => $query->whereNull('context')->orWhere('context', Invoice::CONTEXT_TENANT));
        if ($newInvoice) {
            $candidates->whereKey($source->id);
        } else {
            $candidates->where('id', '!=', $source->id)->where(function ($query) use ($source) {
                $query->where('period', '<', $source->period?->toDateString())
                    ->orWhere(function ($samePeriod) use ($source) {
                        $samePeriod->where('period', $source->period?->toDateString())->where(function ($older) use ($source) {
                            $older->where('created_at', '<', $source->created_at)
                                ->orWhere(fn ($tie) => $tie->where('created_at', $source->created_at)->where('id', '<', $source->id));
                        });
                    });
            });
        }
        $previous = $candidates->orderBy('period')->orderBy('created_at')->orderBy('id')->lockForUpdate()->get();
        if ($newInvoice && $previous->isEmpty()) return null;

        $pendingSkipped = [];
        foreach ($previous as $invoice) {
            // Identify refund deductions even when all wallet funds are reserved.
            try {
                app(RefundService::class)->assertInvoicePaymentAllowed($invoice, onlyIncludedInvoices: true);
            } catch (ValidationException $exception) {
                $reason = implode(' ', \Illuminate\Support\Arr::flatten($exception->errors()));
                $summary['notes'][] = 'Skipped '.$invoice->invoice_no.': '.$reason;
                $pendingSkipped[] = count($summary['skipped']);
                $summary['skipped'][] = $this->skippedRow($invoice, $reason, true);
                continue;
            }
            if ($walletService->automaticSpendableBalance($wallet->fresh()) <= 0) continue;
            $before = (int) $invoice->amount_balance;
            $existingError = session('error');
            try {
                // Each nested payment has its own rollback boundary, while the full receipt
                // and excess allocation still commit together in the outer transaction.
                app(PaymentProcessor::class)->process($invoice, [
                    'amount_paid' => 0, 'payment_method' => 'Wallet', 'use_wallet' => true,
                    'payment_date' => $paymentDate, 'transaction_ref' => null,
                    'remarks' => $newInvoice ? 'Automatic wallet payment for newly generated invoice '.$source->invoice_no
                        : 'Wallet payment from overpayment on '.$source->invoice_no,
                ], $invoices, automatic: true, issuer: $issuer);
            } catch (ValidationException | HttpResponseException $exception) {
                $reason = $exception instanceof ValidationException
                    ? implode(' ', \Illuminate\Support\Arr::flatten($exception->errors()))
                    : (session('error') ?? 'The payment policy requires review before this invoice can be paid.');
                if ($exception instanceof HttpResponseException) {
                    if ($existingError === null) session()->forget('error');
                    else session()->flash('error', $existingError);
                }
                $summary['notes'][] = 'Skipped '.$invoice->invoice_no.': '.$reason;
                $pendingSkipped[] = count($summary['skipped']);
                $summary['skipped'][] = $this->skippedRow($invoice, $reason,
                    $exception instanceof ValidationException && array_key_exists('refund', $exception->errors()));
                continue;
            } catch (\RuntimeException $exception) {
                // Preserve the original external payment if an older invoice lacks a receipt
                // template. Database/other runtime subclasses must still abort and roll back.
                if (get_class($exception) !== \RuntimeException::class) throw $exception;
                $summary['notes'][] = 'Skipped '.$invoice->invoice_no.': '.$exception->getMessage();
                $pendingSkipped[] = count($summary['skipped']);
                $summary['skipped'][] = $this->skippedRow($invoice, $exception->getMessage());
                continue;
            }
            $invoice->refresh();
            $this->addRow($summary, $invoice, $before - (int) $invoice->amount_balance);
            foreach ($pendingSkipped as $index) {
                $summary['skipped'][$index]['next_paid_invoice_no'] = $invoice->invoice_no;
            }
            $pendingSkipped = [];
            // The existing processor may create and pay a late-penalty invoice as well.
            foreach ($invoice->newQuery()->where('parent_id', $invoice->id)->where('type', 'penalty')->get() as $penalty) {
                $this->addRow($summary, $penalty, (int) $penalty->amount_paid);
            }
        }
        $summary['wallet_remaining_cents'] = max(0, (int) $wallet->fresh()->balance);
        $summary['wallet_used_cents'] = $startingBalance - $summary['wallet_remaining_cents'];
        if ($wallet->reserved_refund_id && $summary['wallet_remaining_cents'] > 0) {
            $summary['notes'][] = 'Funds reserved for the approved checkout refund remain in the wallet and were not spent.';
        }
        if ($previous->isEmpty()) {
            $summary['notes'][] = 'There are no earlier unpaid invoices on this lease to pay. The balance remains in the wallet.';
        }

        return $summary;
    }

    private function skippedRow(Invoice $invoice, string $reason, bool $refundLocked = false): array
    {
        $invoice->loadMissing('items.feeType');
        return [
            'invoice_no' => $invoice->invoice_no,
            'items' => $invoice->items->map(fn ($item) => $item->feeType?->name ?? 'Unavailable fee type')->unique()->implode(', '),
            'remaining_cents' => (int) $invoice->amount_balance,
            'refund_locked' => $refundLocked, 'reason' => $reason, 'next_paid_invoice_no' => null,
        ];
    }

    private function addRow(array &$summary, Invoice $invoice, int $paidCents): void
    {
        if ($paidCents <= 0) return;
        $invoice->loadMissing('items.feeType');
        $summary['payments'][] = [
            'invoice_no' => $invoice->invoice_no,
            'items' => $invoice->items->map(fn ($item) => $item->feeType?->name ?? 'Unavailable fee type')->unique()->implode(', '),
            'invoice_amount_cents' => (int) $invoice->total_amount,
            'paid_cents' => $paidCents, 'remaining_cents' => (int) $invoice->amount_balance,
            'status' => $invoice->status,
        ];
    }
}
