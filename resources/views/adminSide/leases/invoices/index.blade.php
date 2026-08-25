<x-app-layout>
    <div x-data="{ 
            loading: false,
            openPayment: false, 
            shakePayment: false,
            paymentData: { id: '', invoice_no: '', amount_due: 0, actionUrl: '' }
         }"
        @open-payment.window="paymentData = $event.detail; openPayment = true;"
        class="py-12 bg-gray-50 min-h-screen font-sans text-slate-900">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Invoices</h1>
                    <p class="mt-2 text-sm text-gray-500">View and process tenant invoices and payment records.</p>
                </div>
            </div>

            <!-- Main Content Card -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="p-5 border-b border-gray-100 bg-white">
                    <div class="flex justify-end">
                        <x-form.form method="GET" action="{{ route('admin.invoices.index') }}" class="flex flex-wrap items-center gap-4">
                            <div class="flex items-stretch justify-between">
                                <x-table.search placeholder="Search invoices..." />
                            </div>
                        </x-form.form>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    @if($invoices->count() > 0)
                    <table class="table-auto w-full min-w-[1200px] divide-y divide-gray-200 text-left">
                        <thead class="bg-gray-50">
                            <tr>
                                <x-table.th name="Documents" sortField="inv" />
                                <x-table.th name="Tenant Details" sortField="t" />
                                <x-table.th name="Amount (RM)" sortField="a" />
                                <x-table.th name="Status" sortField="s" />
                                <x-table.th name="Date" sortField="d" />
                                <x-table.th name="Actions" />
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($invoices as $invoice)
                            <tr class="hover:!bg-indigo-50 transition-colors duration-150">

                                <!-- 💡 Documents 欄位 (使用 Alpine x-data 防撞引號) -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col items-start gap-1.5">
                                        <span class="text-sm font-bold text-indigo-600">{{ $invoice->invoice_no }}</span>

                                        @php
                                            $template = data_get($invoice, 'documentTemplate');
                                            $templateTitle = is_object($template) ? ($template->title ?? null) : (is_array($template) ? ($template['title'] ?? null) : null);
                                            $receipts = data_get($invoice, 'receipts', []); 
                                        @endphp

                                        <!-- 1. Invoice Template Component -->
                                        @if(data_get($invoice, 'document_template_id') && $templateTitle)
                                            <div x-data="{
                                                invNo: @js($invoice->invoice_no),
                                                content: @js(optional($invoice->documentTemplate)->html_template ?? optional($invoice->documentTemplate)->html_content ?? ''),
                                                vars: @js(data_get($invoice, 'variables', [])),
                                                items: @js(data_get($invoice, 'invoice_items', data_get($invoice, 'items', []))),
                                                btnTitle: @js($templateTitle)
                                            }">
                                                <x-buttons.preview-doc
                                                    type="invoice"
                                                    color="indigo"
                                                    titleExpr="'Invoice: ' + invNo"
                                                    contentExpr="content"
                                                    variablesExpr="vars"
                                                    itemsExpr="items"
                                                    buttonTextExpr="btnTitle"
                                                />
                                            </div>
                                        @endif

                                        <!-- 2. Loop Through Multiple Receipts Components -->
                                        @if(!empty($receipts))
                                            @foreach($receipts as $receipt)
                                                @php
                                                    $receiptTemplate = data_get($receipt, 'documentTemplate');
                                                    $receiptTitle = is_object($receiptTemplate) ? ($receiptTemplate->title ?? null) : (is_array($receiptTemplate) ? ($receiptTemplate['title'] ?? null) : null);
                                                    $receiptNo = data_get($receipt, 'receipt_no', 'Receipt');
                                                    $rawAmount = data_get($receipt, 'amount', 0);
                                                    $rContent = is_object($receiptTemplate) ? ($receiptTemplate->html_template ?? $receiptTemplate->html_content ?? '') : (is_array($receiptTemplate) ? ($receiptTemplate['html_template'] ?? $receiptTemplate['html_content'] ?? '') : data_get($receipt, 'template_html', ''));
                                                    
                                                    $payDate = data_get($receipt, 'created_at') ? \Carbon\Carbon::parse(data_get($receipt, 'created_at'))->format('Y-m-d') : '—';
                                                    $btnTitle = $receiptNo . ($receiptTitle ? ' (' . $receiptTitle . ')' : '');
                                                    $paidAmount = is_numeric($rawAmount) ? ($rawAmount > 100 ? number_format($rawAmount/100, 2, '.', '') : number_format((float)$rawAmount, 2, '.', '')) : '0.00';
                                                @endphp
                                                <div class="mt-1" x-data="{
                                                    rNo: @js($receiptNo),
                                                    rContent: @js($rContent),
                                                    invVars: @js(data_get($invoice, 'variables', [])),
                                                    invItems: @js(data_get($invoice, 'invoice_items', data_get($invoice, 'items', []))),
                                                    btnTitle: @js($btnTitle),
                                                    extraData: {
                                                        receiptNo: @js($receiptNo),
                                                        paymentDate: @js($payDate),
                                                        receiptVariables: @js(data_get($receipt, 'variables', [])),
                                                        paidAmount: @js($paidAmount),
                                                        invoiceNo: @js($invoice->invoice_no)
                                                    }
                                                }">
                                                    <x-buttons.preview-doc
                                                        type="receipt"
                                                        color="emerald"
                                                        titleExpr="'Receipt: ' + rNo"
                                                        contentExpr="rContent"
                                                        variablesExpr="invVars"
                                                        itemsExpr="invItems"
                                                        buttonTextExpr="btnTitle"
                                                        extraExpr="extraData"
                                                    />
                                                </div>
                                            @endforeach
                                        @endif
                                        
                                        @if(!data_get($invoice, 'document_template_id') && empty($receipts))
                                            <span class="text-xs text-gray-400 italic mt-1">- None -</span>
                                        @endif
                                    </div>
                                </td>

                                <!-- Tenant Details -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-slate-900">{{ $invoice->user->name ?? 'N/A' }}</div>
                                    <div class="text-xs text-gray-500">
                                        @php
                                        $leasable = $invoice->lease->leasable ?? null;
                                        @endphp
                                        @if($leasable instanceof \App\Models\Room)
                                        Room: {{ $leasable->room_no }} (Unit: {{ $leasable->unit->unit_no ?? 'N/A' }})
                                        @elseif($leasable instanceof \App\Models\Unit)
                                        Unit: {{ $leasable->unit_no }}
                                        @elseif($leasable instanceof \App\Models\Property)
                                        Property: {{ $leasable->name }}
                                        @else
                                        Subscription: {{ $invoice->context ?? 'N/A' }}
                                        @endif
                                    </div>
                                </td>

                                <!-- Amount -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 font-semibold">RM {{ number_format($invoice->total_amount / 100, 2) }}</div>
                                    @if($invoice->amount_paid > 0)
                                    <div class="text-[10px] text-green-600">Paid: RM {{ number_format($invoice->amount_paid / 100, 2) }}</div>
                                    @endif
                                </td>

                                <!-- Status Badge -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase
                                                @if($invoice->status === 'paid') bg-green-100 text-green-700 border-green-200
                                                @elseif($invoice->status === 'unpaid') bg-yellow-100 text-yellow-700 border-yellow-200
                                                @elseif($invoice->status === 'rejected') bg-red-100 text-red-700 border-red-200
                                                @elseif($invoice->status === 'void') bg-gray-100 text-gray-500 border-gray-200 line-through
                                                @endif">
                                        {{ $invoice->status }}
                                    </span>
                                </td>

                                <!-- Date -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $invoice->created_at->format('d M Y') }}
                                </td>

                                <!-- Actions -->
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="flex justify-center items-center gap-2">
                                        @if($invoice->status === 'unpaid')
                                        @php
                                        $paymentPayload = json_encode([
                                        'id' => $invoice->id,
                                        'invoiceNo' => $invoice->invoice_no,
                                        'totalAmount' => number_format($invoice->amount_balance / 100, 2),
                                        'invoiceItems' => $invoice->invoice_items,
                                        'actionUrl' => route('admin.invoices.payment', $invoice->id)
                                        ]);
                                        @endphp
                                        <button type="button"
                                            @click="$dispatch('open-payment', {{ $paymentPayload }})"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-600 transition hover:bg-emerald-100">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                            <span class="text-[10px] font-bold uppercase tracking-tighter">Record Payment</span>
                                        </button>
                                        @endif
                                        @if(!in_array($invoice->status, ['paid', 'void']))
                                        <form method="POST" action="{{ route('admin.invoices.void', $invoice->id) }}" onsubmit="return confirm('Void this invoice?')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600 transition hover:bg-slate-200">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <circle cx="12" cy="12" r="9" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.5 5.5l13 13" />
                                                </svg>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @else
                    <div class="text-center py-20 bg-white">
                        <p class="text-gray-500">No payment records found.</p>
                    </div>
                    @endif
                </div>

                @if($invoices->hasPages())
                <div class="p-4 border-t border-gray-100">
                    {{ $invoices->links() }}
                </div>
                @endif
            </div>
        </div>

        <x-modals.payment-modal />

    </div>
</x-app-layout>