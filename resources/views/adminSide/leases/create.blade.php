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
                                    @php
                                    $leasePropertyType = '';
                                    $leasePropertyName = '';
                                    $leaseOwnerName = '';
                                    $leaseOwnerIc = '';
                                    $leaseOwnerId = '';

                                    // 💡 修复点：连跳两层关联找到真实的 Owner IC
                                    if ($lease->leasable instanceof \App\Models\Property) {
                                        $leasePropertyType = 'property';
                                        $leasePropertyName = $lease->leasable->name ?? '';
                                        $leaseOwnerName = $lease->leasable->owner?->name ?? '';
                                        $leaseOwnerIc = $lease->leasable->owner?->owner?->ic_number ?? '';
                                        $leaseOwnerId = $lease->leasable->owner?->id ?? '';
                                    } elseif ($lease->leasable instanceof \App\Models\Unit) {
                                        $leasePropertyType = 'unit';
                                        $leasePropertyName = $lease->leasable->unit_no ?? '';
                                        $leaseOwnerName = $lease->leasable->owner?->name ?? '';
                                        $leaseOwnerIc = $lease->leasable->owner?->owner?->ic_number ?? '';
                                        $leaseOwnerId = $lease->leasable->owner?->id ?? '';
                                    } elseif ($lease->leasable instanceof \App\Models\Room) {
                                        $leasePropertyType = 'room';
                                        $leasePropertyName = $lease->leasable->room_no ?? '';
                                        $leaseOwnerName = $lease->leasable->unit?->owner?->name ?? '';
                                        $leaseOwnerIc = $lease->leasable->unit?->owner?->owner?->ic_number ?? '';
                                        $leaseOwnerId = $lease->leasable->unit?->owner?->id ?? '';
                                    }
                                    @endphp
                                    <option value="{{ $lease->id }}"
                                        data-property-type="{{ $leasePropertyType }}"
                                        data-property-name="{{ $leasePropertyName }}"
                                        data-property-address="{{ optional($lease->leasable)->full_address ?? '' }}"
                                        data-owner-name="{{ $leaseOwnerName }}"
                                        data-owner-ic="{{ $leaseOwnerIc }}"
                                        data-owner-id="{{ $leaseOwnerId }}"
                                        @selected(old('lease_id')==$lease->id)>
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
                                        {{-- 💡 修复点：$p->owner?->owner?->ic_number --}}
                                        <option value="{{ $p->id }}" data-owner="{{ $p->owner?->name ?? 'N/A' }}" data-owner-id="{{ $p->owner?->id }}" data-owner-ic="{{ $p->owner?->owner?->ic_number ?? 'N/A' }}" data-address="{{ $p->full_address }}" {{ old('property_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
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
                                        {{-- 💡 修复点：$u->owner?->owner?->ic_number --}}
                                        <option value="{{ $u->id }}" data-owner="{{ $u->owner?->name ?? 'N/A' }}" data-owner-id="{{ $u->owner?->id }}" data-owner-ic="{{ $u->owner?->owner?->ic_number ?? 'N/A' }}" data-address="{{ $u->full_address }}" {{ old('unit_id') == $u->id ? 'selected' : '' }}>{{ $u->unit_no }}</option>
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
                                        {{-- 💡 修复点：$r->unit?->owner?->owner?->ic_number --}}
                                        <option value="{{ $r->id }}" data-owner="{{ $r->unit?->owner?->name ?? 'N/A' }}" data-owner-id="{{ $r->unit?->owner?->id }}" data-owner-ic="{{ $r->unit?->owner?->owner?->ic_number ?? 'N/A' }}" data-address="{{ $r->full_address }}" {{ old('room_id') == $r->id ? 'selected' : '' }}>{{ $r->room_no }}</option>
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
                                    <option value="{{ $tenant->id }}" @selected(old('tenant_id')==$tenant->id)>
                                        {{ $tenant->user?->name ?? 'Unknown' }} ({{ $tenant->ic_number ?? $tenant->passport ?? 'N/A' }})
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
                                <x-form.date-input id="start-date" name="start_date" label="Start Date" onchange="calculateAvailableFeeTypes()" />
                                @error('start_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">End Date</label>
                                <x-form.date-input id="end-date" name="end_date" label="End Date" onchange="calculateAvailableFeeTypes()" />
                                @error('end_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div id="check_out_section" class="mt-4">
                            <label class="block text-sm font-medium text-gray-700">Check Out Date</label>
                            <x-form.date-input id="check-out-date" name="checked_out_at" label="Check Out Date" />
                            @error('checked_out_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div id="agreement_end_section" class="mt-4">
                            <label class="block text-sm font-medium text-gray-700">Agreement Ended Date</label>
                            <x-form.date-input id="agreement-end-date" name="agreement_ended_at" label="Agreement Ended Date" />
                            @error('agreement_ended_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div id="fee_section" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Select Fee Type</label>
                                <select name="term_type" id="term_type" onchange="toggleLeaseInput()" data-preview="{rent_mode}"
                                    class="mt-1 w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                                    <option value="daily" @selected(old('term_type')=='daily' )>Daily Fee</option>
                                    <option value="weekly" @selected(old('term_type')=='weekly' )>Weekly Fee</option>
                                    <option value="monthly" @selected(old('term_type', 'monthly' )=='monthly' )>Monthly Fee</option>
                                    <option value="yearly" @selected(old('term_type')=='yearly' )>Yearly Fee</option>
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

                        <div id="bring_forward_notice" class="hidden col-span-3 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <p class="text-sm text-blue-800">
                                <span class="font-semibold">Previous Deposits:</span>
                                <span id="bf-security">RM 0.00</span> (Security) |
                                <span id="bf-utilities">RM 0.00</span> (Utilities)
                                <span class="block mt-1 text-xs text-blue-600 italic">These amounts are brought forward from your previous lease.</span>
                            </p>
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
        // 1. 日期 & Fee Type 计算逻辑
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

            // 根据业务逻辑推断可用选项
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
                    opt.style.display = ''; // 显示选项
                    opt.disabled = false; // 启用选项
                    if (opt.selected) selectedStillValid = true;
                } else {
                    opt.style.display = 'none'; // 隐藏选项
                    opt.disabled = true; // 禁用选项
                    if (opt.selected) opt.selected = false;
                }
            });

            if (!selectedStillValid) {
                if (allowedTypes.includes('monthly')) termSelect.value = 'monthly';
                else if (allowedTypes.includes('weekly')) termSelect.value = 'weekly';
                else termSelect.value = 'daily';

                if (typeof toggleLeaseInput === 'function') toggleLeaseInput();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const startInput = document.querySelector('#start-date');
            const endInput = document.querySelector('#end-date');

            if (startInput && endInput) {
                const startPicker = startInput._flatpickr;
                const endPicker = endInput._flatpickr;

                function syncDateConstraints() {
                    if (startPicker && startPicker.selectedDates.length > 0) {
                        endPicker.set('minDate', startPicker.selectedDates[0]);
                    }
                }

                syncDateConstraints();

                startPicker.config.onChange.push(function(selectedDates) {
                    if (selectedDates.length > 0) {
                        endPicker.set('minDate', selectedDates[0]);
                        if (typeof calculateAvailableFeeTypes === 'function') {
                            calculateAvailableFeeTypes();
                        }
                    }
                });

                endPicker.config.onChange.push(function() {
                    if (typeof calculateAvailableFeeTypes === 'function') {
                        calculateAvailableFeeTypes();
                    }
                });
            }
        });

        // ==========================================
        // 2. 基础表单交互联动
        // ==========================================
        const allLeases = @json($leases -> keyBy('id'));
        const leasePreviewData = @json($leasePreviewData -> keyBy('id'));

        document.addEventListener('DOMContentLoaded', function() {
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

                const targetSelect = document.getElementById(type + '_select_input');
                if (targetSelect) {
                    targetSelect.value = lease.leasable_id;
                }

                document.getElementById('term_type').value = lease.term_type || 'monthly';
                document.getElementById('monthly-rent').value = lease.rent_price;

                if (lease.end_date) {
                    let startDateObj = new Date(lease.end_date);
                    startDateObj.setDate(startDateObj.getDate() + 1);

                    const startInput = document.getElementById('start-date');
                    const endInput = document.getElementById('end-date');

                    if (startInput._flatpickr) {
                        startInput._flatpickr.setDate(startDateObj);
                    } else {
                        startInput.value = startDateObj.toISOString().split('T')[0];
                    }

                    if (endInput._flatpickr) {
                        endInput._flatpickr.set('minDate', startDateObj);
                        if (endInput._flatpickr.selectedDates.length > 0 &&
                            endInput._flatpickr.selectedDates[0] < startDateObj) {
                            endInput._flatpickr.clear();
                        }
                    }

                    calculateAvailableFeeTypes();
                }

                document.getElementById('security-deposit').value = '';
                document.getElementById('utilities-deposit').value = '';

                const sDep = parseFloat(lease.security_deposit) || 0;
                const uDep = parseFloat(lease.utilities_deposit) || 0;

                const leaseDataMap = @json($leasePreviewData -> keyBy('id'));
                const leaseData = leaseDataMap[leaseId];
                const bfSec = document.getElementById('bf-security');
                const bfUtil = document.getElementById('bf-utilities');

                if (bfSec) bfSec.innerText = 'RM ' + parseFloat(leaseData.cumulative_security).toFixed(2);
                if (bfUtil) bfUtil.innerText = 'RM ' + parseFloat(leaseData.cumulative_utilities).toFixed(2);

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
            const newStatus = statusSelect.value || 'New';

            const urlParams = new URLSearchParams(window.location.search);
            if (newStatus !== urlParams.get('status')) {
                window.location.href = `{{ route('admin.leases.create') }}?status=${newStatus}`;
                return;
            }

            const sections = {
                'lease_select_container': ['Renew', 'Check Out', 'End Agreement'].includes(newStatus),
                'property_select_type': newStatus === 'New',
                'tenant_field': newStatus === 'New',
                'date_section': ['New', 'Renew'].includes(newStatus),
                'fee_section': ['New', 'Renew'].includes(newStatus),
                'deposit_section': ['New', 'Renew'].includes(newStatus),
                'bring_forward_notice': newStatus === 'Renew',
                'check_out_section': newStatus === 'Check Out',
                'agreement_end_section': newStatus === 'End Agreement',
                'agreement_template_section': ['New', 'Renew'].includes(newStatus)
            };

            Object.keys(sections).forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;
                const isVisible = sections[id];
                el.classList.toggle('hidden', !isVisible);
                const inputs = el.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    input.disabled = !isVisible;
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleLeaseInput();
            toggleDepositVisibility();
            toggleLeaseSelect();
        });

        // ==========================================
        // 💡 预览模板的【统一控制中心】
        // ==========================================
        document.addEventListener('DOMContentLoaded', function() {
            const previewBtn = document.getElementById('preview-btn');
            const agreementSelect = document.getElementById('agreement_id');

            if (!previewBtn) return;

            // 彻底解决 Scroll 锁定防线：无论任何形式的弹窗消失，强制解锁滚动
            document.addEventListener('click', function() {
                setTimeout(() => {
                    const modal = document.getElementById('preview-modal');
                    if (!modal || modal.classList.contains('hidden') || getComputedStyle(modal).display === 'none') {
                        document.body.style.overflow = '';
                    }
                }, 50);
            });

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

            // 提取并替换资料的核心逻辑
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

                // 抓取基础表单数据
                // 抓取基础表单数据
                document.querySelectorAll('[data-preview]').forEach(el => {
                    const placeholder = el.getAttribute('data-preview');
                    replacements[placeholder] = el.value || '';
                });

                // 手动且精准地抓取日期
                const sd = document.getElementById('start-date');
                if (sd && sd.value) replacements['{start_date}'] = sd.value;

                const ed = document.getElementById('end-date');
                if (ed && ed.value) replacements['{end_date}'] = ed.value;

                const cod = document.getElementById('check-out-date');
                if (cod && cod.value) replacements['{check_out_date}'] = cod.value;

                const aed = document.getElementById('agreement-end-date');
                if (aed && aed.value) replacements['{end_agreement_date}'] = aed.value;

                // 区分状态：提取房源、租客与业主资料
                const statusSelect = document.getElementById('lease-status');
                const isRenew = statusSelect && statusSelect.value !== 'New';

                if (isRenew) {
                    const leaseIdSelect = document.getElementById('lease_id');
                    if (leaseIdSelect && leaseIdSelect.value && leaseIdSelect.selectedIndex > 0) {
                        const opt = leaseIdSelect.options[leaseIdSelect.selectedIndex];
                        
                        if (opt.dataset.propertyName) replacements['{property_name}'] = opt.dataset.propertyName;
                        if (opt.dataset.propertyAddress) replacements['{property_address}'] = opt.dataset.propertyAddress;
                        if (opt.dataset.ownerName) replacements['{owner_name}'] = opt.dataset.ownerName;
                        if (opt.dataset.ownerIc) replacements['{owner_ic}'] = opt.dataset.ownerIc;
                        if (opt.dataset.ownerId) replacements['{owner_id}'] = opt.dataset.ownerId;
                        if (opt.dataset.propertyType) replacements['{property_type}'] = opt.dataset.propertyType.toUpperCase();

                        const match = opt.text.match(/^\s*(.+?)\s*\((.+?)\)/);
                        if (match) {
                            replacements['{tenant_name}'] = match[1].trim();
                            replacements['{tenant_ic}'] = match[2].trim();
                        }
                    }
                } else {
                    const tenantSelect = document.getElementById('tenant_id');
                    if (tenantSelect && tenantSelect.value && tenantSelect.selectedIndex > 0) {
                        const fullText = tenantSelect.options[tenantSelect.selectedIndex].text;
                        const match = fullText.match(/(.+?)\s*\((.+?)\)/);
                        replacements['{tenant_name}'] = match ? match[1].trim() : fullText;
                        replacements['{tenant_ic}'] = match ? match[2].trim() : '';
                    }

                    const leaseSelectionEl = document.getElementById('lease_selection');
                    if (leaseSelectionEl) {
                        const leaseType = leaseSelectionEl.value;
                        const activeSelect = document.getElementById(`${leaseType}_select_input`);

                        if (activeSelect && activeSelect.value && activeSelect.selectedIndex > 0) {
                            const opt = activeSelect.options[activeSelect.selectedIndex];
                            
                            replacements['{property_name}'] = opt.text.trim();
                            replacements['{property_address}'] = opt.getAttribute('data-address') || '';
                            replacements['{owner_name}'] = opt.getAttribute('data-owner') || '';
                            
                            // 💡 修复点：直接获取刚刚在 HTML 里修好的 data-owner-ic！
                            replacements['{owner_ic}'] = opt.getAttribute('data-owner-ic') || ''; 
                        }
                    }
                }

                // 金额补全 0.00
                const amounts = ['{utilities_deposit}', '{security_deposit}', '{rent_price}'];
                amounts.forEach(key => {
                    if (!replacements[key]) {
                        replacements[key] = '0.00';
                    } else {
                        const normalized = String(replacements[key]).trim().replace(/[^0-9.\-]/g, '');
                        const parsed = parseFloat(normalized);
                        replacements[key] = Number.isNaN(parsed) ? String(replacements[key]) : String(parsed);
                    }
                });

                // 空白内容统一变为 N/A
                const strings = ['{start_date}', '{end_date}', '{check_out_date}', '{end_agreement_date}', '{owner_ic}', '{property_type}', '{owner_name}'];
                strings.forEach(key => {
                    if (!replacements[key] || replacements[key].trim() === '') replacements[key] = 'N/A';
                });

                // 执行替换并添加高亮
                Object.keys(replacements).forEach(placeholder => {
                    const val = replacements[placeholder];
                    const regex = new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
                    content = content.replace(regex, `<span class="text-inherit font-semibold text-indigo-600">${val}</span>`);
                });

                return { content, title };
            }

            // 按钮点击事件（触发弹窗）
            previewBtn.addEventListener('click', function() {
                try {
                    const result = generatePreviewContent();
                    if (result) {
                        const modal = document.getElementById('preview-modal');
                        const modalContent = document.getElementById('modal-content');
                        const modalTitle = document.getElementById('modal-title');
                        
                        if (modalTitle) modalTitle.innerText = "Preview: " + result.title;
                        if (modalContent) modalContent.innerHTML = result.content;
                        if (modal) modal.classList.remove('hidden');
                        document.body.style.overflow = 'hidden'; // 禁止背景滚动
                        
                        // 兼容 AlpineJS 组件
                        window.dispatchEvent(new CustomEvent('open-preview-modal'));
                    }
                } catch (error) {
                    console.error("🚨 Preview Error: ", error);
                    alert("Something went wrong while generating the preview. Check console for details.");
                }
            });
        });

        function filterTemplates() {
            let ownerId = null;

            const leaseSelect = document.getElementById('lease_id');
            if (leaseSelect && leaseSelect.value !== "") {
                const selectedLease = leaseSelect.options[leaseSelect.selectedIndex];
                ownerId = selectedLease.getAttribute('data-owner-id');
            }

            if (!ownerId) {
                const typeEl = document.getElementById('lease_selection');
                if (typeEl && typeEl.value) {
                    const activeSelect = document.getElementById(typeEl.value + '_select_input');
                    if (activeSelect && activeSelect.selectedIndex > 0) {
                        const selectedOption = activeSelect.options[activeSelect.selectedIndex];
                        ownerId = selectedOption.getAttribute('data-owner-id');
                    }
                }
            }

            if (!ownerId) return;

            const agreementSelect = document.getElementById('agreement_id');
            if (!agreementSelect) return;

            Array.from(agreementSelect.options).forEach(option => {
                if (option.value === "") return;
                const templateUserId = option.getAttribute('data-agreement-user-id');
                if (ownerId && String(templateUserId) === String(ownerId)) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });

            if (agreementSelect.selectedIndex > 0 && agreementSelect.options[agreementSelect.selectedIndex].style.display === 'none') {
                agreementSelect.value = "";
            }
        }

        ['property_select_input', 'unit_select_input', 'room_select_input', 'lease_id'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', filterTemplates);
        });
    </script>
</x-app-layout>