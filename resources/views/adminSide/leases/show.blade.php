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
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-3">
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
                    <div class="bg-white shadow-sm" x-data="{ 
                        openUpload: {{ $errors->any() ? 'true' : 'false' }}, 
                        shake: {{ $errors->any() ? 'true' : 'false' }},
                        activeLease: JSON.parse(sessionStorage.getItem('lastActiveLease') || '{}')
                    }">
                        {{-- 左侧按钮：View Agreement --}}
                        @if (!empty($lease->document_id))
                            <button type="button"
                                data-base-content="{{ $lease->documentTemplate?->html_template }}"
                                data-title="{{ $lease->documentTemplate?->title }}"
                                data-replacements="{{ json_encode([
                                    '{status}' => $lease->status ?? 'N/A',
                                    '{tenant_name}' => $lease->tenant?->user->name ?? 'N/A',
                                    '{tenant_ic}'   => $lease->tenant?->ic_number ?? 'N/A',
                                    '{owner_name}' => $lease->leasable?->owner?->user->name ?? 'N/A',
                                    '{owner_ic}'   => $lease->leasable?->owner?->ic_number ?? 'N/A',
                                    '{property_address}'   => $lease->leasable?->full_address ?? 'N/A',
                                    '{property_type}'   => $lease->leasableTypeLabel ?? 'N/A',
                                    '{property_name}'   => $lease->leasableName ?? 'N/A',
                                    '{rent_mode}'   => $lease->term_type ?? 'N/A',
                                    '{rent_price}'  => number_format($lease->rent_price, 2),
                                    '{deposit_mode}'  => $lease->deposit_mode ?? 'N/A',
                                    '{security_deposit}' => number_format($lease->security_deposit, 2),
                                    '{utilities_deposit}' => number_format($lease->utilities_deposit, 2),
                                    '{start_date}'  => $lease->start_date?->format('d/m/Y') ?? 'N/A',
                                    '{end_date}'    => $lease->end_date?->format('d/m/Y') ?? 'N/A',
                                    '{check_out_date}'    => $lease->checked_out_at?->format('d/m/Y') ?? 'N/A',
                                    '{end_agreement_date}'    => $lease->agreement_ended_at?->format('d/m/Y') ?? 'N/A',
                                ]) }}"
                                @click="
                                    const btn = $el;
                                    let content = btn.dataset.baseContent;
                                    if (!content) {
                                        console.error('Agreement content is empty');
                                        return;
                                    }
                                    const replacements = JSON.parse(btn.dataset.replacements);
                                    Object.keys(replacements).forEach(key => {
                                        const val = replacements[key];
                                        const regex = new RegExp(key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
                                        content = content.replace(regex, `<span class='text-inherit font-semibold'>${val}</span>`);
                                    });
                                    $dispatch('open-lease-preview', { 
                                        content: content, 
                                        title: btn.dataset.title 
                                    });
                                "
                                class="flex-1 px-4 py-2.5 bg-white text-indigo-600 text-xs font-bold rounded-xl border border-indigo-100 hover:bg-indigo-50 transition-all shadow-sm text-center">
                                VIEW AGREEMENT
                            </button>
                        @endif

                        {{-- 右侧按钮：Upload Stamping (条件渲染) --}}
                        @if(!$lease->stamping_status && !in_array(strtolower($lease->status), ['check out', 'end agreement']))
                            <button @click="openUpload = true" 
                                class="flex-1 px-4 py-2.5 bg-indigo-600 text-white text-xs font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-sm text-center">
                                UPLOAD STAMPING
                            </button>
                        @endif

                        {{-- Modal 渲染 --}}
                        @if(!$lease->stamping_status && !in_array(strtolower($lease->status), ['check out', 'end agreement']))
                            <x-modals.lease-stamping-modal :lease="$lease" />
                        @endif
                    </div>
                </div>

                <!-- Compact Structured Grid Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                    
                    <!-- Block 1: Terms & Financials -->
                    <div class="bg-gray-50/60 rounded-md p-3 border border-gray-100 space-y-1.5">
                        <h4 class="font-bold tracking-wider text-gray-400 uppercase text-[10px]">Financials & Term</h4>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Term Type</span>
                            <span class="font-semibold text-gray-900" x-text="activeLease.term_type"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Total Rent</span>
                            <span class="font-bold text-emerald-600">RM <span x-text="activeLease.total_rent_price"></span></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Duration</span>
                            <span class="font-medium text-gray-900"><span x-text="activeLease.start_date"></span> → <span x-text="activeLease.end_date"></span></span>
                        </div>
                    </div>

                    <!-- Block 2: Tenant Information -->
                    <div class="bg-gray-50/60 rounded-md p-3 border border-gray-100 space-y-1.5">
                        <h4 class="font-bold tracking-wider text-gray-400 uppercase text-[10px]">Tenant Details</h4>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Name</span>
                            <span class="font-semibold text-gray-900 truncate max-w-[160px]" :title="activeLease.tenant_name" x-text="activeLease.tenant_name"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">IC / ID</span>
                            <span class="font-mono text-gray-700" x-text="activeLease.tenant_ic"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Wallet Amount</span>
                            <span class="font-mono text-gray-700" x-text="'RM ' + activeLease.wallet_balance"></span>
                        </div>
                    </div>

                    <!-- Block 3: Owner & Compliance -->
                    <div class="bg-gray-50/60 rounded-md p-3 border border-gray-100 space-y-1.5">
                        <h4 class="font-bold tracking-wider text-gray-400 uppercase text-[10px]">Owner & Compliance</h4>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Owner</span>
                            <span class="font-semibold text-gray-900 truncate max-w-[160px]" :title="activeLease.owner_name" x-text="activeLease.owner_name"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Owner ID</span>
                            <span class="font-mono text-gray-700" x-text="activeLease.owner_ic"></span>
                        </div>
                        <div class="flex justify-between items-center pt-0.5">
                            <span class="text-gray-500">Stamping</span>
                            <span class="inline-flex items-center gap-1 font-medium">
                                <span class="w-1.5 h-1.5 rounded-full" :class="activeLease.stamping_status ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                                <span :class="activeLease.stamping_status ? 'text-emerald-700 font-semibold' : 'text-amber-700'" 
                                    x-text="activeLease.stamping_status ? 'Stamped (' + (activeLease.stamping_reference_no ?? 'N/A') + ')' : 'Unstamped'"></span>
                            </span>
                        </div>
                    </div>

                    <!-- Block 4: Property Address (Compact Full Width) -->
                    <div class="md:col-span-2 lg:col-span-3 bg-gray-50/60 rounded-md p-3 border border-gray-100 flex flex-col justify-center">
                        <h4 class="font-bold tracking-wider text-gray-400 uppercase text-[10px] mb-0.5">Property Address</h4>
                        <span class="font-semibold text-gray-900 line-clamp-1" :title="activeLease.property_address" x-text="activeLease.property_address"></span>
                    </div>

                </div>

                <!-- Additional Charges Section (Compact Horizontal Wrap) -->
                <template x-if="activeLease.charges && activeLease.charges.length > 0">
                    <div class="border-t border-gray-100 pt-3 mt-4 text-xs">
                        <h4 class="font-bold tracking-wider text-gray-400 uppercase text-[10px] mb-2">Additional Charges Breakdown</h4>
                        <div class="bg-gray-50/50 rounded-md p-2.5 border border-gray-100 flex flex-wrap gap-x-6 gap-y-1.5">
                            <template x-for="charge in activeLease.charges" :key="charge.id">
                                <div class="flex items-center gap-2">
                                    <span x-text="charge.description" class="text-gray-600 font-medium"></span>
                                    <span class="font-semibold text-gray-900">RM <span x-text="charge.amount"></span></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

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
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Remarks</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="invoices-table-body" class="bg-white divide-y divide-gray-200">
                                    <template x-for="invoice in paginatedInvoices" :key="invoice.id">
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-bold text-indigo-600" x-text="invoice.invoice_no"></td>
                                            
                                            <!-- 💡 Alpine Loop Document Preview (Using Component) -->
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <!-- Invoice Component -->
                                                <div class="flex flex-col items-start gap-1.5">
                                                    <template x-if="invoice.document_template_id && invoice.document_template_id !== '—' && invoice.template_title">
                                                        <div>
                                                            <x-buttons.preview-doc
                                                                type="invoice"
                                                                color="indigo"
                                                                titleExpr="'Invoice: ' + invoice.invoice_no"
                                                                contentExpr="invoice.template_html || invoice.html_content || ''"
                                                                variablesExpr="invoice.variables || {}"
                                                                itemsExpr="invoice.invoice_items || invoice.items || []"
                                                                buttonTextExpr="invoice.invoice_no"
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
                                                                    buttonTextExpr="receipt.receipt_no"
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
                                                </div>
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
                                            <td class="px-4 py-4 text-sm text-gray-600" x-text="invoice.remarks"></td>
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
                                                            id: invoice.id, invoiceNo: invoice.invoice_no, totalAmount: invoice.amount_balance, invoiceItems: invoice.invoice_items, walletBalance: activeLease.wallet_balance, actionUrl: '{{ route('admin.invoices.payment', ':id') }}'.replace(':id', invoice.id)
                                                        })"
                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200/60 rounded-lg transition-all shadow-sm">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                                        </svg>
                                                        <span>Record Payment</span>
                                                    </button>
                                                </template>

                                                <!-- Void Button -->
                                                <template x-if="invoice.status !== 'void'">
                                                    <button type="button"
                                                        @click="
                                                            $dispatch('open-void-modal', { 
                                                                actionUrl: '{{ route('admin.invoices.void', ':id') }}'.replace(':id', invoice.id),
                                                                invoiceNumber: invoice.invoice_no
                                                            });
                                                        "
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

                        <x-modals.payment-modal />

                        <!-- The Confirmation Modal Component with Dynamic actionUrl binding -->
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
    </div>
</x-app-layout>