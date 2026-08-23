<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoice\{StoreInvoiceRequest, RecordPaymentRequest, VoidInvoiceRequest};
use App\Models\{Invoice, Lease, Payment, Owners, Transaction};
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    public function index()
    {
        Gate::authorize('owner-admin');
        
        $user = get_effective_user();

        // 🌟 核心修正 1：改為預載入 transactions.documentTemplate
        $query = Invoice::with([
            'documentTemplate',
            'user',
            'billable',
            'lease.leasable.owner', 
            'lease.tenant.user',
            'items.feeType',
            'transactions.documentTemplate', // <-- 正確的關聯名稱在這裡！
            'payments' => function ($query) {
                $query->where('status', 'pending');
            },
        ]);

        if ($user->role === 'ownerAdmin') {
            $query->where(function ($q) use ($user) {
                $q->whereHas('lease.leasable', function ($sq) use ($user) {
                    $sq->where('user_id', $user->id); 
                })->orWhere('user_id', $user->id); 
            });
        } elseif ($user->role === 'agentAdmin') {
            $managedOwnerIds = Owners::where('agent_id', $user->id)->pluck('user_id');
            
            $query->where(function ($q) use ($user, $managedOwnerIds) {
                $q->whereHas('lease.leasable', function ($sq) use ($user, $managedOwnerIds) {
                    $sq->where('user_id', $user->id)
                      ->orWhereIn('user_id', $managedOwnerIds);
                })->orWhere('user_id', $user->id)
                  ->orWhereIn('user_id', $managedOwnerIds); 
            });
        }

        $paginatedInvoices = $query->latest()
            ->paginate(20)
            ->onEachSide(1);

        $paginatedInvoices->setCollection(
            $paginatedInvoices->getCollection()->map(function ($invoice) {
                return (object) $this->transformInvoice($invoice);
            })
        );

        $invoices = $paginatedInvoices;

        return view('adminSide.leases.invoices.index', compact('invoices'));
    }

    protected function transformInvoice($invoice)
    {
        $rawPeriod = $invoice->period_display ?? $invoice->period;

        $formattedPeriod = '—';
        if ($rawPeriod) {
            try {
                $formattedPeriod = \Carbon\Carbon::parse($rawPeriod)->format('m/Y');
            } catch (\Exception $e) {
                $formattedPeriod = $rawPeriod;
            }
        }

        $invoiceItems = $invoice->items->map(function ($subItem) {
            return [
                'description' => $subItem->description ?? 'Item',
                'amount' => number_format(($subItem->amount ?? 0) / 100, 2),
            ];
        });

        if ($invoiceItems->isEmpty() && $invoice->description) {
            $invoiceItems->push([
                'description' => $invoice->description,
                'amount' => number_format(($invoice->total_amount ?? 0) / 100, 2),
            ]);
        }

        $latestPayment = $invoice->payments->first();

        // 🌟 核心修正 2：讀取 transactions 並轉換為前端需要的 receipts 陣列
        $receipts = $invoice->transactions ? $invoice->transactions->map(function($transaction) {
            return [
                'id' => $transaction->id,
                'receipt_no' => $transaction->receipt_no ?? 'Receipt-' . $transaction->id, // 防止空值
                'amount' => $transaction->amount_paid, // 抓取 Transaction 表的 amount_paid
                'created_at' => $transaction->payment_date ?? $transaction->created_at, // 優先使用 payment_date
                'variables' => method_exists($transaction, 'variables') ? $transaction->variables : [],
                'documentTemplate' => $transaction->documentTemplate ? [
                    'title' => $transaction->documentTemplate->title,
                    'html_template' => $transaction->documentTemplate->html_template,
                    'html_content' => $transaction->documentTemplate->html_content,
                ] : null,
            ];
        })->toArray() : [];

        return [
            'id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no ?? $invoice->serial_number,
            'total_amount' => $invoice->total_amount,
            'amount_due' => $invoice->amount_due ?? $invoice->total_amount,
            'amount_balance' => $invoice->amount_balance,
            'amount_paid' => $invoice->amount_paid,
            'status' => $invoice->status,
            'invoice_items' => $invoiceItems,
            'actionUrl' => route('admin.invoices.payment', $invoice->id),
            'context' => $invoice->context,
            'created_at' => $invoice->created_at,
            'period' => $formattedPeriod,
            'due_date' => $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') : '—',
            'remarks' => $invoice->remarks,
            'document_template_id' => $invoice->document_template_id ?? '—',
            'documentTemplate' => $invoice->documentTemplate,
            'receipt_path' => $invoice->receipt_path ?? $latestPayment?->receipt_path,
            'latestPayment' => $latestPayment,
            'user' => $invoice->lease?->user ?? null,
            'template_title' => $invoice->documentTemplate?->title,
            'template_html' => $invoice->documentTemplate?->html_content ?? $invoice->documentTemplate?->html_template,
            
            // 打包變數
            'variables' => $this->invoiceService->getInvoiceVariables($invoice),
            
            // 🌟 核心修正 3：將轉換好的 transactions 傳遞給前端的 receipts 變數
            'receipts' => $receipts, 
        ];
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
}