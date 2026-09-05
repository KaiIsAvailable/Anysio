<x-app-layout>
    <div x-data="{ 
            loading: false,
            openPayment: false, 
            shakePayment: false,
            paymentData: { id: '', invoice_no: '', amount_due: 0, actionUrl: '' },
            voidModalOpen: false, 
            actionUrl: ''
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
                                <x-table.th name="Invoice No" sortField="inv" />
                                <x-table.th name="Documents" />
                                <x-table.th name="Tenant Details" sortField="t" />
                                <x-table.th name="Period" />
                                <x-table.th name="Due Date" />
                                <x-table.th name="Amount Details" />
                                <x-table.th name="Remarks" />
                                <x-table.th name="Status" sortField="s" />
                                <x-table.th name="Actions" />
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($invoices as $invoice)
                            <tr class="hover:!bg-indigo-50 transition-colors duration-150">

                                <!-- Invoice No -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col items-start gap-1.5">
                                        <span class="text-sm font-bold text-indigo-600">{{ $invoice->invoice_no }}</span>
                                    </div>
                                </td>

                                <!-- Document -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col items-start gap-1.5">
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
                                                buttonTextExpr="invNo" />
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

                                        $payDate = data_get($receipt, 'created_at')
                                        ? \Carbon\Carbon::parse(data_get($receipt, 'created_at'))->format('Y-m-d')
                                        : '—';

                                        \Log::info('Receipt Date Debug', [
                                        'receipt_no' => $receiptNo,
                                        'created_at' => data_get($receipt, 'created_at'),
                                        'payment_date_variable' => data_get($receipt, 'variables.payment_date'),
                                        'payDate' => $payDate,
                                        ]);

                                        $btnTitle = $receiptNo . ($receiptTitle ? ' (' . $receiptTitle . ')' : '');
                                        $paidAmount = is_numeric($rawAmount) ? ($rawAmount > 100 ? number_format($rawAmount/100, 2, '.', '') : number_format((float)$rawAmount, 2, '.', '')) : '0.00';
                                        $invoiceTotal = number_format($invoice->total_amount / 100, 2, '.', '');
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
                                                        invoiceNo: @js($invoice->invoice_no),
                                                        invoiceTotal: @js($invoiceTotal)
                                                    }
                                                        
                                                        
                                                }">
                                            <x-buttons.preview-doc
                                                type="receipt"
                                                color="emerald"
                                                titleExpr="'Receipt: ' + rNo"
                                                contentExpr="rContent"
                                                variablesExpr="invVars"
                                                itemsExpr="invItems"
                                                buttonTextExpr="rNo"
                                                extraExpr="extraData" />
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
                                    <div class="text-sm font-medium text-slate-900">
                                        {{ $invoice->recipient_name }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $invoice->context_label }}
                                    </div>
                                </td>

                                <!-- Period -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-slate-900">{{ $invoice->period }}</div>
                                </td>

                                <!-- Due Date -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-slate-900">{{ $invoice->due_date }}</div>
                                </td>

                                <!-- Amount -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-gray-900 font-semibold">RM {{ number_format($invoice->total_amount / 100, 2) }}</div>
                                    <div class="text-emerald-600 font-medium text-xs">Paid: RM {{ number_format($invoice->amount_paid / 100, 2) }}</div>
                                    <div class="text-red-600 font-medium text-xs">Balance: RM {{ number_format($invoice->amount_balance / 100, 2) }}</div>
                                </td>

                                <!-- Remarks -->
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-slate-900">{{ $invoice->remarks }}</div>
                                </td>

                                <!-- Status Badge -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase
                                                @if($invoice->status === 'paid') bg-green-100 text-green-700 border-green-200
                                                @elseif($invoice->status === 'unpaid') bg-yellow-100 text-yellow-700 border-yellow-200
                                                @elseif($invoice->status === 'partial') bg-blue-100 text-blue-700 border-blue-200
                                                @elseif($invoice->status === 'overdue') bg-red-100 text-red-700 border-red-200
                                                @elseif($invoice->status === 'rejected') bg-red-100 text-red-700 border-red-200
                                                @elseif($invoice->status === 'void') bg-gray-100 text-gray-500 border-gray-200 line-through
                                                @endif">
                                        {{ $invoice->status }}
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="flex justify-center items-center gap-2">
                                        @if(in_array($invoice->status, ['unpaid', 'partial', 'overdue']))
                                        @php
                                        $paymentPayload = json_encode([
                                        'id' => $invoice->id,
                                        'invoiceNo' => $invoice->invoice_no,
                                        'totalAmount' => number_format($invoice->amount_balance / 100, 2),
                                        'invoiceItems' => $invoice->invoice_items,
                                        'walletBalance' => $invoice->wallet_balance,
                                        'actionUrl' => route('admin.invoices.payment', $invoice->id)
                                        ]);
                                        @endphp
                                        <button type="button"
                                            @click="$dispatch('open-payment', {{ $paymentPayload }})"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200/60 rounded-lg transition-all shadow-sm">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                            </svg>
                                            <span>Record Payment</span>
                                        </button>
                                        @endif

                                        <!-- Void Button (Fixed Blade Conditional instead of Alpine x-if on server loop) -->
                                        @if(!in_array($invoice->status, ['void']))
                                        <button type="button"
                                            @click="
                                                    $dispatch('open-void-modal', { actionUrl: '{{ route('admin.invoices.void', $invoice->id) }}', invoiceNumber: '{{ $invoice->invoice_no }}' });
                                                "
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200/60 rounded-lg transition-all shadow-sm">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                            <span>Void</span>
                                        </button>
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

        <x-modals.confirmation-modal id="void-modal" title="Void Invoice" maxWidth="sm:max-w-lg">
            <div x-data="{ actionUrl: '', invoiceNumber: '', loading: false }"
                @open-void-modal.window="
                    actionUrl = $event.detail.actionUrl;
                    invoiceNumber = $event.detail.invoiceNumber;
                ">

                <!-- Optional: Display invoice number in the modal content -->
                <x-form.form :action="''" x-bind:action="actionUrl" method="POST" loading="loading">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="redirect" value="{{ request()->url() }}">

                    <div class="p-6 space-y-4">
                        <p class="text-sm text-slate-600 font-medium">
                            Are you sure you want to void invoice <span class="font-bold text-slate-900" x-text="invoiceNumber"></span>? This action cannot be undone.
                        </p>

                        <div>
                            <x-form.input-label value="Reason for Voiding" class="mb-1" />
                            <textarea name="reason" rows="3" required
                                class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50/50 border-t border-gray-100 flex justify-end space-x-3">
                        <button type="button" @click="$dispatch('close-void-modal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-xs font-semibold uppercase hover:bg-gray-300 transition-colors">
                            Cancel
                        </button>
                        <x-form.primary-button type="submit" loading="loading" class="px-4 py-2 bg-rose-600 text-white rounded-md text-xs font-semibold uppercase hover:bg-rose-700">
                            Confirm Void
                        </x-form.primary-button>
                    </div>
                </x-form.form>
            </div>
        </x-modals.confirmation-modal>

        <x-modals.payment-modal />

    </div>
</x-app-layout>