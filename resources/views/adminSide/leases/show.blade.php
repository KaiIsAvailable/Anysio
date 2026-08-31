<x-app-layout>
    @php
        $defaultActiveId = $leaseHistory->firstWhere('is_current', true)?->id ?? $leaseHistory->first()?->id ?? $lease->id;
    @endphp
    <div class="py-12 bg-gray-50 min-h-screen font-sans"
        x-data="{
            activeId: '{{ old('active_id', $defaultActiveId) }}',
            source: {{ $historyJson->isNotEmpty() ? $historyJson->toJson() : '{}' }},
            loading: false,
            invoicePage: 1,
            perPage: 5,
            voidModalOpen: false, 
            actionUrl: '',

            openUpload: {{ $errors->has('stamping_reference_no') || $errors->has('stamping_cert') ? 'true' : 'false' }},
            shake: {{ $errors->any() ? 'true' : 'false' }},
            
            get activeLease() {
                return (this.source && this.activeId) ? (this.source[this.activeId] || {}) : {};
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
                console.log('[Debug] refreshTable called for activeId:', this.activeId);
                if (!this.activeId || this.loading) return;
                this.loading = true;
                const url = `{{ route('admin.leases.show', ':id') }}`.replace(':id', this.activeId) + `?lease_id=${this.activeId}`;

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(response => {
                    if (!response.ok) throw new Error('Status: ' + response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('[Debug] refreshTable success data:', data);
                    if (this.activeLease) {
                        this.activeLease.can_generate = data.can_generate;
                        if (data.invoices) {
                            this.activeLease.invoices = data.invoices;
                        }
                    }
                })
                .catch(e => { console.error('[Debug] Table refresh failed:', e); })
                .finally(() => { this.loading = false; });
            },

            init() {
                let self = this;
                console.log('[Debug] Component initialized. refreshTable is function?', typeof self.refreshTable);
                
                this.$watch('activeId', (newVal) => {
                    if (newVal) {
                        self.invoicePage = 1;
                        self.refreshTable();
                    }
                });
            }
        }"
        @click.stop
        @open-payment.window="paymentData = $event.detail; openPayment = true;"
        @open-manual-modal.window="openManual = true; manualActionUrl = $event.detail.action;"
        @invoice-generated.window="
            let self = $data;
            console.log('[Debug] @invoice-generated event caught. refreshTable type:', typeof self.refreshTable);
            if ($event.detail && $event.detail.success) {
                if ($event.detail.invoice) {
                    if (self.activeId && self.source[self.activeId]) {
                        if (!self.source[self.activeId].invoices) {
                            self.source[self.activeId].invoices = [];
                        }
                        let inv = $event.detail.invoice;
                        if (!inv.invoice_items && inv.items) inv.invoice_items = inv.items;
                        self.source[self.activeId].invoices.unshift(inv);
                    }
                }
                self.refreshTable();
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

            <div class="bg-white shadow-sm border border-gray-200 rounded-xl p-6 mb-6" x-show="activeLease && Object.keys(activeLease).length > 0">
                <!-- Header Summary Bar -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-gray-100 pb-4 mb-6 gap-3">
                    <div>
                        <span class="text-xs font-semibold tracking-wider text-gray-400 uppercase">Lease Profile</span>
                        <h3 class="text-base font-bold text-gray-900" x-text="activeLease.property_name"></h3>
                    </div>
                    <div>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full shadow-sm" 
                            :class="{
                                'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20': activeLease.status === 'Active',
                                'bg-blue-50 text-blue-700 ring-1 ring-blue-600/20': activeLease.status === 'New',
                                'bg-gray-50 text-gray-600 ring-1 ring-gray-500/20': activeLease.status !== 'Active' && activeLease.status !== 'New'
                            }" 
                            x-text="activeLease.status"></span>
                    </div>
                </div>

                <!-- Structured Grid Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 text-sm">
                    
                    <!-- Block 1: Terms & Financials -->
                    <div class="bg-gray-50/60 rounded-lg p-4 border border-gray-100/80 space-y-2.5">
                        <h4 class="text-xs font-bold tracking-wider text-gray-400 uppercase">Financials & Term</h4>
                        <div>
                            <span class="text-gray-500 block text-xs">Term Type</span>
                            <span class="font-semibold text-gray-900" x-text="activeLease.term_type"></span>
                        </div>
                        <div>
                            <span class="text-gray-500 block text-xs">Total Rent Price</span>
                            <span class="font-bold text-emerald-600">RM <span x-text="activeLease.total_rent_price"></span></span>
                        </div>
                        <div>
                            <span class="text-gray-500 block text-xs">Lease Duration</span>
                            <span class="font-medium text-gray-900 text-xs"><span x-text="activeLease.start_date"></span> → <span x-text="activeLease.end_date"></span></span>
                        </div>
                    </div>

                    <!-- Block 2: Tenant Information -->
                    <div class="bg-gray-50/60 rounded-lg p-4 border border-gray-100/80 space-y-2.5">
                        <h4 class="text-xs font-bold tracking-wider text-gray-400 uppercase">Tenant Details</h4>
                        <div>
                            <span class="text-gray-500 block text-xs">Name</span>
                            <span class="font-semibold text-gray-900" x-text="activeLease.tenant_name"></span>
                        </div>
                        <div>
                            <span class="text-gray-500 block text-xs">IC / ID Number</span>
                            <span class="font-mono text-gray-700 text-xs" x-text="activeLease.tenant_ic"></span>
                        </div>
                    </div>

                    <!-- Block 3: Owner & Compliance -->
                    <div class="bg-gray-50/60 rounded-lg p-4 border border-gray-100/80 space-y-2.5">
                        <h4 class="text-xs font-bold tracking-wider text-gray-400 uppercase">Owner & Compliance</h4>
                        <div>
                            <span class="text-gray-500 block text-xs">Owner Name</span>
                            <span class="font-semibold text-gray-900" x-text="activeLease.owner_name"></span>
                        </div>
                        <div>
                            <span class="text-gray-500 block text-xs">Owner IC / ID</span>
                            <span class="font-mono text-gray-700 text-xs" x-text="activeLease.owner_ic"></span>
                        </div>
                        <div>
                            <span class="text-gray-500 block text-xs">Stamping Status</span>
                            <span class="inline-flex items-center gap-1.5 font-medium mt-0.5 text-xs">
                                <span class="w-2 h-2 rounded-full" :class="activeLease.stamping_status ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                                <span :class="activeLease.stamping_status ? 'text-emerald-700 font-semibold' : 'text-amber-700'" 
                                    x-text="activeLease.stamping_status ? 'Stamped (' + (activeLease.stamping_reference_no ?? 'N/A') + ')' : 'Unstamped'"></span>
                            </span>
                        </div>
                    </div>

                    <!-- Block 4: Property Address Full Width Banner -->
                    <div class="md:col-span-2 lg:col-span-3 bg-gray-50/60 rounded-lg p-4 border border-gray-100/80 flex flex-col justify-center">
                        <h4 class="text-xs font-bold tracking-wider text-gray-400 uppercase mb-1">Property Address</h4>
                        <span class="font-semibold text-gray-900" x-text="activeLease.property_address"></span>
                    </div>

                </div>

                <!-- Additional Charges Section (If any) -->
                <template x-if="activeLease.charges && activeLease.charges.length > 0">
                    <div class="border-t border-gray-100 pt-4 mt-6">
                        <h4 class="text-xs font-bold tracking-wider text-gray-400 uppercase mb-3">Additional Charges Breakdown</h4>
                        <div class="bg-gray-50/50 rounded-lg p-3 border border-gray-100 divide-y divide-gray-200 text-sm">
                            <template x-for="charge in activeLease.charges" :key="charge.id">
                                <div class="py-2 first:pt-0 last:pb-0 flex justify-between items-center">
                                    <span x-text="charge.description" class="text-gray-600 font-medium"></span>
                                    <span class="font-semibold text-gray-900">RM <span x-text="charge.amount"></span></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
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
                                <tbody id="invoices-table-body" class="bg-white divide-y divide-gray-200">
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