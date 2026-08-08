<?php
namespace App\Http\Controllers;

use App\Http\Requests\Invoice\{StoreInvoiceRequest, RecordPaymentRequest, VoidInvoiceRequest};
use App\Models\{Invoice, Lease, Payment, Owners};
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Traits\RoleBasedDataTrait;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    public function index()
    {
        Gate::authorize('owner-admin');
        
        $user = Auth::user();

        $query = Invoice::with([
            'lease.leasable', // Ensure leasable is loaded so you can check ownership if needed
            'items.feeType',
            'transactions', 
            'payments' => function ($query) {
                $query->where('status', 'pending');
            },
        ]);

        // Apply your role-based ownership filter
        // Depending on your schema, invoices might relate to owners via leases -> properties.
        // If your invoices table has a direct column (e.g., created_by or user_id), pass that.
        // If it's filtered through the lease relation, you can scope it like below:
        
        if ($user->role === 'ownerAdmin') {
            $query->whereHas('lease.leasable', function ($q) use ($user) {
                // Adjust this based on how your Room/Unit/Property links to the owner user ID
                $q->where('user_id', $user->id); 
            });
        } elseif ($user->role === 'agentAdmin') {
            $managedOwnerIds = Owners::where('agent_id', $user->id)->pluck('user_id');
            
            $query->whereHas('lease.leasable', function ($q) use ($user, $managedOwnerIds) {
                $q->where('user_id', $user->id)
                ->orWhereIn('user_id', $managedOwnerIds);
            });
        }
        // Super admins bypass this and see everything naturally

        $invoices = $query->latest()
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

    public function storeManualInvoice(StoreInvoiceRequest $request, Lease $lease)
    {
        Gate::authorize('owner-admin', $lease);
        $invoice = $this->invoiceService->createManualInvoice($lease, $request->validated());
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Invoice {$invoice->invoice_no} created.",
                'invoice' => $invoice
            ]);
        }

        return redirect()->route('leases.invoices.show', [$lease, $invoice])
                        ->with('success', "Invoice {$invoice->invoice_no} created.");
    }
}