@props(['endpoint' => null, 'leaseId' => null, 'showTenant' => false, 'initialPage' => 1, 'ajax' => true, 'invoices' => null])
@php
    $invoiceColumns = 6 + ($showTenant ? 1 : 0)
        + (\Illuminate\Support\Facades\Gate::any(['super-admin', 'invoice.view invoice', 'invoice.view receipt', 'invoice.view payment proof']) ? 1 : 0)
        + (\Illuminate\Support\Facades\Gate::any(['super-admin', 'invoice.record payment', 'invoice.void']) ? 1 : 0);
    $tableConfig = ['endpoint' => $endpoint, 'leaseId' => $leaseId, 'initialPage' => (int) $initialPage,
        'ajax' => (bool) $ajax, 'paymentUrl' => route('admin.invoices.payment', '__INVOICE__')];
    if (!$ajax && $invoices) {
        $tableConfig['initialRows'] = $invoices->items();
        $tableConfig['initialPage'] = $invoices->currentPage();
        $tableConfig['pagination'] = ['last_page' => $invoices->lastPage(), 'total' => $invoices->total()];
    }
@endphp
<div x-data="invoiceTable(@js($tableConfig))" {{ $attributes }}
    @open-payment.window="paymentData = $event.detail; openPayment = true"
    @payment-recorded.window="paymentRecorded($event)"
    @invoice-generated.window="if ($event.detail?.success) refreshTable()"
    @invoice-proof-open.window="showProof($event.detail)">
    <div x-cloak x-show="invoiceError" class="text-sm text-red-700 p-3" role="alert">
        <span x-text="invoiceError"></span>
        <button type="button" @click="refreshTable()" class="underline">Retry</button>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[1100px] divide-y divide-gray-200 text-left">
            <thead class="bg-gray-50">
                <tr>
                    <x-table.th name="Invoice No" :sort-field="$showTenant ? 'inv' : null" />
                    @canany(['super-admin', 'invoice.view invoice', 'invoice.view receipt', 'invoice.view payment proof'])
                    <x-table.th name="Documents" />
                    @endcanany
                    @if($showTenant)
                        <x-table.th name="Tenant Details" sort-field="t" />
                    @endif
                    <x-table.th name="Period" />
                    <x-table.th name="Due Date" />
                    <x-table.th name="Amount Details" />
                    <x-table.th name="Status" :sort-field="$showTenant ? 's' : null" />
                    @canany(['super-admin', 'invoice.record payment', 'invoice.void'])
                    <x-table.th name="Actions" />
                    @endcanany
                    <x-table.th name="Remarks" />
                </tr>
            </thead>
            <tbody x-cloak x-show="loading" class="bg-white">
                <tr>

                    <td colspan="{{ $invoiceColumns }}">
                        <x-loading-skeleton variant="table-rows" :columns="$invoiceColumns" label="Loading invoices..." />
                    </td>
                </tr>
            </tbody>
            <template data-invoice-table-body x-for="invoice in paginatedInvoices" :key="invoice.id">
                <tbody x-cloak x-show="!loading" class="bg-white divide-y divide-gray-200">
                    <tr class="hover:!bg-indigo-50 transition-colors duration-150">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-indigo-600">
                            <span x-text="invoice.invoice_no"></span>
                            <span x-show="invoice.charge_label" x-text="invoice.charge_label" class="block mt-1 text-xs font-medium text-amber-700"></span>
                        </td>

                        <!-- 💡 Alpine Loop Document Preview (Using Component) -->
                        @canany(['super-admin', 'invoice.view invoice', 'invoice.view receipt', 'invoice.view payment proof'])
                        <td class="px-6 py-4 whitespace-nowrap">
                            <!-- Invoice Component -->
                            <div class="flex flex-col items-start gap-1.5">
                                @canany(['super-admin', 'invoice.view invoice'])
                                <template x-if="invoice.document_url">
                                    <button type="button" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg transition-colors" @click="window.openLeaseInvoiceDocument(invoice.document_url, 'Invoice: ' + invoice.invoice_no)" x-text="invoice.invoice_no"></button>
                                </template>
                                @endcanany
                                @canany(['super-admin', 'invoice.view receipt'])
                                <template x-for="receipt in (invoice.receipts || [])" :key="receipt.id">
                                    <button type="button" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition-colors" x-show="receipt.document_url" @click="window.openLeaseInvoiceDocument(receipt.document_url, 'Receipt: ' + receipt.receipt_no)" x-text="receipt.receipt_no || 'Receipt'"></button>
                                </template>
                                @endcanany

                                @canany(['super-admin', 'invoice.view payment proof'])
                                <template x-for="proof in (invoice.payment_proofs || [])" :key="proof.id">
                                    <button type="button" @click="$dispatch('invoice-proof-open', proof)" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg border border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100">View Payment Proof</button>
                                </template>
                                @endcanany
                                <template x-if="(!invoice.document_template_id || invoice.document_template_id === '—' || !invoice.template_title) && (!invoice.receipts || invoice.receipts.length === 0) && !(invoice.payment_proofs || []).length">
                                    <span class="text-xs text-gray-400 italic mt-1">- None -</span>
                                </template>
                            </div>
                        </td>
                        @endcanany

                        @if($showTenant)
                        <td class="px-6 py-4 text-sm">
                            <a x-show="invoice.tenant?.url" :href="invoice.tenant?.url" class="font-semibold text-indigo-600 hover:underline" x-text="invoice.tenant?.name"></a>
                            <span x-show="!invoice.tenant?.url" x-text="invoice.tenant?.name || 'N/A'"></span>
                            <div><a :href="invoice.tenant?.context_url" class="text-xs text-gray-500 hover:underline" x-text="invoice.tenant?.context"></a></div>
                        </td>
                        @endif
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="invoice.period"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600" x-text="invoice.due_date_formatted"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <div class="space-y-1">
                                <div class="text-gray-900 font-semibold">Total: <span class="font-normal" x-text="'RM ' + invoice.total_amount"></span></div>
                                <div class="text-emerald-600 font-medium text-xs">Paid: <span x-text="'RM ' + invoice.amount_paid"></span></div>
                                <div class="text-red-600 font-medium text-xs">Balance: <span x-text="'RM ' + invoice.amount_balance"></span></div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border uppercase"
                                :class="{
                                'bg-green-100 text-green-700 border-green-200': invoice.status === 'paid',
                                'bg-yellow-100 text-yellow-700 border-yellow-200': invoice.status === 'unpaid',
                                'bg-red-100 text-red-700 border-red-200': invoice.status === 'rejected',
                                'bg-gray-100 text-gray-500 border-gray-200 line-through': invoice.status === 'void'
                            }" x-text="invoice.status">
                            </span>
                        </td>
                        @canany(['super-admin', 'invoice.record payment', 'invoice.void'])
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                            <div class="flex flex-col gap-1.5">
                            <!-- Record Payment Button -->
                            @canany(['super-admin', 'invoice.record payment'])
                            <template x-if="invoice && ['unpaid', 'partial', 'overdue'].includes((invoice.status || '').toLowerCase()) && Number(String(invoice.amount_balance || 0).replace(/,/g, '')) > 0">
                                <button type="button" :disabled="loading"
                                    @click="recordPayment(invoice)"
                                    class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200/60 rounded-lg transition-all shadow-sm">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                    </svg>
                                    <span>Record Payment</span>
                                </button>
                            </template>
                            @endcanany

                            <!-- Void Button -->
                            @canany(['super-admin', 'invoice.void'])
                            <template x-if="invoice && ['unpaid', 'partial', 'paid', 'overdue'].includes((invoice.status || '').toLowerCase())">
                                <button type="button" :disabled="loading"
                                    @click="
                                    $dispatch('open-void-modal', { 
                                        actionUrl: '{{ route('admin.invoices.void', ':id') }}'.replace(':id', invoice.id),
                                        invoiceNumber: invoice.invoice_no
                                    });
                                "
                                    class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200/60 rounded-lg transition-all shadow-sm">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    <span>Void</span>
                                </button>
                            </template>
                            @endcanany
                            <span x-show="(invoice.status || '').toLowerCase() === 'void'" class="text-gray-400">&mdash;</span>
                            </div>
                        </td>
                        @endcanany
                        <td class="px-6 py-4 text-sm text-gray-600" x-text="invoice.remarks"></td>
                    </tr>
                    @canany(['super-admin', 'invoice.view invoice', 'invoice.view receipt', 'invoice.view payment proof'])
                    <template x-if="invoice.original_invoice || (invoice.related_charges || []).length">
                        <tr class="bg-slate-50">
                            <td colspan="{{ $invoiceColumns }}" class="px-6 py-2">
                                <div class="flex items-start gap-3">
                                    <span class="shrink-0 text-xs font-medium text-slate-400" x-text="invoice.original_invoice ? '↳ Original invoice' : '↳ Related charges'"></span>
                                    <div class="min-w-0 space-y-1.5">
                                        <template x-for="charge in (invoice.original_invoice ? [invoice.original_invoice] : (invoice.related_charges || []))" :key="charge.id">
                                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                                                <p class="text-xs font-semibold text-amber-900" x-text="charge.label + ' · RM ' + charge.amount"></p>
                                                <p class="text-xs text-slate-500">
                                                    @canany(['super-admin', 'invoice.view invoice'])
                                                    <button type="button" x-show="charge.document_url" @click="window.openLeaseInvoiceDocument(charge.document_url, 'Invoice: ' + charge.invoice_no)" class="text-xs font-semibold text-indigo-700 hover:underline" x-text="charge.invoice_no"></button>
                                                    <span x-show="!charge.document_url" x-text="charge.invoice_no"></span>
                                                    @else
                                                    <span x-text="charge.invoice_no"></span>
                                                    @endcanany
                                                    · <span class="capitalize" x-text="charge.status"></span>
                                                </p>
                                                <div class="flex flex-wrap gap-1.5">
                                                    @canany(['super-admin', 'invoice.view receipt'])
                                                    <template x-for="receipt in charge.receipts" :key="receipt.id">
                                                        <button type="button" x-show="receipt.document_url" @click="window.openLeaseInvoiceDocument(receipt.document_url, 'Receipt: ' + receipt.receipt_no)" class="text-xs font-semibold text-emerald-700 hover:underline" x-text="receipt.receipt_no || 'View receipt'"></button>
                                                    </template>
                                                    @endcanany
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                    @endcanany
                </tbody>
            </template>
            <tbody>
                <template x-if="!loading && !invoiceError && (!invoices || invoices.length === 0)">
                    <tr>
                        <td colspan="{{ $invoiceColumns }}" class="px-6 py-12 text-center text-sm text-gray-500 italic">No invoices found.</td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    <!-- Pagination -->
    @if($ajax)
    <div x-cloak class="mt-4 flex items-center justify-between px-2" x-show="totalInvoicePages > 1">
        <div class="text-xs text-gray-500">Showing page <span class="font-bold" x-text="invoicePage"></span> of <span class="font-bold" x-text="totalInvoicePages"></span></div>
        <div class="flex items-center space-x-1">
            <button type="button" @click="if(invoicePage > 1) invoicePage--" :disabled="invoicePage === 1" class="px-3 py-1.5 text-xs font-semibold bg-white border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">&lsaquo;</button>
            <template x-for="page in paginationLinks" :key="page">
                <div>
                    <template x-if="page !== '...'"><button type="button" @click="invoicePage = page" class="px-3 py-1.5 text-xs font-semibold border rounded-md transition-all" :class="invoicePage === page ? 'bg-indigo-600 border-indigo-600 text-white shadow-sm' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'" x-text="page"></button></template>
                    <template x-if="page === '...'"><span class="px-2 py-1 text-xs text-gray-400 font-bold">...</span></template>
                </div>
            </template>
            <button type="button" @click="if(invoicePage < totalInvoicePages) invoicePage++" :disabled="invoicePage === totalInvoicePages" class="px-3 py-1.5 text-xs font-semibold bg-white border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">&rsaquo;</button>
        </div>
    </div>
    @elseif($invoices && $invoices->hasPages())
        <div class="p-4 border-t border-gray-100">
            {{ $invoices->links() }}
        </div>
    @endif
    @once('shared-invoice-table-modals')
        <x-modals.payment-modal />
        <x-modals.receipt-preview-modal />
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
                            <div class="flex items-center gap-3 text-amber-600 bg-amber-50 p-4 rounded-xl border border-amber-100 mb-4">
                                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <p class="text-sm text-slate-600 font-medium">
                                    Are you sure you want to void invoice <span class="font-bold text-slate-900" x-text="invoiceNumber"></span>? This action cannot be undone.
                                </p>
                            </div>

                            <div>
                                <x-form.input-label value="Reason for Voiding" class="mb-1" />
                                <textarea @keydown.enter.stop="if ($event.ctrlKey &amp;&amp; !$event.isComposing &amp;&amp; $event.keyCode !== 229) { $event.preventDefault(); if (!loading) $el.form.requestSubmit(); }" name="reason" rows="3" required
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
    @endonce
</div>
