<x-app-layout>
    {{-- 1. 初始化数据，注意 activeId 加了单引号 --}}
    <div class="py-12 bg-gray-50 min-h-screen font-sans"
        x-data="{ 
            activeId: '{{ old('active_id', $lease->id) }}',
            source: {{ $historyJson->isNotEmpty() ? $historyJson->toJson() : '{}' }},
            loading: false, 
            openUpload: {{ $errors->has('stamping_reference_no') || $errors->has('stamping_cert') ? 'true' : 'false' }},
            shake: {{ $errors->any() ? 'true' : 'false' }},

            get activeLease() { 
                return (this.source && this.activeId) ? (this.source[this.activeId] || {}) : {} 
            },

            openPayment: false, 
            shakePayment: false,
            openPreview: false, 
            paymentData: { id: '', invoiceNo: '', totalAmount: 0, invoiceItems: [], actionUrl: '' },

            openManual: false,
            manualActionUrl: '',

            getManualInvoiceUrl() {
                if (!this.activeId) return '#';
                return `{{ route('admin.invoices.store-manual', ':lease') }}`.replace(':lease', this.activeId);
            }
        }">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <a href="{{ route('admin.leases.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 flex items-center transition-colors">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Lease List
            </a>
            <br>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                {{-- Grid Lease Flow --}}
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

                            {{-- 修改点：@click 内部 ID 加引号，:class 内部比较也加引号 --}}
                            <div @click="activeId = '{{ $history->id }}'"
                                class="relative p-4 rounded-xl border-2 transition-all duration-200 cursor-pointer group"
                                :class="activeId == '{{ $history->id }}' 
                                        ? 'border-{{ $statusColor }}-500 bg-{{ $statusColor }}-50 ring-4 ring-{{ $statusColor }}-100 z-10' 
                                        : 'border-gray-100 bg-white hover:border-indigo-300 hover:shadow-md hover:-translate-y-1'">

                                <div class="flex justify-between items-start mb-3">
                                    <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded"
                                        :class="activeId == '{{ $history->id }}' ? 'bg-{{ $statusColor }}-200 text-{{ $statusColor }}-800' : 'bg-gray-100 text-gray-500'">
                                        {{ $history->status }} {{ $history->is_current ? '(Current)' : '' }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <p class="text-xs font-bold text-slate-900">
                                        {{ $history->start_date_formatted ?? '-' }} - {{ $history->end_date_formatted ?? '-' }}
                                        @if ($history->agreement_ended_at)
                                        <p class="text-xs font-bold text-slate-900">
                                            ({{ $history->agreement_ended_at_formatted ?? '-' }})
                                        </p>
                                        @elseif ($history->checked_out_at)
                                        <p class="text-xs font-bold text-slate-900">
                                            ({{ $history->checked_out_at_formatted ?? '-' }})
                                        </p>
                                        @endif
                                    </p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3. 详情区域 --}}
            <div class="mt-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6"
                    x-show="activeId"
                    x-transition:enter="transition ease-out duration-300">

                    <div class="flex items-center justify-between mb-8 pb-4 border-b border-gray-100">
                        {{-- 左侧：标题 --}}
                        <h3 class="text-xl font-black text-slate-800 flex items-center gap-3">
                            <span class="w-2 h-6 bg-indigo-600 rounded-full"></span>
                            Lease Details: <span x-text="activeLease.status" class="capitalize text-indigo-600"></span>
                        </h3>

                        {{-- 右侧：动态操作按钮 --}}
                        <div class="flex items-center gap-3">

                            <template x-if="activeLease.can_stamp">
                                <div class="flex items-center gap-2">

                                    <template x-if="activeLease.stamping_status && activeLease.stamping_cert_path">
                                        <div class="flex items-center gap-2">
                                            <div class="flex items-center gap-2">
                                                <span class="p-1 bg-emerald-100 text-emerald-600 rounded-full">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                    </svg>
                                                </span>
                                                <a :href="activeLease.view_url" class="text-xs font-bold text-indigo-600 hover:underline">
                                                    View Cert
                                                </a>
                                            </div>

                                            <button @click="openUpload = true" class="p-1.5 text-gray-400 hover:text-indigo-600 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            </button>
                                        </div>
                                    </template>

                                    <template x-if="!activeLease.stamping_status || !activeLease.stamping_cert_path">
                                        <button @click="openUpload = true"
                                            class="px-3 py-1.5 bg-indigo-50 text-indigo-600 text-xs font-black rounded-lg border border-indigo-100 hover:bg-indigo-600 hover:text-white transition-all shadow-sm">
                                            UPLOAD STAMPING
                                        </button>
                                    </template>

                                </div>
                            </template>

                            <template x-if="!activeLease.can_stamp">
                                <span class="px-3 py-1 bg-gray-100 text-gray-400 text-[10px] font-bold rounded-full uppercase tracking-tighter">
                                    NO STAMPING NEEDED
                                </span>
                            </template>

                            <div x-show="activeLease && activeLease.agreement_id">
                                <button type="button"
                                    @click="
                                        let content = activeLease.agreement?.content || '';
                                        if (!content) {
                                            console.warn('Agreement content is empty');
                                            return;
                                        }

                                        const formatMoney = (val) => {
                                            const num = parseFloat(val);
                                            return isNaN(num) ? '0.00' : num.toLocaleString(undefined, {minimumFractionDigits: 2});
                                        };

                                        const replacements = {
                                            '{status}': activeLease.status,
                                            '{tenant_name}': activeLease.tenant_name,
                                            '{tenant_ic}': activeLease.tenant_ic,
                                            '{owner_name}': activeLease.owner_name,
                                            '{owner_ic}': activeLease.owner_ic,
                                            '{property_address}': activeLease.property_address,
                                            '{property_type}': activeLease.property_type,
                                            '{property_name}': activeLease.property_name,
                                            '{rent_mode}': activeLease.rent_mode,
                                            '{rent_price}': formatMoney(activeLease.rent_price),
                                            '{total_rent_price}': formatMoney(
                                                parseFloat(activeLease.rent_price || 0) + 
                                                (activeLease.charges ? activeLease.charges.reduce((sum, c) => sum + parseFloat(c.amount || 0), 0) : 0)
                                            ),
                                            '{start_date}': activeLease.start_date,
                                            '{end_date}': activeLease.end_date,
                                            '{check_out_date}': activeLease.check_out_date,
                                            '{end_agreement_date}': activeLease.end_agreement_date,
                                        };

                                        Object.keys(replacements).forEach(key => {
                                            const val = replacements[key] || 'N/A';
                                            const regex = new RegExp(key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
                                            content = content.replace(regex, `<span class='text-inherit font-semibold text-indigo-600'>${val}</span>`);
                                        });

                                        $dispatch('open-lease-preview', { 
                                            content: content, 
                                            title: activeLease.agreement?.title || 'Agreement Preview'
                                        });
                                        
                                        document.body.style.overflow = 'hidden';
                                    "
                                    class="px-3 py-1.5 bg-indigo-50 text-indigo-600 text-xs font-black rounded-lg border border-indigo-100 hover:bg-indigo-600 hover:text-white transition-all shadow-sm">
                                    VIEW AGREEMENT
                                </button>
                            </div>

                            <div class="h-6 w-[1px] bg-gray-200 mx-1"></div>

                            <x-modals.lease-stamping-modal ::lease-id="activeId" />
                            <x-preview-agreement-modal ::lease-id="activeId" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500 uppercase font-bold">Owner Name</p>
                            <p class="text-lg font-semibold" x-text="activeLease.owner_name"></p>
                            <div class="flex items-center gap-1 mt-1 text-xs font-semibold text-gray-600">
                                <span>IC:</span>
                                <span x-text="activeLease.owner_ic"></span>
                            </div>
                        </div>

                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500 uppercase font-bold">Tenant Name</p>
                            <p class="text-lg font-semibold" x-text="activeLease.tenant_name"></p>
                            <div class="flex items-center gap-1 mt-1 text-xs font-semibold text-gray-600">
                                <span>IC:</span>
                                <span x-text="activeLease.tenant_ic"></span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mt-3">
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500 uppercase font-bold">Start Date</p>
                            <p class="text-lg font-semibold" x-text="activeLease.start_date"></p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500 uppercase font-bold">End Date</p>
                            <p class="text-lg font-semibold" x-text="activeLease.end_date"></p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-lg md:col-span-1">
                            <p class="text-xs text-gray-500 uppercase font-bold">Charges</p>
                            
                            <div class="text-lg font-semibold text-indigo-600 mt-0.5">
                                RM <span x-text="activeLease.total_rent_price"></span>
                            </div>

                            <template x-if="activeLease.charges && activeLease.charges.length > 0">
                                <div class="space-y-0.5 border-t border-gray-100 pt-1 mt-1">
                                    <template x-for="charge in activeLease.charges" :key="charge.id">
                                        <div class="text-[11px] text-slate-600 flex justify-between gap-2">
                                            <span class="truncate" :title="charge.description" x-text="charge.description + ':'"></span>
                                            <span class="font-medium text-slate-900 shrink-0">RM <span x-text="charge.amount"></span></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div x-show="activeLease.checked_out_at" class="mb-6 mt-3">
                        <div class="p-4 bg-amber-50 rounded-lg border border-amber-100">
                            <p class="text-xs text-amber-600 uppercase font-bold">Check Out Date</p>
                            <p class="text-lg font-semibold text-amber-900" x-text="activeLease.checked_out_at"></p>
                        </div>
                    </div>

                    <div x-show="activeLease.agreement_ended_at" class="mb-6 mt-3">
                        <div class="p-4 bg-red-50 rounded-lg border border-red-100">
                            <p class="text-xs text-red-600 uppercase font-bold">Agreement Ended Date</p>
                            <p class="text-lg font-semibold text-red-900" x-text="activeLease.agreement_ended_at"></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden p-6 space-y-6">
                    
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-800">Payment Overview</h3>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                @click="$dispatch('open-manual-modal', { action: getManualInvoiceUrl() })"
                                class="inline-flex items-center px-4 py-2 h-10 text-sm font-medium rounded-lg text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 shadow-sm transition-all">
                                Generate Invoice
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
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Template ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Period</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Due Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount Details</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Invoice Items</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Remarks</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="other-payments-container" class="bg-white divide-y divide-gray-200">
                                    <!-- Loop through active lease's invoices -->
                                    <template x-for="invoice in (activeLease.invoices || [])" :key="invoice.id">
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <!-- Invoice No -->
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-bold text-indigo-600" x-text="invoice.invoice_no"></td>

                                            <!-- Document Template ID -->
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600" x-text="invoice.document_template_id"></td>

                                            <!-- Period -->
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900" x-text="invoice.period"></td>

                                            <!-- Due Date -->
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600" x-text="invoice.due_date"></td>

                                            <!-- Combined Amount Details Column -->
                                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                <div class="space-y-1">
                                                    <div class="text-gray-900 font-semibold">
                                                        Total: <span class="font-normal" x-text="'RM ' + invoice.total_amount"></span>
                                                    </div>
                                                    <div class="text-emerald-600 font-medium text-xs">
                                                        Paid: <span x-text="'RM ' + invoice.amount_paid"></span>
                                                    </div>
                                                    <div class="text-red-600 font-medium text-xs">
                                                        Balance: <span x-text="'RM ' + invoice.amount_balance"></span>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Invoice Items -->
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <template x-if="invoice.invoice_items && invoice.invoice_items.length > 0">
                                                    <div class="space-y-1">
                                                        <template x-for="subItem in invoice.invoice_items">
                                                            <div class="flex justify-between gap-4 text-xs">
                                                                <span class="font-medium text-gray-800 capitalize" x-text="subItem.description"></span>
                                                                <span class="text-gray-600 font-semibold" x-text="'RM ' + subItem.amount"></span>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </template>
                                                <template x-if="!invoice.invoice_items || invoice.invoice_items.length === 0">
                                                    <span class="text-gray-400 italic">No items found</span>
                                                </template>
                                            </td>

                                            <!-- Status Badge -->
                                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border uppercase"
                                                    :class="{
                                                        'bg-green-100 text-green-700 border-green-200': invoice.status === 'paid',
                                                        'bg-yellow-100 text-yellow-700 border-yellow-200': invoice.status === 'unpaid',
                                                        'bg-red-100 text-red-700 border-red-200': invoice.status === 'rejected',
                                                        'bg-gray-100 text-gray-500 border-gray-200 line-through': invoice.status === 'void'
                                                    }"
                                                    x-text="invoice.status">
                                                </span>
                                            </td>

                                            <!-- Remarks -->
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 italic max-w-xs truncate" x-text="invoice.remarks"></td>

                                            <!-- Actions -->
                                            <td class="px-4 py-4 whitespace-nowrap text-center text-sm font-medium">
                                                <div class="flex justify-center items-center gap-2">
                                                    <template x-if="invoice.status === 'unpaid'">
                                                        <button type="button" 
                                                            @click="$dispatch('open-payment', {
                                                                id: invoice.id, 
                                                                invoiceNo: invoice.invoice_no, 
                                                                totalAmount: invoice.amount_balance, 
                                                                invoiceItems: invoice.invoice_items,
                                                                actionUrl: `/admin/invoices/${invoice.id}`
                                                            })"
                                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200/60 rounded-lg transition-all shadow-sm">
                                                            <!-- Payment / Credit Card Icon -->
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                                            </svg>
                                                            <span>Record Payment</span>
                                                        </button>
                                                    </template>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>

                                    <x-modals.payment-modal />

                                    <!-- Empty State -->
                                    <template x-if="!activeLease.invoices || activeLease.invoices.length === 0">
                                        <tr>
                                            <td colspan="10" class="px-6 py-12 text-center text-sm text-gray-500 italic">No invoices found for this lease.</td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        @if($invoices->hasPages())
                            <div class="mt-4">
                                {{ $invoices->appends(['rent_page' => request('rent_page')])->links() }}
                            </div>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- 💡 修复点 3：在底部加入和 create 页面完全一样的 "暴力解锁防线" --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 方法 A：监听全域点击
            document.addEventListener('click', function() {
                setTimeout(() => {
                    const modal = document.getElementById('preview-modal');
                    if (!modal || modal.classList.contains('hidden') || getComputedStyle(modal).display === 'none') {
                        document.body.style.overflow = ''; 
                    }
                }, 50);
            });

            // 方法 B：监听键盘 Esc 键
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    setTimeout(() => {
                        const modal = document.getElementById('preview-modal');
                        if (!modal || modal.classList.contains('hidden') || getComputedStyle(modal).display === 'none') {
                            document.body.style.overflow = ''; 
                        } else {
                            if(modal) modal.classList.add('hidden'); 
                            document.body.style.overflow = ''; 
                        }
                    }, 50);
                }
            });
            
            // 方法 C：DOM 突变观察者
            const modalEl = document.getElementById('preview-modal');
            if (modalEl) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.attributeName === 'class' || mutation.attributeName === 'style') {
                            if (modalEl.classList.contains('hidden') || getComputedStyle(modalEl).display === 'none') {
                                document.body.style.overflow = ''; 
                            }
                        }
                    });
                });
                observer.observe(modalEl, { attributes: true });
            }
        });
    </script>
</x-app-layout>