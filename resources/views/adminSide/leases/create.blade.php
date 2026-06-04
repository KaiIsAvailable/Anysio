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
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <x-form.form method="POST" action="{{ route('admin.leases.store') }}">
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

                        {{-- 2. Select Lease --}}
                        <div id="lease_select_container" class="grid grid-cols-1 md:grid-cols-1 gap-6 hidden">
                            <div class="relative">
                                <label class="block text-sm font-medium text-gray-700">Select Existing Lease</label>
                                <select name="lease_id" id="lease_id" 
                                        class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                    <option value="">-- Choose Lease --</option>
                                    @foreach($leases as $lease)
                                        <option value="{{ $lease->id }}" @selected(old('lease_id') == $lease->id)>
                                            {{ $lease->tenant->user->name ?? 'Tenant' }} 
                                            ({{ $lease->tenant->ic_number ?? 'IC' }}) - 
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
                                    <option value="property" {{ old('lease_selection', 'property') == 'property' ? 'selected' : '' }}>Entire Property</option>
                                    <option value="unit" {{ old('lease_selection') == 'unit' ? 'selected' : '' }}>Specific Unit</option>
                                    <option value="room" {{ old('lease_selection') == 'room' ? 'selected' : '' }}>Specific Room</option>
                                </select>
                            </div>

                            {{-- 2. 右边：动态切换的 Select Fields --}}
                            <div>
                                <div id="property_field" class="lease-field">
                                    <label class="block text-sm font-medium text-gray-700">Select Property</label>
                                    <select name="property_id" id="property_select_input" class="mt-1 w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                                        <option value="">-- Choose Property --</option>
                                        @foreach($properties as $p)
                                            <option value="{{ $p->id }}" data-owner="{{ $p->owner->name ?? 'N/A' }}" data-owner-id="{{$p->owner?->id}}" data-owner-ic="{{ $p->owner?->ic_number ?? 'N/A' }}" data-address="{{ $p->full_address }}" {{ old('property_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('property_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div id="unit_field" class="lease-field hidden">
                                    <label class="block text-sm font-medium text-gray-700">Select Unit</label>
                                    <select name="unit_id" id="unit_select_input" class="mt-1 w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                                        <option value="">-- Choose Unit --</option>
                                        @foreach($units as $u)
                                            <option value="{{ $u->id }}" data-owner="{{ $u->owner->name }}" data-owner-id="{{$u->owner?->id}}" data-owner-ic="{{ $u->owner->ic_number }}" data-address="{{ $u->full_address }}" {{ old('unit_id') == $u->id ? 'selected' : '' }}>{{ $u->unit_no }}</option>
                                        @endforeach
                                    </select>
                                    @error('unit_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div id="room_field" class="lease-field hidden">
                                    <label class="block text-sm font-medium text-gray-700">Select Room</label>
                                    <select name="room_id" id="room_select_input" class="mt-1 w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                                        <option value="">-- Choose Room --</option>
                                        @foreach($rooms as $r)
                                            <option value="{{ $r->id }}" data-owner="{{ $r->unit->owner->name }}" data-owner-id="{{$r->unit->owner?->id}}" data-owner-ic="{{ $r->unit->owner->ic_number }}" data-address="{{ $r->full_address }}" {{ old('room_id') == $r->id ? 'selected' : '' }}>{{ $r->room_no }}</option>
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
                                {{-- 💡 修复点 3: 增加 onchange 触发计算函数 --}}
                                <input type="date" id="start-date" name="start_date" value="{{ old('start_date') }}" data-preview="{start_date}" onchange="calculateAvailableFeeTypes()"
                                       class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @error('start_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">End Date</label>
                                {{-- 💡 修复点 4: 增加 onchange 触发计算函数 --}}
                                <input type="date" id="end-date" name="end_date" value="{{ old('end_date') }}" data-preview="{end_date}" onchange="calculateAvailableFeeTypes()"
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
                                    <option value="daily" @selected(old('term_type') == 'daily')>Daily Fee</option>
                                    <option value="weekly" @selected(old('term_type') == 'weekly')>Weekly Fee</option>
                                    <option value="monthly" @selected(old('term_type', 'monthly') == 'monthly')>Monthly Fee</option>
                                    <option value="yearly" @selected(old('term_type') == 'yearly')>Yearly Fee</option>
                                </select>
                                <p class="mt-1 text-xs text-gray-400 italic">Options auto-update based on date range.</p>
                                @error('term_type')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
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
                                        <option value="security_only" selected>Security Deposit Only</option>
                                        <option value="utilities_only">Utilities Deposit Only</option>
                                        <option value="both">Both (Security & Utilities)</option>
                                </select>
                            </div>

                            <div id="security_container">
                                <label class="block text-sm font-medium text-gray-700">Security Deposit (RM)</label>
                                <input type="text" id="security-deposit" name="security_deposit" value="{{ old('security_deposit') }}" data-preview="{security_deposit}"
                                    class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @error('security_deposit')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div id="utilities_container">
                                <label class="block text-sm font-medium text-gray-700">Utilities Deposit (RM)</label>
                                <input type="text" id="utilities-deposit" name="utilities_deposit" value="{{ old('utilities_deposit') }}" data-preview="{utilities_deposit}"
                                    class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @error('utilities_deposit')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div id="agreement_template_section" class="mt-4">
                            <div class="flex justify-between items-center mb-1">
                                <label for="agreement_id" class="block text-sm font-medium text-gray-700">
                                    Agreements Template
                                </label>
                                <button type="button" id="preview-btn" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors uppercase tracking-wider">
                                    Preview Template
                                </button>
                            </div>
                            <select id="agreement_id" name="agreement_id" 
                                class="block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                <option value="">-- Select Template --</option>
                                @foreach($templates as $template)
                                    <option value="{{ $template->id }}" data-agreement-user-id="{{$template->user->id}}" data-content="{{ $template->content }}" data-title="{{ $template->title }}" {{ old('agreement_id') == $template->id ? 'selected' : '' }}>
                                        {{ $template->title }} (v{{ $template->version }}) - {{ $template->user->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('agreement_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <x-preview-agreement-modal />

                        <!--
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
                        </div>-->

                    </div>

                    <div class="px-8 py-5 bg-white border-t border-gray-100 flex items-center justify-end">
                        <x-form.primary-button type="submit" loading="loading"
                                class="px-5 py-2.5">
                            Create Lease
                        </x-form.primary-button>
                    </div>
                </x-form.form>
            </div>
        </div>
    </div>
    
    <script>
        // ==========================================
        // 💡 修复点 5: 全新的日期 & Fee Type 计算逻辑
        // ==========================================
        function calculateAvailableFeeTypes() {
            const startInput = document.getElementById('start-date');
            const endInput = document.getElementById('end-date');
            const termSelect = document.getElementById('term_type');

            if (!startInput || !endInput || !termSelect) return;

            const startVal = startInput.value;
            const endVal = endInput.value;

            // 动态设置 End Date 的最小值为 Start Date (防止前端选错)
            if (startVal) {
                endInput.min = startVal;
            }

            if (!startVal || !endVal) return;

            const start = new Date(startVal);
            const end = new Date(endVal);

            // 前端拦截：如果 End Date 小于 Start Date，强制只给 daily 并退出
            if (end < start) {
                updateTermOptions(['daily']);
                return;
            }

            // 计算差值
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            let months = (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth());
            if (end.getDate() < start.getDate()) {
                months--; // 天数没到，不算满一个月
            }

            let years = end.getFullYear() - start.getFullYear();
            if (end.getMonth() < start.getMonth() || (end.getMonth() === start.getMonth() && end.getDate() < start.getDate())) {
                years--; // 月份或天数没到，不算满一年
            }

            // 根据你领导的业务逻辑推断可用选项
            let allowed = ['daily']; // 任何情况都可以选 daily

            if (diffDays >= 7 && months < 1) {
                allowed.push('weekly');
            } else if (months >= 1 && years < 1) {
                allowed.push('weekly', 'monthly');
            } else if (years >= 1) {
                allowed.push('weekly', 'monthly', 'yearly');
            }

            updateTermOptions(allowed);
        }

        function updateTermOptions(allowedTypes) {
            const termSelect = document.getElementById('term_type');
            const options = Array.from(termSelect.options);
            let selectedStillValid = false;

            options.forEach(opt => {
                if (allowedTypes.includes(opt.value)) {
                    opt.style.display = '';    // 显示选项
                    opt.disabled = false;      // 启用选项
                    if (opt.selected) selectedStillValid = true;
                } else {
                    opt.style.display = 'none'; // 隐藏选项
                    opt.disabled = true;        // 禁用选项 (防止部分浏览器不支持 display:none)
                    if (opt.selected) opt.selected = false;
                }
            });

            // 如果当前选中的值被禁用了，自动帮你选一个目前能用的最高级选项
            if (!selectedStillValid) {
                if (allowedTypes.includes('monthly')) termSelect.value = 'monthly';
                else if (allowedTypes.includes('weekly')) termSelect.value = 'weekly';
                else termSelect.value = 'daily';
                
                // 触发已有联动（如果有的话）
                if(typeof toggleLeaseInput === 'function') toggleLeaseInput();
            }
        }

        // ==========================================
        // 以下为你原本已有的完整代码逻辑
        // ==========================================
        const allLeases = @json($leases->keyBy('id'));

        document.addEventListener('DOMContentLoaded', function() {
            // 初始化检查一次 Fee Type
            calculateAvailableFeeTypes();

            const leaseSelect = document.getElementById('lease_id');

            leaseSelect.addEventListener('change', function() {
                const leaseId = this.value;
                if (!leaseId || !allLeases[leaseId]) return;

                const lease = allLeases[leaseId];

                let type = '';
                if (lease.leasable_type.includes('Property')) type = 'property';
                else if (lease.leasable_type.includes('Unit')) type = 'unit';
                else if (lease.leasable_type.includes('Room')) type = 'room';

                const selection = document.getElementById('lease_selection');
                if (selection) {
                    selection.value = type;
                    toggleLeaseInput(); 
                }

                const targetSelect = document.getElementById(type + '_id_select');
                if (targetSelect) {
                    targetSelect.value = lease.leasable_id;
                }

                document.getElementById('term_type').value = lease.term_type || 'monthly';
                document.getElementById('monthly-rent').value = lease.rent_price;

                if (lease.end_date) {
                    document.getElementById('start-date').value = lease.end_date;
                    // 旧租约拉过来后，也顺便计算一下新开放的 Fee Types
                    calculateAvailableFeeTypes();
                }

                const depositMode = document.getElementById('deposit_mode');
                const sDep = parseFloat(lease.security_deposit) || 0;
                const uDep = parseFloat(lease.utilities_deposit) || 0;

                if (sDep > 0 && uDep > 0) depositMode.value = 'both';
                else if (uDep > 0) depositMode.value = 'utilities_only';
                else depositMode.value = 'security_only';

                document.getElementById('security-deposit').value = sDep;
                document.getElementById('utilities-deposit').value = uDep;
                toggleDepositVisibility(); 

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
                const select = field.querySelector('select');
                
                if (field.id === selection + '_field') {
                    field.classList.remove('hidden');
                    if (select) select.disabled = false;
                } else {
                    field.classList.add('hidden');
                    if (select) {
                        select.disabled = true;
                        select.value = ""; 
                    }
                }
            });
        }

        function toggleDepositVisibility() {
            const mode = document.getElementById('deposit_mode').value;
            const securityDiv = document.getElementById('security_container');
            const utilitiesDiv = document.getElementById('utilities_container');

            if (mode === 'both') {
                securityDiv.classList.remove('hidden');
                utilitiesDiv.classList.remove('hidden');
            } else if (mode === 'security_only') {
                securityDiv.classList.remove('hidden');
                utilitiesDiv.classList.add('hidden');
                document.getElementById('utilities-deposit').value = ''; 
            } else if (mode === 'utilities_only') {
                securityDiv.classList.add('hidden');
                utilitiesDiv.classList.remove('hidden');
                document.getElementById('security-deposit').value = ''; 
            }
        }

        function toggleLeaseSelect() {
            const statusSelect = document.getElementById('lease-status');
            const newStatus = statusSelect.value;
            
            const urlParams = new URLSearchParams(window.location.search);
            const currentStatusInUrl = urlParams.get('status');

            if (newStatus !== currentStatusInUrl) {
                window.location.href = `{{ route('admin.leases.create') }}?status=${newStatus}`;
                return; 
            }

            const leaseContainer = document.getElementById('lease_select_container');
            const propertyContainer = document.getElementById('property_select_type');
            const tenantContainer = document.getElementById('tenant_field');
            const dateSection = document.getElementById('date_section');
            const feeSection = document.getElementById('fee_section');
            const depositSection = document.getElementById('deposit_section');
            //const utilitiesSection = document.getElementById('utilities_section');
            const checkOutSection = document.getElementById('check_out_section');
            const agreementEndSection = document.getElementById('agreement_end_section');
            const agreementTemplateSection = document.getElementById('agreement_template_section');

            if (newStatus === 'New' || !newStatus) {
                if(leaseContainer) leaseContainer.classList.add('hidden'); 
                propertyContainer.classList.remove('hidden');
                tenantContainer.classList.remove('hidden');
                dateSection.classList.remove('hidden');
                feeSection.classList.remove('hidden');
                depositSection.classList.remove('hidden');
                //utilitiesSection.classList.remove('hidden');
                checkOutSection.classList.add('hidden');
                agreementEndSection.classList.add('hidden');
                agreementTemplateSection.classList.remove('hidden');

            } 
            else if (newStatus === 'Renew') {
                if(leaseContainer) leaseContainer.classList.remove('hidden');
                propertyContainer.classList.add('hidden');
                tenantContainer.classList.add('hidden');
                dateSection.classList.remove('hidden');
                feeSection.classList.remove('hidden');
                depositSection.classList.remove('hidden');
                //utilitiesSection.classList.remove('hidden');
                checkOutSection.classList.add('hidden');
                agreementEndSection.classList.add('hidden');
                agreementTemplateSection.classList.remove('hidden');
            } 
            else if (newStatus === 'Check Out') {
                if(leaseContainer) leaseContainer.classList.remove('hidden');
                propertyContainer.classList.add('hidden');
                tenantContainer.classList.add('hidden');
                dateSection.classList.add('hidden'); 
                feeSection.classList.add('hidden');
                depositSection.classList.add('hidden');
                //utilitiesSection.classList.add('hidden');
                checkOutSection.classList.remove('hidden');
                agreementEndSection.classList.add('hidden');
                agreementTemplateSection.classList.add('hidden');
            }
            else if (newStatus === 'End Agreement') {
                if(leaseContainer) leaseContainer.classList.remove('hidden');
                propertyContainer.classList.add('hidden');
                tenantContainer.classList.add('hidden');
                dateSection.classList.add('hidden');
                feeSection.classList.add('hidden');
                depositSection.classList.add('hidden');
                //utilitiesSection.classList.add('hidden');
                checkOutSection.classList.add('hidden');
                agreementEndSection.classList.remove('hidden'); 
                agreementTemplateSection.classList.add('hidden');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleLeaseInput();
            toggleDepositVisibility();
            toggleLeaseSelect();
        });

        document.addEventListener('DOMContentLoaded', function() {
            const previewBtn = document.getElementById('preview-btn');
            const modal = document.getElementById('preview-modal');
            const modalContent = document.getElementById('modal-content');
            const modalTitle = document.getElementById('modal-title');
            const agreementSelect = document.getElementById('agreement_id');

            const closeElements = ['close-modal-btn', 'close-modal-bg', 'close-modal-footer'];

            previewBtn.addEventListener('click', function() {
                const selectedOption = agreementSelect.options[agreementSelect.selectedIndex];
                const content = selectedOption.getAttribute('data-content');
                const title = selectedOption.getAttribute('data-title');

                if (!content || agreementSelect.value === "") {
                    alert("Please select a template first.");
                    return;
                }

                modalTitle.innerText = "Preview: " + title;
                modalContent.innerHTML = content; 
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden'; 
            });

            closeElements.forEach(id => {
                document.getElementById(id).addEventListener('click', () => {
                    modal.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                });
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                    modal.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const previewBtn = document.getElementById('preview-btn');
            const agreementSelect = document.getElementById('agreement_id');

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
                
                document.querySelectorAll('[data-preview]').forEach(el => {
                    const placeholder = el.getAttribute('data-preview');
                    replacements[placeholder] = el.value || '';
                });

                const tenantSelect = document.getElementById('tenant_id');
                if (tenantSelect && tenantSelect.value) {
                    const fullText = tenantSelect.options[tenantSelect.selectedIndex].text;
                    const match = fullText.match(/(.+?)\s*\((.+?)\)/);
                    replacements['{tenant_name}'] = match ? match[1].trim() : fullText;
                    replacements['{tenant_ic}'] = match ? match[2].trim() : '';
                }

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
                
                const defaults = ['{utilities_deposit}', '{security_deposit}', '{rent_price}'];
                defaults.forEach(key => {
                    if (!replacements[key]) replacements[key] = '0.00';
                });

                const dateDefaults = ['{start_date}', '{end_date}', '{check_out_date}', '{end_agreement_date}'];
                dateDefaults.forEach(key => {
                    if (!replacements[key]) replacements[key] = 'N/A';
                });

                Object.keys(replacements).forEach(placeholder => {
                    const val = replacements[placeholder];
                    const regex = new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
                    content = content.replace(regex, `<span class="text-inherit font-semibold">${val}</span>`);
                });

                return { content, title };
            }

            if (previewBtn) {
                previewBtn.addEventListener('click', function() {
                    const result = generatePreviewContent();
                    
                    if (result) {
                        const modalContent = document.getElementById('modal-content');
                        if (modalContent) {
                            modalContent.innerHTML = result.content;
                            window.dispatchEvent(new CustomEvent('open-preview-modal'));
                        } else {
                            console.error("找不到 ID 为 modal-content 的元素，请检查组件文件。");
                        }
                    }
                });
            }
        });

        function filterTemplates() {
            // 1. 获取当前选中的租赁类型 (property/unit/room)
            const type = document.getElementById('lease_selection').value;
            const activeSelect = document.getElementById(type + '_select_input');
            
            // 2. 获取当前选中房产的 owner_id
            // 确保你的 property_select_input, unit_select_input, room_select_input 的 option 里都存了 data-owner-id
            const selectedOption = activeSelect.options[activeSelect.selectedIndex];
            const ownerId = selectedOption.getAttribute('data-owner-id'); 

            console.log("--- 模板过滤调试 ---");
            console.log("选中的房产 Owner ID:", ownerId);

            // 3. 过滤模板
            const agreementSelect = document.getElementById('agreement_id');
            
            Array.from(agreementSelect.options).forEach(option => {
                if (option.value === "") return; // 跳过默认提示选项
                
                // 使用你在 HTML 中定义的 data-agreement-user-id
                const templateUserId = option.getAttribute('data-agreement-user-id');
                
                console.log(`对比: 模板用户(${templateUserId}) vs 房产Owner(${ownerId})`);

                // 强转为字符串比较，防止类型不匹配
                if (ownerId && String(templateUserId) === String(ownerId)) {
                    option.style.display = 'block'; // 显示匹配的
                } else {
                    option.style.display = 'none';  // 隐藏不匹配的
                }
            });
            
            // 重置选择（如果当前选中的模板被隐藏了，则重置）
            if (agreementSelect.selectedIndex > 0 && agreementSelect.options[agreementSelect.selectedIndex].style.display === 'none') {
                agreementSelect.value = "";
            }
        }

        // 绑定事件：当房产选择改变时触发
        ['property_select_input', 'unit_select_input', 'room_select_input'].forEach(id => {
            document.getElementById(id).addEventListener('change', filterTemplates);
        });
    </script>
</x-app-layout>