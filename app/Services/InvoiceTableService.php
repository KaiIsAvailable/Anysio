<?php

namespace App\Services;

use App\Models\{Invoice, Lease};
use Illuminate\Support\Facades\Gate;

/** Shared, permission-filtered invoice table payloads without embedded documents. */
class InvoiceTableService
{
    public function page(Lease $lease, int $page = 1): array
    {
        return $this->paginate($lease->invoices(), $page, 5, $lease);
    }

    public function allPage(?string $actualId, ?string $effectiveId, bool $superAdmin, int $page = 1, ?string $search = null): array
    {
        return $this->paginate(Invoice::query()->visibleTo($actualId, $effectiveId, $superAdmin), $page, 10, search: $search);
    }

    public function indexPaginator(?string $actualId, ?string $effectiveId, bool $superAdmin, int $page = 1, ?string $search = null)
    {
        return $this->paginate(Invoice::query()->visibleTo($actualId, $effectiveId, $superAdmin), $page, 10, asPaginator: true, search: $search);
    }

    private function paginate($query, int $page, int $perPage, ?Lease $lease = null, bool $asPaginator = false, ?string $search = null)
    {
        $relatedQuery = clone $query;
        $query->search($search);
        $canProof = !$lease && (Gate::allows('super-admin') || Gate::allows('invoice.view payment proof'));
        if (!$lease) $query->with(['user:id,name', 'lease.tenant.user.wallet', 'lease.leasable']);
        if ($canProof) $query->with('payments:id,invoice_id,receipt_path,created_at');
        $rows = $query->with([
            'documentTemplate:id,title', 'items.feeType:id,name',
            'transactions' => fn ($q) => $q->select('id', 'invoice_id', 'receipt_no', 'amount_paid', 'payment_date', 'document_template_id'),
        ])->latest()->orderByDesc('id')->paginate($perPage, ['*'], 'page', max(1, $page));
        $canInvoice = Gate::allows('super-admin') || Gate::allows('invoice.view invoice');
        $canReceipt = Gate::allows('super-admin') || Gate::allows('invoice.view receipt');
        $relatedCharges = (clone $relatedQuery)->whereIn('parent_id', $rows->getCollection()->pluck('id'))
            ->whereIn('type', ['penalty', 'processing_fee'])
            ->with(['documentTemplate:id,title', 'transactions:id,invoice_id,receipt_no,document_template_id'])
            ->oldest()->get()->groupBy('parent_id');
        $originalInvoices = (clone $relatedQuery)
            ->whereIn('id', $rows->getCollection()->whereIn('type', ['penalty', 'processing_fee'])->pluck('parent_id')->filter())
            ->with(['documentTemplate:id,title', 'transactions:id,invoice_id,receipt_no,document_template_id'])
            ->get()->keyBy('id');
        $walletCents = $lease?->tenant?->user_id
            ? (int) app(WalletService::class)->getBalance($lease->tenant->user_id) : 0;
        $invoices = $rows->getCollection()->map(function (Invoice $invoice) use ($canInvoice, $canReceipt, $relatedCharges, $originalInvoices, $lease, $walletCents, $canProof) {
            $original = in_array($invoice->type, ['penalty', 'processing_fee'], true)
                ? $originalInvoices->get($invoice->parent_id) : null;
            return [
                'id' => $invoice->id, 'invoice_no' => $invoice->invoice_no,
                'lease_id' => $invoice->lease_id,
                'wallet_balance' => number_format(($lease ? $walletCents : (int) ($invoice->lease?->tenant?->user?->wallet?->balance ?? 0)) / 100, 2),
                'tenant' => !$lease ? [
                    'name' => $invoice->lease?->tenant?->user?->name ?? $invoice->user?->name ?? 'N/A',
                    'url' => $invoice->lease?->tenant_id ? route('admin.tenants.show', $invoice->lease->tenant_id) : null,
                    'context' => $invoice->lease ? ucfirst(class_basename($invoice->lease->leasable_type)).': '.$invoice->lease->leasable_name : 'Subscription',
                    'context_url' => $invoice->lease_id ? route('admin.leases.show', $invoice->lease_id) : null,
                ] : null,
                'payment_proofs' => $canProof ? $invoice->payments->filter(fn ($payment) => filled($payment->receipt_path))->map(fn ($payment) => [
                    'id' => $payment->id, 'url' => route('admin.payments.view-receipt', $payment),
                    'is_pdf' => strtolower(pathinfo($payment->receipt_path, PATHINFO_EXTENSION)) === 'pdf',
                    'date' => $payment->created_at?->format('d/m/Y') ?? '?',
                    'user' => $invoice->lease?->tenant?->user?->name ?? 'N/A',
                ])->values()->all() : [],

                'charge_label' => match ($invoice->type) {
                    'penalty' => 'Late penalty', 'processing_fee' => 'Processing fee', default => null,
                },
                'original_invoice' => $original ? [
                    'id' => $original->id, 'invoice_no' => $original->invoice_no,
                    'label' => 'Original invoice',
                    'amount' => number_format($original->total_amount / 100, 2),
                    'status' => strtolower($original->status),
                    'document_url' => $canInvoice && $original->documentTemplate
                        ? route('admin.invoices.document-data', $original) : null,
                    'receipts' => $canReceipt ? $original->transactions->map(fn ($transaction) => [
                        'id' => $transaction->id, 'receipt_no' => $transaction->receipt_no,
                        'document_url' => $transaction->document_template_id
                            ? route('admin.invoices.receipt-data', [$original, $transaction]) : null,
                    ])->values()->all() : [],
                ] : null,
                'related_charges' => $relatedCharges->get($invoice->id, collect())->map(fn ($charge) => [
                    'id' => $charge->id, 'invoice_no' => $charge->invoice_no,
                    'label' => $charge->type === 'penalty' ? 'Late penalty' : 'Processing fee',
                    'amount' => number_format($charge->total_amount / 100, 2),
                    'status' => strtolower($charge->status),
                    'document_url' => $canInvoice && $charge->documentTemplate
                        ? route('admin.invoices.document-data', $charge) : null,
                    'receipts' => $canReceipt ? $charge->transactions->map(fn ($transaction) => [
                        'id' => $transaction->id, 'receipt_no' => $transaction->receipt_no,
                        'document_url' => $transaction->document_template_id
                            ? route('admin.invoices.receipt-data', [$charge, $transaction]) : null,
                    ])->values()->all() : [],
                ])->values()->all(),
                'period' => $invoice->period?->format('m/Y') ?? '—',
                'due_date' => $invoice->due_date?->toDateString(),
                'due_date_formatted' => $invoice->due_date?->format('d/m/Y') ?? '—',
                'total_amount' => number_format($invoice->total_amount / 100, 2),
                'amount_paid' => number_format($invoice->amount_paid / 100, 2),
                'amount_balance' => number_format($invoice->amount_balance / 100, 2),
                'status' => strtolower($invoice->status), 'remarks' => $invoice->remarks ?? '—',
                'document_template_id' => $canInvoice ? $invoice->document_template_id : null,
                'template_title' => $canInvoice ? $invoice->documentTemplate?->title : null,
                'document_url' => $canInvoice && $invoice->documentTemplate
                    ? route('admin.invoices.document-data', $invoice) : null,
                'invoice_items' => $invoice->items->map(fn ($item) => [
                    'id' => $item->id, 'name' => $item->feeType?->name ?? 'Item',
                    'description' => $item->description, 'amount' => number_format($item->amount / 100, 2),
                ])->values()->all(),
                'receipts' => $canReceipt ? $invoice->transactions->map(fn ($transaction) => [
                    'id' => $transaction->id, 'receipt_no' => $transaction->receipt_no,
                    'amount' => number_format($transaction->amount_paid / 100, 2),
                    'date' => $transaction->payment_date,
                    'document_url' => $transaction->document_template_id
                        ? route('admin.invoices.receipt-data', [$invoice, $transaction]) : null,
                ])->values()->all() : [],
            ];
        })->values()->all();

        if ($asPaginator) {
            $rows->setCollection(collect($invoices));
            return $rows->withQueryString()->onEachSide(1);
        }
        $refund = $lease?->latestRefund;
        return ['invoices' => $invoices, 'lease_id' => $lease?->id, 'wallet_balance' => number_format($walletCents / 100, 2),
            'refund_status' => $refund?->status?->value, 'refund_id' => $refund?->id,
            'pagination' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()]];
    }
}
