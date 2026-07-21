<?php
namespace App\Http\Controllers;

use App\Http\Requests\Invoice\{StoreInvoiceRequest, RecordPaymentRequest, VoidInvoiceRequest};
use App\Models\{Invoice, Lease, Payment};
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Gate;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    public function index()
    {
        Gate::authorize('owner-admin');
        $invoices = Invoice::with([
            'lease',
            'items.feeType',
            'transactions', 
            'payments' => function ($query) {
                $query->where('status', 'pending');
            },])
                ->latest()
                ->paginate(20)
                ->onEachSide(1);
                
        return view('adminSide.leases.invoices.index', compact('invoices'));
    }

    public function show(Lease $lease, Invoice $invoice)
    {
        Gate::authorize('owner-admin', $lease);
        $invoice->load(['items.feeType', 'transactions.approver', 'lease.tenant.user']);
        return view('invoices.show', compact('lease', 'invoice'));
    }

    public function store(StoreInvoiceRequest $request, Lease $lease)
    {
        Gate::authorize('owner-admin', $lease);
        $invoice = $this->invoiceService->createManualInvoice($lease, $request->validated());
        return redirect()->route('leases.invoices.show', [$lease, $invoice])
                         ->with('success', "Invoice {$invoice->invoice_no} created.");
    }

    public function recordPayment(RecordPaymentRequest $request, Invoice $invoice)
    {
        Gate::authorize('owner-admin', $invoice->lease);
        $this->invoiceService->recordPayment($invoice, $request->validated());
        return back()->with('success', 'Payment recorded successfully.');
    }

    public function void(VoidInvoiceRequest $request, Invoice $invoice)
    {
        Gate::authorize('owner-admin', $invoice->lease);
        $this->invoiceService->voidInvoice($invoice, $request->validated('reason'));
        return back()->with('success', 'Invoice voided.');
    }

    public function generateInvoice(Lease $lease)
    {
        Gate::authorize('owner-admin', $lease);
        $period = $this->invoiceService->nextBillingPeriod($lease);
        abort_unless($period !== null, 422, 'All billing periods already generated.');
        $invoice = $this->invoiceService->createInvoice($lease, $period);
        return back()->with('success', "Invoice {$invoice->invoice_no} for {$period->format('M Y')} generated.");
    }
}