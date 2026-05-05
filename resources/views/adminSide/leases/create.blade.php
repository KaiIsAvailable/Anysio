<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <nav class="flex mb-2" aria-label="Breadcrumb">
                        <a href="{{ route('admin.leases.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 flex items-center transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Back to Leases List
                        </a>
                    </nav>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Lease Controller</h1>
                    <p class="mt-2 text-sm text-gray-500">Set up a new tenant lease with utilities.</p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <form method="POST" action="{{ route('admin.leases.store') }}">
                    @csrf
                    <div class="p-8 space-y-8">
                        {{-- 1. Status 选择 --}}
                        <div class="grid grid-cols-1 md:grid-cols-1 gap-6 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Status</label>
                                <select name="status" id="lease-status" onchange="toggleLeaseSelect()" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                data-preview="{status}">
                                    <option value="New" {{ request('status') == 'New' ? 'selected' : '' }}>New</option>
                                    <option value="Renew" {{ request('status') == 'Renew' ? 'selected' : '' }}>Renew</option>
                                    <option value="Check Out" {{ request('status') == 'Check Out' ? 'selected' : '' }}>Check Out</option>
                                    <option value="End Agreement" {{ request('status') == 'End Agreement' ? 'selected' : '' }}>End Agreement</option>
                                </select>
                                @error('status')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- 2. Select Lease (初始状态设为 hidden) --}}
                        <div id="lease_select_container" class="grid grid-cols-1 md:grid-cols-1 gap-6 hidden">
                            <div class="relative">
                                <label class="block text-sm font-medium text-gray-700">Select Existing Lease</label>
                                <select name="lease_id" id="lease_id" 
                                        class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                    <option value="">-- Choose Lease --</option>
                                    @foreach($leases as $lease)
                                        <option value="{{ $lease->id }}" @selected(old('lease_id') == $lease->id)>
                                            {{-- 1. 租客名字 --}}
                                            {{ $lease->tenant->user->name ?? 'Tenant' }} 
                                            
                                            {{-- 2. 租客 IC --}}
                                            ({{ $lease->tenant->ic_number ?? 'IC' }}) - 

                                            {{-- 3. 动态显示资产名称 --}}
                                            @if($lease->leasable instanceof \App\Models\Property)
                                                {{ $lease->leasable->name }} (Entire)
                                            @elseif($lease->leasable instanceof \App\Models\Unit)
                                                {{ $lease->leasable->unit_no }} (Unit)
                                            @elseif($lease->leasable instanceof \App\Models\Room)
                                                {{ $lease->leasable->room_no }} (Room)
                                            @else
                                                N/A
                                            @endif

                                            {{ dateFormat($lease->start_date) . ' - ' . dateFormat($lease->end_date) ?? '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('lease_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div id="property_select_type" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- 1. 左边：选择租赁类型 --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Select Properties Type</label>
                                <select name="lease_selection" id="lease_selection" onchange="toggleLeaseInput()" 
                                        class="mt-1 w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm"
                                        data-preview="{property_type}">
                                    <option value="property" @selected(old('lease_selection') == 'property')>Entire Property</option>
                                    <option value="unit" @selected(old('lease_selection') == 'unit')>Specific Unit</option>
                                    <option value="room" @selected(old('lease_selection') == 'room')>Specific Room</option>
                                </select>
                            </div>

                            {{-- 2. 右边：动态切换的 Select Fields --}}
                            <div>
                                {{-- Property --}}
                                <div id="property_field" class="lease-field">
                                    <label class="block text-sm font-medium text-gray-700">Select Property</label>
                                    {{-- 加上 id="property_select_input" --}}
                                    <select name="property_id" id="property_select_input" class="mt-1 w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                                        <option value="">-- Choose Property --</option>
                                        @foreach($properties as $p)
                                            <option value="{{ $p->id }}" data-owner="{{ $p->owner->user?->name ?? 'N/A' }}" data-owner-ic="{{ $p->owner?->ic_number ?? 'N/A' }}" data-address="{{ $p->full_address }}">{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('property_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Unit (你原本就有了，保持一致即可) --}}
                                <div id="unit_field" class="lease-field hidden">
                                    <label class="block text-sm font-medium text-gray-700">Select Unit</label>
                                    <select name="unit_id" id="unit_select_input" class="mt-1 w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                                        <option value="">-- Choose Unit --</option>
                                        @foreach($units as $u)
                                            <option value="{{ $u->id }}" data-owner="{{ $u->owner->user->name }}" data-owner-ic="{{ $u->owner->ic_number }}" data-address="{{ $u->full_address }}">{{ $u->unit_no }}</option>
                                        @endforeach
                                    </select>
                                    @error('unit_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Room --}}
                                <div id="room_field" class="lease-field hidden">
                                    <label class="block text-sm font-medium text-gray-700">Select Room</label>
                                    {{-- 加上 id="room_select_input" --}}
                                    <select name="room_id" id="room_select_input" class="mt-1 w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                                        <option value="">-- Choose Room --</option>
                                        @foreach($rooms as $r)
                                            <option value="{{ $r->id }}" data-owner="{{ $r->unit->owner->user->name }}" data-owner-ic="{{ $r->unit->owner->ic_number }}" data-address="{{ $r->full_address }}">{{ $r->room_no }}</option>
                                        @endforeach
                                    </select>
                                    @error('room_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div id="tenant_field" class="grid grid-cols-1 md:grid-cols-1 gap-6">
                            <div class="relative">
                                <label class="block text-sm font-medium text-gray-700">Select Tenant</label>
                                
                                <select name="tenant_id" id="tenant_id" 
                                        class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                    <option value="">-- Choose Tenant --</option>
                                    @foreach($tenants as $tenant)
                                        <option value="{{ $tenant->id }}" @selected(old('tenant_id') == $tenant->id)>
                                            {{ $tenant->user?->name ?? 'Unknown' }} ({{ $tenant->ic_number ?? 'N/A' }})
                                        </option>
                                    @endforeach
                                </select>

                                @error('tenant_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div id="date_section" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Start Date</label>
                                <input type="date" id="start-date" name="start_date" value="{{ old('start_date') }}" data-preview="{start_date}"
                                       class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @error('start_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">End Date</label>
                                <input type="date" id="end-date" name="end_date" value="{{ old('end_date') }}" data-preview="{end_date}"
                                       class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @error('end_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div id="check_out_section" class="mt-4">
                            <label class="block text-sm font-medium text-gray-700">Check Out Date</label>
                            <input type="date" name="checked_out_at" value="{{ old('checked_out_at') }}" data-preview="{check_out_date}"
                                class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('checked_out_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div id="agreement_end_section" class="mt-4">
                            <label class="block text-sm font-medium text-gray-700">Agreement Ended Date</label>
                            <input type="date" name="agreement_ended_at" value="{{ old('agreement_ended_at') }}" data-preview="{end_agreement_date}"
                                class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('agreement_ended_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div id="fee_section" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Select Fee Type</label>
                                <select name="term_type" id="term_type" onchange="toggleLeaseInput()" data-preview="{rent_mode}"
                                        class="mt-1 w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                                    <option value="monthly" @selected(old('term_type') == 'monthly')>Monthly Fee</option>
                                    <option value="daily" @selected(old('term_type') == 'daily')>Daily Fee</option>
                                    <option value="weekly" @selected(old('term_type') == 'weekly')>Weekly Fee</option>
                                    <option value="yearly" @selected(old('term_type') == 'yearly')>Yearly Fee</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Rent Price(RM)</label>
                                <input type="text" id="monthly-rent" name="rent_price" value="{{ old('rent_price') }}" data-preview="{rent_price}"
                                       class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @error('rent_price')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div id="deposit_section" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="max-w-xs">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Deposit Collection Mode</label>
                                <select id="deposit_mode" name="deposit_mode" onchange="toggleDepositVisibility()" data-preview="{deposit_mode}"
                                        class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm text-sm">
                                        <option value="security_only" selected>Security Deposit Only</option> {{-- Default Security --}}
                                        <option value="utilities_only">Utilities Deposit Only</option>
                                        <option value="both">Both (Security & Utilities)</option>
                                </select>
                            </div>

                            {{-- Security 容器 --}}
                            <div id="security_container">
                                <label class="block text-sm font-medium text-gray-700">Security Deposit (RM)</label>
                                <input type="text" id="security-deposit" name="security_deposit" value="{{ old('security_deposit') }}" data-preview="{security_deposit}"
                                    class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @error('security_deposit')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Utilities 容器 --}}
                            <div id="utilities_container">
                                <label class="block text-sm font-medium text-gray-700">Utilities Deposit (RM)</label>
                                <input type="text" id="utilities-deposit" name="utilities_deposit" value="{{ old('utilities_deposit') }}" data-preview="{utilities_deposit}"
                                    class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @error('utilities_deposit')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <label for="agreement_id" class="block text-sm font-medium text-gray-700">
                                    Agreements Template
                                </label>
                                {{-- Preview 按钮 --}}
                                <button type="button" id="preview-btn" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors uppercase tracking-wider">
                                    Preview Template
                                </button>
                            </div>
                            <select id="agreement_id" name="agreement_id" 
                                class="block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                <option value="">-- Select Template --</option>
                                @foreach($templates as $template)
                                    {{-- 将 content 存入 data-content 属性 --}}
                                    <option value="{{ $template->id }}" data-content="{{ $template->content }}" data-title="{{ $template->title }}">
                                        {{ $template->title }} (v{{ $template->version }}) - {{ $template->owner->user->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('agreement_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <x-preview-agreement-modal />

                        <div id="utilities_section" class="pt-4 border-t border-gray-100">
                            <h2 class="text-lg font-semibold text-slate-900 mb-4">Utilities (RM)</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                    <h3 class="text-sm font-semibold text-slate-900 mb-3">Water</h3>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500">Previous</label>
                                            <input type="text" id="water-prev" name="utilities[water][prev]" value="{{ old('utilities.water.prev') }}"
                                                   class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                            @error('utilities.water.prev')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500">Current</label>
                                            <input type="text" id="water-curr" name="utilities[water][curr]" value="{{ old('utilities.water.curr') }}"
                                                   class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                            @error('utilities.water.curr')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                    <h3 class="text-sm font-semibold text-slate-900 mb-3">Electric</h3>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500">Previous</label>
                                            <input type="text" id="electric-prev" name="utilities[electric][prev]" value="{{ old('utilities.electric.prev') }}"
                                                   class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                            @error('utilities.electric.prev')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500">Current</label>
                                            <input type="text" id="electric-curr" name="utilities[electric][curr]" value="{{ old('utilities.electric.curr') }}"
                                                   class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                            @error('utilities.electric.curr')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="px-8 py-5 bg-white border-t border-gray-100 flex items-center justify-end">
                        <button type="submit"
                                class="inline-flex items-center justify-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition-all duration-200">
                            Create Lease
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        const allLeases = @json($leases->keyBy('id'));

        document.addEventListener('DOMContentLoaded', function() {
            const leaseSelect = document.getElementById('lease_id');

            leaseSelect.addEventListener('change', function() {
                const leaseId = this.value;
                if (!leaseId || !allLeases[leaseId]) return;

                const lease = allLeases[leaseId];

                // 1. 设置类型并切换显示
                let type = '';
                if (lease.leasable_type.includes('Property')) type = 'property';
                else if (lease.leasable_type.includes('Unit')) type = 'unit';
                else if (lease.leasable_type.includes('Room')) type = 'room';

                const selection = document.getElementById('lease_selection');
                if (selection) {
                    selection.value = type;
                    toggleLeaseInput(); // 这一步会把正确的 field 显示出来，并取消 disabled
                }

                // 2. 精准填充 ID (使用 ID 选择器更稳定)
                const targetSelect = document.getElementById(type + '_id_select');
                if (targetSelect) {
                    targetSelect.value = lease.leasable_id;
                }

                // 4. 自动填入价格和周期
                document.getElementById('term_type').value = lease.term_type || 'monthly';
                document.getElementById('monthly-rent').value = lease.rent_price;

                // 5. 自动填入日期 (Renew 建议：Start Date = 旧租约的 End Date)
                if (lease.end_date) {
                    document.getElementById('start-date').value = lease.end_date;
                }

                // 6. 自动处理押金模式
                const depositMode = document.getElementById('deposit_mode');
                const sDep = parseFloat(lease.security_deposit) || 0;
                const uDep = parseFloat(lease.utilities_deposit) || 0;

                if (sDep > 0 && uDep > 0) depositMode.value = 'both';
                else if (uDep > 0) depositMode.value = 'utilities_only';
                else depositMode.value = 'security_only';

                document.getElementById('security-deposit').value = sDep;
                document.getElementById('utilities-deposit').value = uDep;
                toggleDepositVisibility(); // 调用你原本的函数来显示/隐藏押金输入框

                // 7. 自动填入水电费 Previous (拿旧租约的 Current 当作新租约的 Previous)
                // 假设你数据库里存的是 JSON
                let utils = lease.utilities;
                if (typeof utils === 'string') utils = JSON.parse(utils);
                
                if (utils) {
                    if (utils.water) document.getElementById('water-prev').value = utils.water.curr || 0;
                    if (utils.electric) document.getElementById('electric-prev').value = utils.electric.curr || 0;
                }
            });
        });

        function toggleLeaseInput() {
            const selection = document.getElementById('lease_selection').value;
            const fields = document.querySelectorAll('.lease-field');
            
            fields.forEach(field => {
                // 找到该 div 下的 select 元素
                const select = field.querySelector('select');
                
                if (field.id === selection + '_field') {
                    // 显示并启用当前选中的字段
                    field.classList.remove('hidden');
                    if (select) select.disabled = false;
                } else {
                    // 隐藏并禁用不相关的字段
                    field.classList.add('hidden');
                    if (select) {
                        select.disabled = true;
                        select.value = ""; // 建议清空隐藏字段的值，防止干扰
                    }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleLeaseInput();
        });

        function toggleDepositVisibility() {
            const mode = document.getElementById('deposit_mode').value;
            const securityDiv = document.getElementById('security_container');
            const utilitiesDiv = document.getElementById('utilities_container');

            // 先全部隐藏，再根据逻辑显示
            if (mode === 'both') {
                securityDiv.classList.remove('hidden');
                utilitiesDiv.classList.remove('hidden');
            } else if (mode === 'security_only') {
                securityDiv.classList.remove('hidden');
                utilitiesDiv.classList.add('hidden');
                document.getElementById('utilities-deposit').value = ''; // 隐藏时清空
            } else if (mode === 'utilities_only') {
                securityDiv.classList.add('hidden');
                utilitiesDiv.classList.remove('hidden');
                document.getElementById('security-deposit').value = ''; // 隐藏时清空
            }
        }

        // 页面初始化
        document.addEventListener('DOMContentLoaded', function() {
            toggleDepositVisibility();
        });

        // --- 修改后的 toggleLeaseSelect 函数 ---
        function toggleLeaseSelect() {
            const statusSelect = document.getElementById('lease-status');
            const newStatus = statusSelect.value;
            
            // 获取当前 URL 中的 status 参数
            const urlParams = new URLSearchParams(window.location.search);
            const currentStatusInUrl = urlParams.get('status');

            // 关键点：只有当选中的 status 和 URL 里的不一样时，才跳转
            // 这样页面刷新回来后，因为 newStatus == currentStatusInUrl，就不会再跳转了
            if (newStatus !== currentStatusInUrl) {
                window.location.href = `{{ route('admin.leases.create') }}?status=${newStatus}`;
                return; // 既然要跳转了，后面的代码不需要执行
            }

            // --- 以下是原本控制 UI 显示/隐藏的逻辑 (保留) ---
            const leaseContainer = document.getElementById('lease_select_container');
            const propertyContainer = document.getElementById('property_select_type');
            const tenantContainer = document.getElementById('tenant_field');
            const dateSection = document.getElementById('date_section');
            const feeSection = document.getElementById('fee_section');
            const depositSection = document.getElementById('deposit_section');
            const utilitiesSection = document.getElementById('utilities_section');
            const checkOutSection = document.getElementById('check_out_section');
            const agreementEndSection = document.getElementById('agreement_end_section');

            if (newStatus === 'New' || !newStatus) {
                if(leaseContainer) leaseContainer.classList.add('hidden'); // New 的时候不需要选旧租约
                propertyContainer.classList.remove('hidden');
                tenantContainer.classList.remove('hidden');
                dateSection.classList.remove('hidden');
                feeSection.classList.remove('hidden');
                depositSection.classList.remove('hidden');
                utilitiesSection.classList.remove('hidden');
                checkOutSection.classList.add('hidden');
                agreementEndSection.classList.add('hidden');
            } 
            else if (newStatus === 'Renew') {
                if(leaseContainer) leaseContainer.classList.remove('hidden');
                propertyContainer.classList.add('hidden');
                tenantContainer.classList.add('hidden');
                dateSection.classList.remove('hidden');
                feeSection.classList.remove('hidden');
                depositSection.classList.remove('hidden');
                utilitiesSection.classList.remove('hidden');
                checkOutSection.classList.add('hidden');
                agreementEndSection.classList.add('hidden');
            } 
            else if (newStatus === 'Check Out') {
                if(leaseContainer) leaseContainer.classList.remove('hidden');
                propertyContainer.classList.add('hidden');
                tenantContainer.classList.add('hidden');
                dateSection.classList.add('hidden'); // 隐藏标准的 Start/End Date
                feeSection.classList.add('hidden');
                depositSection.classList.add('hidden');
                utilitiesSection.classList.add('hidden');
                checkOutSection.classList.remove('hidden');
                agreementEndSection.classList.add('hidden');
            }
            else if (newStatus === 'End Agreement') {
                if(leaseContainer) leaseContainer.classList.remove('hidden');
                propertyContainer.classList.add('hidden');
                tenantContainer.classList.add('hidden');
                dateSection.classList.add('hidden');
                feeSection.classList.add('hidden');
                depositSection.classList.add('hidden');
                utilitiesSection.classList.add('hidden');
                checkOutSection.classList.add('hidden');
                agreementEndSection.classList.remove('hidden'); // 仅显示 End Agreement 日期
            }
        }

        // 页面加载时的初始化逻辑
        document.addEventListener('DOMContentLoaded', function() {
            // 运行一次逻辑来正确显示/隐藏字段，但不会触发跳转
            toggleLeaseSelect();
        });

        // 页面加载时运行一次，处理 validation error 后的回显情况
        document.addEventListener('DOMContentLoaded', function() {
            toggleLeaseSelect();
        });

        document.addEventListener('DOMContentLoaded', function() {
            const previewBtn = document.getElementById('preview-btn');
            const modal = document.getElementById('preview-modal');
            const modalContent = document.getElementById('modal-content');
            const modalTitle = document.getElementById('modal-title');
            const agreementSelect = document.getElementById('agreement_id');

            // 关闭 Modal 的元素
            const closeElements = ['close-modal-btn', 'close-modal-bg', 'close-modal-footer'];

            // 点击 Preview 按钮
            previewBtn.addEventListener('click', function() {
                const selectedOption = agreementSelect.options[agreementSelect.selectedIndex];
                const content = selectedOption.getAttribute('data-content');
                const title = selectedOption.getAttribute('data-title');

                //console.log("Selected Content:", content);

                if (!content || agreementSelect.value === "") {
                    alert("Please select a template first.");
                    return;
                }

                // 注入内容并显示 Modal
                modalTitle.innerText = "Preview: " + title;
                modalContent.innerHTML = content; // 这里会解析 HTML
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden'; // 防止背景滚动
            });

            // 统一关闭逻辑
            closeElements.forEach(id => {
                document.getElementById(id).addEventListener('click', () => {
                    modal.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                });
            });

            // ESC 键关闭
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                    modal.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            // --- 1. 元素获取 ---
            const previewBtn = document.getElementById('preview-btn');
            const agreementSelect = document.getElementById('agreement_id');
            // 注意：不再需要获取 modal 元素，因为 Alpine 会处理显示

            // --- 2. 核心逻辑：保持不变，只需确保返回值 ---
            function generatePreviewContent() {
                if (!agreementSelect || !agreementSelect.value) {
                    alert("Please select a template first.");
                    return null;
                }

                const selectedOption = agreementSelect.options[agreementSelect.selectedIndex];
                let content = selectedOption.getAttribute('data-content');
                const title = selectedOption.getAttribute('data-title');

                if (!content) return null;

                const replacements = {};
                
                // A. 自动抓取 data-preview
                document.querySelectorAll('[data-preview]').forEach(el => {
                    const placeholder = el.getAttribute('data-preview');
                    replacements[placeholder] = el.value || '';
                });

                // B. Tenant 逻辑
                const tenantSelect = document.getElementById('tenant_id');
                if (tenantSelect && tenantSelect.value) {
                    const fullText = tenantSelect.options[tenantSelect.selectedIndex].text;
                    const match = fullText.match(/(.+?)\s*\((.+?)\)/);
                    replacements['{tenant_name}'] = match ? match[1].trim() : fullText;
                    replacements['{tenant_ic}'] = match ? match[2].trim() : '';
                }

                // C. Property/Owner 逻辑
                const leaseSelectionEl = document.getElementById('lease_selection');
                if (leaseSelectionEl) {
                    const leaseType = leaseSelectionEl.value;
                    const activeSelect = document.getElementById(`${leaseType}_select_input`);

                    if (activeSelect && activeSelect.value) {
                        const opt = activeSelect.options[activeSelect.selectedIndex];
                        replacements['{property_name}'] = opt.text.trim();
                        replacements['{property_address}'] = opt.getAttribute('data-address') || '';
                        replacements['{owner_name}'] = opt.getAttribute('data-owner') || '';
                        replacements['{owner_ic}'] = opt.getAttribute('data-owner-ic') || '';
                    }
                }
                
                // 默认值填充
                const defaults = ['{utilities_deposit}', '{security_deposit}', '{rent_price}'];
                defaults.forEach(key => {
                    if (!replacements[key]) replacements[key] = '0.00';
                });

                const dateDefaults = ['{start_date}', '{end_date}', '{check_out_date}', '{end_agreement_date}'];
                dateDefaults.forEach(key => {
                    if (!replacements[key]) replacements[key] = 'N/A';
                });

                // 执行替换
                Object.keys(replacements).forEach(placeholder => {
                    const val = replacements[placeholder];
                    const regex = new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
                    content = content.replace(regex, `<span class="text-inherit font-semibold">${val}</span>`);
                });

                return { content, title };
            }

            // --- 3. 事件监听 (关键修改点) ---

            if (previewBtn) {
                previewBtn.addEventListener('click', function() {
                    const result = generatePreviewContent();
                    
                    if (result) {
                        // 1. 注入内容到 Component 里的 ID
                        const modalContent = document.getElementById('modal-content');
                        if (modalContent) {
                            modalContent.innerHTML = result.content;
                        }

                        // 2. 发送自定义事件给 Alpine.js
                        // 这对应了组件里的 @open-preview-modal.window="openPreview = true"
                        window.dispatchEvent(new CustomEvent('open-preview-modal'));
                    }
                });
            }

            // 移除旧的 closeElements 监听逻辑，因为 Alpine 的 @click="openPreview = false" 已经接管了关闭功能
        });

        document.addEventListener('DOMContentLoaded', function() {
            const previewBtn = document.getElementById('preview-btn');
            
            if (previewBtn) {
                previewBtn.addEventListener('click', function() {
                    // 1. 执行你原本的数据替换逻辑
                    const result = generatePreviewContent(); // 调用你那个复杂的替换函数
                    
                    if (result) {
                        // 2. 注入内容
                        const modalContent = document.getElementById('modal-content');
                        if (modalContent) {
                            modalContent.innerHTML = result.content;
                            
                            // 3. 核心修复：直接派发事件
                            // 确保 CustomEvent 的名字和组件里的 @... 一致
                            window.dispatchEvent(new CustomEvent('open-preview-modal'));
                        } else {
                            console.error("找不到 ID 为 modal-content 的元素，请检查组件文件。");
                        }
                    }
                });
            }
        });
    </script>
</x-app-layout>