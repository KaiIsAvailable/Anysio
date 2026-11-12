<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoice\{StoreInvoiceRequest, RecordPaymentRequest, VoidInvoiceRequest};
use App\Models\{Invoice, Lease, Payment, Owners, Transaction, Room, Unit, Property};
use App\Services\{InvoiceService, WalletService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly WalletService $walletService
    ) {}

    public function index(Request $request)
    {
        if (Gate::denies('super-admin') && Gate::denies('invoice.tab')) {
            return view('errors.403');
        }

        $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $invoices = app(\App\Services\InvoiceTableService::class)->indexPaginator(
            Auth::id(), get_effective_user()?->id, Gate::allows('super-admin'), max(1, $request->integer('page', 1)), $request->input('search'));
        return view('adminSide.leases.invoices.index', compact('invoices'));
    }

    public function indexRows(Request $request)
    {
        abort_unless(Gate::allows('super-admin') || Gate::allows('invoice.tab'), 403);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'search' => ['nullable', 'string', 'max:255']]);
        return response()->json(app(\App\Services\InvoiceTableService::class)->allPage(
            Auth::id(), get_effective_user()?->id, Gate::allows('super-admin'), $request->integer('page', 1), $request->input('search')));
    }

    public function statement(Request $request, ?Lease $lease = null)
    {
        abort_unless(Gate::allows('super-admin') || Gate::allows($lease ? 'leases.show' : 'invoice.tab'), 403);
        $actualId = Auth::id();
        $effectiveId = get_effective_user()?->id;
        $superAdmin = Gate::allows('super-admin');
        if ($lease) {
            abort_unless(Invoice::statementLeases($actualId, $effectiveId, $superAdmin)->whereKey($lease->id)->exists(), 403);
        }
        $filters = $request->validate(InvoiceService::statementFilterRules());
        $statement = $this->invoiceService->getStatement($actualId, $effectiveId, $superAdmin, $filters, $lease?->id);
        $summary = $this->invoiceService->getStatementSummary($actualId, $effectiveId, $superAdmin, $lease?->id);
        $showTenant = $lease === null;
        $scopeLabel = $lease ? 'Records for this lease only' : 'All records you can view';

        return response()->json(['html' => view('components.invoice-statement', compact('statement', 'filters', 'scopeLabel', 'summary', 'showTenant'))->render()]);
    }

    public function show(Lease $lease, Invoice $invoice)
    {
        if (Gate::allows('owner-admin') || Gate::allows('is-staff')) {
            Gate::authorize('invoice.view invoice');
        }

        //Gate::authorize('owner-admin', $lease);
        $invoice->load(['items.feeType', 'transactions.approver', 'lease.tenant.user']);
        return view('invoices.show', compact('lease', 'invoice'));
    }

    public function recordPayment(RecordPaymentRequest $request, Invoice $invoice)
    {
        if (Gate::denies('super-admin') && Gate::denies('invoice.record payment')) {
            if ($request->expectsJson()) {
                session()->flash('error', 'You have no permission.');
                return response()->json(['success' => false, 'message' => session('error'),
                    'session_status' => ['type' => 'error', 'message' => session('error')]], 403);
            }
            return redirect()->back()->with('error', 'You have no permission.');
        }
        $this->assertInvoiceVisible($invoice);

        //Gate::authorize('owner-admin', $invoice->lease);
        try {
            $walletSummary = $this->invoiceService->recordPayment($invoice, $request->validated());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->actionError($request, $e);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            if (!$request->expectsJson()) throw $e;
            return $this->actionError($request, \Illuminate\Validation\ValidationException::withMessages([
                'invoice' => session('error') ?? 'Payment could not be recorded. Please review the invoice.',
            ]));
        }
        if ($request->expectsJson()) {
            session()->forget('error');
            session()->flash('success', 'Payment recorded successfully.');
            if ($request->boolean('preserve_payment_summary') && $walletSummary) {
                session()->flash('wallet_auto_payment_summary', $walletSummary);
            }
            return response()->json(['success' => true, 'message' => 'Payment recorded successfully.',
                'session_status' => ['type' => 'success', 'message' => session('success')],
                'lease_id' => $invoice->lease_id,
                'wallet_balance' => $invoice->lease?->tenant?->user_id
                    ? number_format((int) $this->walletService->getBalance($invoice->lease->tenant->user_id) / 100, 2) : '0.00',
                'wallet_auto_payment_summary' => $walletSummary,
                'wallet_summary_html' => $walletSummary
                    ? view('components.modals.wallet-payment-summary-content', ['summary' => $walletSummary])->render() : null]);
        }

        return back()->with('success', 'Payment recorded successfully.')->with('wallet_auto_payment_summary', $walletSummary);
    }

    private function assertInvoiceVisible(Invoice $invoice): void
    {
        abort_unless(Invoice::query()->visibleTo(Auth::id(), get_effective_user()?->id, Gate::allows('super-admin'))
            ->whereKey($invoice->id)->exists(), 403);
    }

    public function leaseRows(Request $request, Lease $lease)
    {
        abort_unless(Gate::allows('super-admin') || Gate::allows('leases.show'), 403);
        abort_unless(Invoice::statementLeases(Auth::id(), get_effective_user()?->id, Gate::allows('super-admin'))->whereKey($lease->id)->exists(), 403);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        return response()->json(app(\App\Services\LeaseInvoiceViewService::class)->page($lease, $request->integer('page', 1)));
    }

    public function documentData(Invoice $invoice)
    {
        abort_unless(Gate::allows('super-admin') || Gate::allows('invoice.view invoice'), 403);
        $this->assertInvoiceVisible($invoice);
        $invoice->loadMissing('documentTemplate', 'items.feeType');
        abort_unless($invoice->documentTemplate, 404);
        return response()->json(['type' => 'invoice', 'title' => 'Invoice: '.$invoice->invoice_no,
            'content' => $invoice->documentTemplate->html_template,
            'variables' => $this->invoiceService->getInvoiceVariables($invoice),
            'items' => $invoice->items->map(fn ($item) => ['name' => $item->feeType?->name ?? 'Item',
                'description' => $item->description, 'amount' => number_format($item->amount / 100, 2)])]);
    }

    public function receiptData(Invoice $invoice, Transaction $transaction)
    {
        abort_unless(Gate::allows('super-admin') || Gate::allows('invoice.view receipt'), 403);
        $this->assertInvoiceVisible($invoice);
        abort_unless($transaction->invoice_id === $invoice->id, 404);
        $transaction->loadMissing('documentTemplate', 'approver');
        abort_unless($transaction->documentTemplate, 404);
        $invoice->loadMissing('items.feeType');
        return response()->json(['type' => 'receipt', 'title' => 'Receipt: '.$transaction->receipt_no,
            'content' => $transaction->documentTemplate->html_template,
            'variables' => $this->invoiceService->getInvoiceVariables($invoice),
            'items' => $invoice->items->map(fn ($item) => ['name' => $item->feeType?->name ?? 'Item',
                'description' => $item->description, 'amount' => number_format($item->amount / 100, 2)]),
            'extra' => ['receiptNo' => $transaction->receipt_no,
                'paymentDate' => $transaction->payment_date ? \Carbon\Carbon::parse($transaction->payment_date)->format('d/m/Y') : $transaction->created_at?->format('d/m/Y'),
                'paidAmount' => number_format($transaction->amount_paid / 100, 2),
                'invoiceTotal' => number_format($invoice->total_amount / 100, 2),
                'receiptVariables' => ['payment_method' => $transaction->payment_method,
                    'reference_no' => $transaction->transaction_ref ?? '—',
                    'issued_by_name' => $transaction->approver?->name ?? 'N/A',
                    'issued_by_email' => $transaction->approver?->email ?? 'N/A',
                    'issued_by_phone' => $transaction->approver?->phone ?? $transaction->approver?->phone_number ?? 'N/A']]]);
    }

    public function void(VoidInvoiceRequest $request, Invoice $invoice)
    {
        if (Gate::denies('super-admin') && Gate::denies('invoice.void')) {
            $request->rememberModal($invoice);
            return redirect()->back()->withInput()->with('error', 'You have no permission.');
        }

        //Gate::authorize('owner-admin', $invoice->lease);
        if ($invoice->settlement_refund_id && Gate::denies('super-admin') && Gate::denies('leases.create refund adjustment')) {
            $request->rememberModal($invoice);
            return back()->withInput()->with('error', 'You have no permission to change a post-refund adjustment.');
        }
        try {
            $this->invoiceService->voidInvoice($invoice, $request->validated('reason'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            $request->rememberModal($invoice);
            return $this->actionError($request, $e);
        }
        return back()->with('success', 'Invoice voided.');
    }

    public function regenerateChargeInvoice(Request $request, Lease $lease, string $chargeId)
    {
        if (Gate::denies('super-admin') && Gate::denies('leases.add manual invoice')) {
            return back()->with('error', 'You have no permission.');
        }
        $issuer = get_effective_user();
        if (!$issuer || !Invoice::statementLeases(Auth::id(), $issuer->id, Gate::allows('super-admin'))->whereKey($lease->id)->exists()) {
            return back()->with('error', 'You have no permission.');
        }
        try {
            $invoice = $this->invoiceService->regenerateChargeInvoice($lease, $chargeId, $issuer);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->actionError($request, $e);
        }

        return back()->with('success', "Invoice {$invoice->invoice_no} generated from the original lease charge."
            . ($invoice->amount_paid > 0 ? ' Wallet payment was applied automatically.' : ' Record payment using the tenant wallet when ready.'));
    }

    public function storeManualInvoice(StoreInvoiceRequest $request, Lease $lease)
    {
        if (Gate::denies('super-admin') && Gate::denies('leases.add manual invoice')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        //Gate::authorize('owner-admin', $lease);
        if ($request->boolean('post_refund_adjustment') && Gate::denies('super-admin') && Gate::denies('leases.create refund adjustment')) {
            session()->flash('error', 'You have no permission to create a post-refund adjustment.');
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => session('error')], 403)
                : back()->with('error', session('error'));
        }
        try {
            $invoice = $this->invoiceService->createManualInvoice($lease, $request->validated());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->actionError($request, $e);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Invoice {$invoice->invoice_no} created.",
                'wallet_auto_payment_summary' => request()->attributes->get('generated_wallet_summary'),
                'invoice' => [
                    'id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no,
                    'document_template_id' => $invoice->document_template_id ?? '—',
                    'template_title' => $invoice->documentTemplate?->title ?? 'Manual Invoice',
                    'template_html' => $invoice->documentTemplate?->html_template ?? '',
                    'period' => \Carbon\Carbon::parse($invoice->period)->format('m/Y'),
                    'due_date' => $invoice->due_date ? $invoice->due_date->format('d M Y') : '—',
                    'total_amount' => number_format($invoice->total_amount / 100, 2),
                    'amount_paid' => number_format($invoice->amount_paid / 100, 2),
                    'amount_balance' => number_format($invoice->amount_balance / 100, 2),
                    'status' => strtolower($invoice->status ?? 'unpaid'),
                    'remarks' => $invoice->remarks ?? '—',
                    'items' => $invoice->items->map(function ($item) {
                        return [
                            'name' => $item->feeType?->name ?? 'Item',
                            'description' => $item->description,
                            'amount' => number_format($item->amount / 100, 2),
                        ];
                    })
                ]
            ]);
        }

        return back()->with('success', "Invoice {$invoice->invoice_no} created.");
    }

    private function actionError(Request $request, \Illuminate\Validation\ValidationException $exception)
    {
        $message = implode(' ', \Illuminate\Support\Arr::flatten($exception->errors()));
        session()->forget(['success', 'status']);
        session()->flash('error', $message);
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message, 'errors' => $exception->errors(),
                'session_status' => ['type' => 'error', 'message' => session('error')]], 422);
        }
        return back()->withErrors($exception->errors())->withInput()->with('error', $message);
    }

    public function generateAutoInvoice(Request $request, Lease $lease)
    {
        if (Gate::denies('super-admin') && Gate::denies('leases.auto generate invoice')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }
        if (in_array(strtolower((string) $lease->status), ['check out', 'refunded'], true) || $lease->checked_out_at) {
            $message = 'Recurring invoices stop after checkout. Add final charges manually while the refund is Draft.';
            session()->flash('error', $message);
            return $request->expectsJson() ? response()->json(['success' => false, 'message' => $message], 422) : back()->with('error', $message);
        }


        if (!app(\App\Services\SettingService::class)->allowsManualRecurringInvoice(get_effective_user())) {
            $message = 'Manual recurring invoice generation is disabled in Auto mode.';
            session()->flash('error', $message);
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        try {
            $generatedCount = $this->invoiceService->generateRecurringInvoices($lease, throughEnd: true);

            $message = $generatedCount > 0 
                ? "Generated {$generatedCount} recurring invoice(s) for this lease."
                : "No missing recurring invoices found for the remaining lease term.";

            // Flash message to session
            session()->flash('success', $message);

            return response()->json([
                'success' => true,
                'message' => $message,
                'wallet_auto_payment_summary' => request()->attributes->get('generated_wallet_summary'),
            ]);
        } catch (\Exception $e) {
            $errorMessage = 'Failed to generate invoice: ' . $e->getMessage();
            
            // Flash error message to session
            session()->flash('error', $errorMessage);

            return response()->json([
                'success' => false,
                'message' => $errorMessage
            ], 500);
        }
    }
}
