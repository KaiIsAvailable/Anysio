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

    public function index()
    {
        if (Gate::denies('owner-admin') && Gate::denies('invoice.tab')) {
            return view('errors.403');
        }

        $actualUserId = Auth::id(); 
        $effectiveUser = get_effective_user();
        $effectiveUserId = $effectiveUser?->id;

        $query = Invoice::with([
            'documentTemplate',
            'user',
            'billable',
            'lease.leasable.owner',
            'lease.tenant.user',
            'items.feeType',
            'transactions.documentTemplate',
            'transactions.approver',
            'payments' => function ($query) {
                $query->where('status', 'pending');
            },
        ]);

        if (!Gate::allows('super-admin')) {
            $query->whereHas('lease', function ($leaseQuery) use ($effectiveUserId, $actualUserId) {
                $leaseQuery->where(function ($q) use ($effectiveUserId, $actualUserId) {
                    $q->whereHasMorph('leasable', [Room::class, Unit::class, Property::class], function ($mq, $type) use ($effectiveUserId, $actualUserId) {
                        if ($type === Room::class) {
                            $mq->whereHas('unit.owner', function ($oq) use ($effectiveUserId, $actualUserId) {
                                $oq->where(function ($subQ) use ($effectiveUserId, $actualUserId) {
                                    $subQ->where('created_by', $effectiveUserId)
                                        ->orWhere('created_by', $actualUserId)
                                        ->orWhere('owner_id', $effectiveUserId);
                                });
                            });
                        } else {
                            $mq->whereHas('owner', function ($oq) use ($effectiveUserId, $actualUserId) {
                                $oq->where(function ($subQ) use ($effectiveUserId, $actualUserId) {
                                    $subQ->where('created_by', $effectiveUserId)
                                        ->orWhere('created_by', $actualUserId)
                                        ->orWhere('owner_id', $effectiveUserId);
                                });
                            });
                        }
                    })
                    ->orWhereHas('tenant', function ($tq) use ($effectiveUserId, $actualUserId) {
                        $tq->where('created_by', $effectiveUserId)
                           ->orWhere('created_by', $actualUserId);
                    });
                });
            });
        }

        $paginatedInvoices = $query->latest()
            ->paginate(10)
            ->onEachSide(1);

        $paginatedInvoices->setCollection(
            $paginatedInvoices->getCollection()->map(function ($invoice) {
                return (object) $this->invoiceService->transformInvoice($invoice);
            })
        );
        
        $invoices = $paginatedInvoices;

        return view('adminSide.leases.invoices.index', compact('invoices'));
    }

    public function show(Lease $lease, Invoice $invoice)
    {
        //Gate::authorize('owner-admin', $lease);
        $invoice->load(['items.feeType', 'transactions.approver', 'lease.tenant.user']);
        return view('invoices.show', compact('lease', 'invoice'));
    }

    public function recordPayment(RecordPaymentRequest $request, Invoice $invoice)
    {
        if (Gate::denies('owner-admin') && Gate::denies('leases.record payment')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        //Gate::authorize('owner-admin', $invoice->lease);
        $this->invoiceService->recordPayment($invoice, $request->validated());
        //dd($request->all());
        return back()->with('success', 'Payment recorded successfully.');
    }

    public function void(VoidInvoiceRequest $request, Invoice $invoice)
    {
        if (Gate::denies('owner-admin') && Gate::denies('leases.void')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        //Gate::authorize('owner-admin', $invoice->lease);
        $this->invoiceService->voidInvoice($invoice, $request->validated('reason'));
        return back()->with('success', 'Invoice voided.');
    }

    public function storeManualInvoice(StoreInvoiceRequest $request, Lease $lease)
    {
        if (Gate::denies('owner-admin') && Gate::denies('leases.add manual invoice')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        //Gate::authorize('owner-admin', $lease);
        $invoice = $this->invoiceService->createManualInvoice($lease, $request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Invoice {$invoice->invoice_no} created.",
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
                            'description' => $item->description ?? $item->feeType?->name ?? 'Item',
                            'amount' => number_format($item->amount / 100, 2),
                        ];
                    })
                ]
            ]);
        }

        return back()->with('success', "Invoice {$invoice->invoice_no} created.");
    }

    public function generateAutoInvoice(Request $request, Lease $lease)
    {
        if (Gate::denies('owner-admin') && Gate::denies('leases.auto generate invoice')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }


        try {
            $generatedCount = $this->invoiceService->generateRecurringInvoices($lease);

            $message = $generatedCount > 0 
                ? "Successfully generated invoice for lease." 
                : "No due recurring charges found for this lease.";

            // Flash message to session
            session()->flash('success', $message);

            return response()->json([
                'success' => true,
                'message' => $message
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
