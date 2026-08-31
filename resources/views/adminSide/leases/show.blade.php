<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans"
        x-data="{
            activeId: '{{ old('active_id', $lease->id) }}',
            source: {{ $historyJson->isNotEmpty() ? $historyJson->toJson() : '{}' }},
            loading: false,
            invoicePage: 1,
            perPage: 5,
            voidModalOpen: false, 
            actionUrl: '',

            openUpload: {{ $errors->has('stamping_reference_no') || $errors->has('stamping_cert') ? 'true' : 'false' }},
            shake: {{ $errors->any() ? 'true' : 'false' }},
            get activeLease() {
                return (this.source && this.activeId) ? (this.source[this.activeId] || {}) : {}
            },

            get paginatedInvoices() {
                let invoices = this.activeLease.invoices || [];
                let start = (this.invoicePage - 1) * this.perPage;
                return invoices.slice(start, start + this.perPage);
            },

            get totalInvoicePages() {
                let invoices = this.activeLease.invoices || [];
                return Math.ceil(invoices.length / this.perPage) || 1;
            },

            get paginationLinks() {
                let total = this.totalInvoicePages;
                let current = this.invoicePage;
                let pages = [];

                if (total <= 7) {
                    for (let i = 1; i <= total; i++) { pages.push(i); }
                } else {
                    if (current <= 4) {
                        pages = [1, 2, 3, 4, 5, '...', total];
                    } else if (current >= total - 3) {
                        pages = [1, '...', total - 4, total - 3, total - 2, total - 1, total];
                    } else {
                        pages = [1, '...', current - 1, current, current + 1, '...', total];
                    }
                }
                return pages;
            },

            openPayment: false,
            shakePayment: false,
            openPreview: false,
            paymentData: { id: '', invoice_no: '', amount_due: 0, actionUrl: '' },

            openManual: false,
            manualActionUrl: '',

            getManualInvoiceUrl() {
                if (!this.activeId) return '#';
                return `{{ route('admin.invoices.store-manual', ':lease') }}`.replace(':lease', this.activeId);
            },

            refreshTable() {
                if (!this.activeId || this.loading) return;
                this.loading = true;
                const url = `{{ url('/') }}/admin/leases/${this.activeId}/refresh-payments`;

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(response => {
                    if (!response.ok) throw new Error('Status: ' + response.status);
                    return response.json();
                })
                .then(data => {
                    const rentEl = document.getElementById('rent-payments-container');
                    const otherEl = document.getElementById('other-payments-container');
                    if (rentEl) rentEl.innerHTML = data.rentHtml;
                    if (otherEl) otherEl.innerHTML = data.otherHtml;
                    if (this.activeLease) {
                        this.activeLease.can_generate = data.can_generate;
                    }
                })
                .catch(e => { console.error('Table refresh failed:', e); })
                .finally(() => { this.loading = false; });
            },

            init() {
                this.$watch('activeId', (newVal) => {
                    if (newVal) {
                        this.invoicePage = 1;
                        this.refreshTable();
                    }
                });
            }
        }"
        @click.stop
        @open-payment.window="paymentData = $event.detail; openPayment = true;"
        @open-manual-modal.window="openManual = true; manualActionUrl = $event.detail.action;"
        @invoice-generated.window="
            if ($event.detail && $event.detail.success) {
                if ($event.detail.invoice) {
                    if (this.activeId && this.source[this.activeId]) {
                        if (!this.source[this.activeId].invoices) {
                            this.source[this.activeId].invoices = [];
                        }
                        let inv = $event.detail.invoice;
                        if (!inv.invoice_items && inv.items) inv.invoice_items = inv.items;
                        this.source[this.activeId].invoices.unshift(inv);
                    }
                }
                this.refreshTable();
            }
        ">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <a href="{{ route('admin.leases.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 flex items-center transition-colors mb-4">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Lease List
            </a>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="mb-8 w-full">
                    <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                        <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Lease Progression</h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full z-[101]">
                            @foreach($leaseHistory as $history)
                            @php
                                $hStatus = strtolower((string)$history->status);
                                $statusColor = match($hStatus) {
                                    'new' => 'indigo', 'renew' => 'emerald', 'check out' => 'amber', 'end' => 'gray', default => 'slate'
                                };
                            @endphp
                            <div @click="activeId = '{{ $history->id }}'"
                                class="relative p-4 rounded-xl border-2 transition-all duration-200 cursor-pointer group"
                                :class="activeId == '{{ $history->id }}' ? 'border-{{ $statusColor }}-500 bg-{{ $statusColor }}-50 ring-4 ring-{{ $statusColor }}-100 z-10' : 'border-gray-100 bg-white hover:border-indigo-300 hover:shadow-md hover:-translate-y-1'">
                                <div class="flex justify-between items-start mb-3">
                                    <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded"
                                        :class="activeId == '{{ $history->id }}' ? 'bg-{{ $statusColor }}-200 text-{{ $statusColor }}-800' : 'bg-gray-100 text-gray-500'">
                                        {{ $history->status }} {{ $history->is_current ? '(Current)' : '' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <p class="text-xs font-bold text-slate-900">
                                        {{ $history->start_date_formatted ?? '-' }} - {{ $history->end_date_formatted ?? '-' }}
                                    </p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoice Tables -->
            <div class="mt-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden p-6 space-y-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-800">Payment Overview</h3>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                @click="$dispatch('open-manual-modal', { action: getManualInvoiceUrl() })"
                                class="inline-flex items-center px-4 py-2 h-10 text-sm font-medium rounded-lg text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 shadow-sm transition-all">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Add Manual Invoice
                            </button>
                            <x-modals.manual-invoice-modal :feeTypes="$feeTypes" />
                        </div>
                    </div>

                    <div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span class="w-1.5 h-4 bg-emerald-500 rounded-full"></span>
                            Invoices
                        </h4>
                        <div class="overflow-x-auto border border-gray-100 rounded-xl">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Invoice No</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Documents</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Period</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Due Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount Details</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="other-payments-container" class="bg-white divide-y divide-gray-200">
                                    <template x-for="invoice in paginatedInvoices" :key="invoice.id">
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-bold text-indigo-600" x-text="invoice.invoice_no"></td>
                                            
                                            <!-- 💡 Alpine Loop Document Preview (Using Component) -->
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600 flex flex-col items-start gap-1.5">
                                                <!-- Invoice Component -->
                                                <template x-if="invoice.document_template_id && invoice.document_template_id !== '—' && invoice.template_title">
                                                    <div>
                                                        <x-buttons.preview-doc
                                                            type="invoice"
                                                            color="indigo"
                                                            titleExpr="'Invoice: ' + invoice.invoice_no"
                                                            contentExpr="invoice.template_html || invoice.html_content || ''"
                                                            variablesExpr="invoice.variables || {}"
                                                            itemsExpr="invoice.invoice_items || invoice.items || []"
                                                            buttonTextExpr="invoice.template_title"
                                                        />
                                                    </div>
                                                </template>

                                                <!-- Receipt Component Loop -->
                                                <template x-if="invoice.receipts && invoice.receipts.length > 0">
                                                    <template x-for="receipt in invoice.receipts" :key="receipt.id">
                                                        <div class="mt-1">
                                                            <x-buttons.preview-doc
                                                                type="receipt"
                                                                color="emerald"
                                                                titleExpr="'Receipt: ' + (receipt.receipt_no || 'N/A')"
                                                                contentExpr="receipt.template_html || receipt.html_content || (receipt.documentTemplate ? receipt.documentTemplate.html_template : '') || ''"
                                                                variablesExpr="invoice.variables || {}"
                                                                itemsExpr="invoice.invoice_items || invoice.items || []"
                                                                buttonTextExpr="(receipt.receipt_no || 'Receipt') + ((receipt.template_title || (receipt.documentTemplate ? receipt.documentTemplate.title : '')) ? ' (' + (receipt.template_title || (receipt.documentTemplate ? receipt.documentTemplate.title : '')) + ')' : '')"
                                                                extraExpr="{
                                                                    receiptNo: receipt.receipt_no || 'N/A',
                                                                    paymentDate: receipt.created_at || receipt.payment_date || '—',
                                                                    receiptVariables: receipt.variables || {},
                                                                    paidAmount: typeof receipt.amount === 'number' ? (receipt.amount > 100 ? (receipt.amount/100).toFixed(2) : parseFloat(receipt.amount).toFixed(2)) : (receipt.amount || '0.00'),
                                                                    invoiceNo: invoice.invoice_no
                                                                }"
                                                            />
                                                        </div>
                                                    </template>
                                                </template>
                                                
                                                <template x-if="(!invoice.document_template_id || invoice.document_template_id === '—' || !invoice.template_title) && (!invoice.receipts || invoice.receipts.length === 0)">
                                                    <span class="text-xs text-gray-400 italic mt-1">- None -</span>
                                                </template>
                                            </td>

                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900" x-text="invoice.period"></td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600" x-text="invoice.due_date"></td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                <div class="space-y-1">
                                                    <div class="text-gray-900 font-semibold">Total: <span class="font-normal" x-text="'RM ' + invoice.total_amount"></span></div>
                                                    <div class="text-emerald-600 font-medium text-xs">Paid: <span x-text="'RM ' + invoice.amount_paid"></span></div>
                                                    <div class="text-red-600 font-medium text-xs">Balance: <span x-text="'RM ' + invoice.amount_balance"></span></div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border uppercase"
                                                    :class="{
                                                        'bg-green-100 text-green-700 border-green-200': invoice.status === 'paid',
                                                        'bg-yellow-100 text-yellow-700 border-yellow-200': invoice.status === 'unpaid',
                                                        'bg-red-100 text-red-700 border-red-200': invoice.status === 'rejected',
                                                        'bg-gray-100 text-gray-500 border-gray-200 line-through': invoice.status === 'void'
                                                    }" x-text="invoice.status">
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center text-sm font-medium">
                                                <!-- Record Payment Button -->
                                                <template x-if="invoice.status !== 'paid' && invoice.status !== 'void'">
                                                    <button type="button"
                                                        @click="$dispatch('open-payment', {
                                                            id: invoice.id, invoiceNo: invoice.invoice_no, totalAmount: invoice.amount_balance, invoiceItems: invoice.invoice_items, actionUrl: '{{ route('admin.invoices.payment', ':id') }}'.replace(':id', invoice.id)
                                                        })"
                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200/60 rounded-lg transition-all shadow-sm">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                                        </svg>
                                                        <span>Record Payment</span>
                                                    </button>
                                                </template>

                                                <!-- Void Button -->
                                                <template x-if="invoice.status !== 'paid' && invoice.status !== 'void'">
                                                    <button type="button"
                                                        @click="actionUrl = '{{ route('admin.invoices.void', ':id') }}'.replace(':id', invoice.id); voidModalOpen = true;"
                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200/60 rounded-lg transition-all shadow-sm">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                                                        </svg>
                                                        <span>Void</span>
                                                    </button>
                                                </template>
                                            </td>
                                        </tr>
                                    </template>
                                    <template x-if="!activeLease.invoices || activeLease.invoices.length === 0">
                                        <tr><td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500 italic">No invoices found for this lease.</td></tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <x-modals.confirmation-modal 
                            id="voidModalOpen"
                            title="Void Invoice"
                            method="PATCH"
                            message="Are you sure you want to void this invoice? This action cannot be undone."
                            label="Reason for Voiding"
                            inputName="reason"
                            inputType="textarea"
                        />

                        <!-- Pagination -->
                        <div class="mt-4 flex items-center justify-between px-2" x-show="totalInvoicePages > 1">
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

                    </div>
                </div>
            </div>
        </div>

        <x-modals.payment-modal />
    </div>
</x-app-layout>