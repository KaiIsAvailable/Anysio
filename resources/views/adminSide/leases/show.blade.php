<x-app-layout>
    @php
        // 将历史记录转换为 JS 容易读取的对象，Key 是 ID
        $historyJson = $leaseHistory->keyBy('id')->map(function($item) {
            return [
                'status' => $item->status,
                'checked_out_at' => $item->checked_out_at ? $item->checked_out_at_formatted : null,
                'agreement_ended_at' => $item->agreement_ended_at ? $item->agreement_ended_at_formatted : null,
                'start_date' => $item->start_date_formatted ?? null,
                'end_date' => $item->end_date_formatted ?? null,
                'term_type' => strtoupper($item->term_type) ?? 'N/A',
                'rent_price' => number_format($item->rent_price, 2),
                'deposit_mode' => strtoupper($item->deposit_mode) ?? 'SECURITY',
                'security_deposit' => ($item->security_deposit > 0) 
                    ? number_format($item->security_deposit, 2) 
                    : null,

                'utilities_deposit' => ($item->utilities_deposit > 0) 
                    ? number_format($item->utilities_deposit, 2) 
                    : null,
                'edit_url' => route('admin.leases.edit', $item->id),
                'stamping_status' => (bool)$item->stamping_status,
                'stamping_cert_path' => $item->stamping_cert_path,
                'stamping_reference_no' => $item->stamping_reference_no,
                'stamped_at' => $item->stamped_at ? $item->stamped_at_formatted : null,
                'can_stamp' => in_array($item->status, ['New', 'Renew']),
                'upload_url' => route('admin.leases.upload-stamping', $item->id),
                'view_url' => route('admin.leases.view-cert', $item->id),
                'agreement' => [
                    'title' => $item->agreement?->title ?? 'Agreement',
                    'content' => $item->agreement?->content ?? '', 
                ],
                'agreement_id' => $item->agreement_id,
                'tenant_name' => $item->tenant?->user->name ?? 'N/A',
                'tenant_ic' => $item->tenant?->ic_number ?? 'N/A',
                'owner_name' => $item->leasable?->owner?->user->name ?? 'N/A',
                'owner_ic' => $item->leasable?->owner?->ic_number ?? 'N/A',
                'property_address' => $item->leasable?->full_address ?? 'N/A',
                'property_type' => $item->leasableTypeLabel ?? 'N/A',
                'property_name' => $item->leasableName ?? 'N/A',
                'rent_mode' => strtoupper($item->term_type ?? 'N/A'),
                'check_out_date' => $item->checked_out_at?->format('d/m/Y') ?? 'N/A',
                'end_agreement_date' => $item->agreement_ended_at?->format('d/m/Y') ?? 'N/A',
            ];
        });
        $historyData = $historyJson->toArray();
    @endphp

    {{-- 1. 初始化数据，注意 activeId 加了单引号 --}}
    <div class="py-12 bg-gray-50 min-h-screen font-sans" 
        x-data="{ 
            activeId: '{{ old('active_id', $lease->id) }}',
            source: {{ $historyJson->isNotEmpty() ? $historyJson->toJson() : '{}' }},
            
            // --- 新增：初始化 loading 状态 ---
            loading: false, 

            openUpload: {{ $errors->has('stamping_reference_no') || $errors->has('stamping_cert') ? 'true' : 'false' }},
            shake: {{ $errors->any() ? 'true' : 'false' }},
            
            get activeLease() { 
                return (this.source && this.activeId) ? (this.source[this.activeId] || {}) : {} 
            },

            openPayment: false, 
            shakePayment: false,
            openPreview: false, 
            paymentData: { id: '', invoice_no: '', amount_due: 0, actionUrl: '' },

            openManual: false,
            manualActionUrl: '',

            getManualInvoiceUrl() {
                if (!this.activeId) return '#';
                return `{{ url('admin/payments/store-manual') }}/${this.activeId}`;
            },

            refreshTable() {
                if (!this.activeId || this.loading) return; // 防止重复点击
                
                // --- 修改：开始加载 ---
                this.loading = true;
                console.log('Fetching data for:', this.activeId);

                const url = `{{ url('/') }}/admin/leases/${this.activeId}/refresh-payments`;

                fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => {
                    if (!response.ok) throw new Error('Status: ' + response.status);
                    return response.json();
                })
                .then(data => {
                    const rentEl = document.getElementById('rent-payments-container');
                    const otherEl = document.getElementById('other-payments-container');
                    if (rentEl) rentEl.innerHTML = data.rentHtml;
                    if (otherEl) otherEl.innerHTML = data.otherHtml;
                })
                .catch(e => {
                    console.error('Table refresh failed:', e);
                })
                .finally(() => {
                    // --- 修改：无论成功或失败，停止加载 ---
                    this.loading = false; 
                });
            },

            init() {
                this.$watch('activeId', (newVal) => {
                    if (newVal) this.refreshTable();
                });
            }
        }"
        @click.stop
        @open-payment.window="paymentData = $event.detail; openPayment = true;"
        @open-manual-modal.window="openManual = true; manualActionUrl = $event.detail.action;"
    >
        
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
                {{-- 使用 x-cloak 防止闪烁 (如果你的 CSS 里有定义的话) --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6" 
                    x-show="activeId" 
                    x-data="{ 
                        {{-- 如果有特定字段的错误，初始化为 true --}}
                        openUpload: {{ $errors->has('stamping_reference_no') || $errors->has('stamping_cert') ? 'true' : 'false' }}, 
                        shake: {{ $errors->any() ? 'true' : 'false' }} 
                    }"
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
                                    
                                    {{-- 修改这一行：使用双叹号强转布尔，或者直接利用 JS 的真值判断 --}}
                                    <template x-if="activeLease.stamping_status && activeLease.stamping_cert_path">
                                        <div class="flex items-center gap-2">
                                            <div class="flex items-center gap-2">
                                                <span class="p-1 bg-emerald-100 text-emerald-600 rounded-full">
                                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                                    </span>
                                                    <a :href="activeLease.view_url" target="_blank" class="text-xs font-bold text-indigo-600 hover:underline">
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

                                    {{-- 对应修改：未上传的情况 --}}
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
                                        console.log('Button clicked! Active Lease Data:', activeLease); // 调试1: 看看数据进来了没
                                        
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
                                            '{rent_price}': activeLease.rent_price,
                                            '{deposit_mode}': activeLease.deposit_mode,
                                            '{security_deposit}': activeLease.security_deposit,
                                            '{utilities_deposit}': activeLease.utilities_deposit,
                                            '{start_date}': activeLease.start_date,
                                            '{end_date}': activeLease.end_date,
                                            '{check_out_date}': activeLease.check_out_date,
                                            '{end_agreement_date}': activeLease.end_agreement_date,
                                        };

                                        console.log('Replacements ready:', replacements); // 调试2: 看看替换表是否正确

                                        Object.keys(replacements).forEach(key => {
                                            const val = replacements[key];
                                            const regex = new RegExp(key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
                                            content = content.replace(regex, `<span class='text-inherit font-semibold'>${val}</span>`);
                                        });

                                        console.log('Dispatching event...'); // 调试3
                                        $dispatch('open-lease-preview', { 
                                            content: content, 
                                            title: activeLease.agreement?.title || 'Agreement Preview'
                                        });
                                    "
                                    class="px-3 py-1.5 bg-indigo-50 text-indigo-600 text-xs font-black rounded-lg border border-indigo-100 hover:bg-indigo-600 hover:text-white transition-all shadow-sm">
                                    VIEW AGREEMENT
                                </button>
                            </div>

                            <div class="h-6 w-[1px] bg-gray-200 mx-1"></div>
                            
                            {{-- 3. 关键：将 $lease 替换为页面上定义的变量 --}}
                            {{-- 这里假设你的后端已经通过路由或初始化传了一个总的 $lease 对象 --}}
                            <x-modals.lease-stamping-modal ::lease-id="activeId" />
                            <x-preview-agreement-modal ::lease-id="activeId"  />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500 uppercase font-bold">Start Date</p>
                            <p class="text-lg font-semibold" x-text="activeLease.start_date"></p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500 uppercase font-bold">End Date</p>
                            <p class="text-lg font-semibold" x-text="activeLease.end_date"></p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500 uppercase font-bold">Term Type</p>
                            <p class="text-lg font-semibold" x-text="activeLease.term_type"></p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500 uppercase font-bold">Rent Price</p>
                            <p class="text-lg font-semibold text-indigo-600">RM <span x-text="activeLease.rent_price"></span></p>
                        </div>
                        
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500 uppercase font-bold">Deposit Mode</p>
                            <p class="text-lg font-semibold" x-text="activeLease.deposit_mode"></p>
                        </div>
                        <div x-show="activeLease.security_deposit" class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500 uppercase font-bold">Security Deposit</p>
                            <p class="text-lg font-semibold" x-text="activeLease.security_deposit"></p>
                        </div>
                        <div x-show="activeLease.utilities_deposit" class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500 uppercase font-bold">Utilities Deposit</p>
                            <p class="text-lg font-semibold" x-text="activeLease.utilities_deposit"></p>
                        </div>
                        <div></div>
                    </div> <br>

                    <div x-show="activeLease.checked_out_at" class="mb-6">
                        <div class="p-4 bg-amber-50 rounded-lg border border-amber-100">
                            <p class="text-xs text-amber-600 uppercase font-bold">Check Out Date</p>
                            <p class="text-lg font-semibold text-amber-900" x-text="activeLease.checked_out_at"></p>
                        </div>
                    </div>

                    <div x-show="activeLease.agreement_ended_at" class="mb-6">
                        <div class="p-4 bg-red-50 rounded-lg border border-red-100">
                            <p class="text-xs text-red-600 uppercase font-bold">Agreement Ended Date</p>
                            <p class="text-lg font-semibold text-red-900" x-text="activeLease.agreement_ended_at"></p>
                        </div>
                    </div>

                    <div class="mt-6 pt-6 border-t border-gray-100 flex justify-end">
                        <a :href="activeLease.edit_url" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold hover:bg-indigo-700 transition">
                            Edit This Lease
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden p-6 space-y-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-800">Payment Overview</h3>
                        <div class="flex items-center gap-2">
                            {{-- Add Manual Invoice 按钮 --}}
                            <button type="button" 
                                {{-- 关键：确保 $lease->tenant 存在再生成路由，否则传 null --}}
                                @click="$dispatch('open-manual-modal', { 
                                    action: '{{ route('admin.payments.storeManualInvoice', $lease->tenant_id) }}'
                                })"
                                class="inline-flex items-center px-4 py-2 h-10 text-sm font-medium rounded-lg text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 shadow-sm transition-all">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Add Manual Invoice
                            </button>

                            <x-manual-invoice-modal />

                            <form action="{{ route('admin.payments.generateMonthlyInvoice', $lease->id) }}" method="POST" class="inline-block">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-4 py-2 h-10 text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition-all">
                                    Generate Invoice
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    {{-- Rent Outstanding 部分 --}}
                    <div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span class="w-1.5 h-4 bg-indigo-500 rounded-full"></span>
                            Rent Outstanding
                        </h4>
                        <div class="overflow-x-auto border border-gray-100 rounded-xl">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Invoice</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Type / Period</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount Details</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Method</th>
                                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="rent-payments-container" class="bg-white divide-y divide-gray-200">
                                    @include('adminSide.tenants.payments.paymentTable', [
                                        'payments' => $rentPayments,
                                        'emptyMessage' => 'No outstanding rent found.'
                                    ])
                                </tbody>
                            </table>
                        </div>
                        @if($rentPayments->hasPages())
                            <div class="mt-4">
                                {{ $rentPayments->appends(['other_page' => request('other_page')])->links() }}
                            </div>
                        @endif
                    </div>

                    <hr class="border-gray-100">

                    {{-- Other Bills & Miscellaneous 部分 --}}
                    <div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span class="w-1.5 h-4 bg-emerald-500 rounded-full"></span>
                            Other Bills & Miscellaneous
                        </h4>
                        <div class="overflow-x-auto border border-gray-100 rounded-xl">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Invoice</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Type / Period</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount Details</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Method</th>
                                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="other-payments-container" class="bg-white divide-y divide-gray-200">
                                    @include('adminSide.tenants.payments.paymentTable', [
                                        'payments' => $otherPayments,
                                        'emptyMessage' => 'No miscellaneous records found.'
                                    ])
                                </tbody>
                            </table>
                        </div>
                        @if($otherPayments->hasPages())
                            <div class="mt-4">
                                {{ $otherPayments->appends(['rent_page' => request('rent_page')])->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>