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
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                            <div class="md:col-span-1">
                                <x-form.input-label value="Status" :required="true" class="mb-1" />
                                <x-form.input-select
                                    name="status"
                                    id="lease-status"
                                    :options="[
                                        'New' => 'New',
                                        'Renew' => 'Renew',
                                        'Check Out' => 'Check Out',
                                        'End Agreement' => 'End Agreement'
                                    ]"
                                    :value="request('status', 'New')"
                                    onchange="toggleLeaseSelect()"
                                />
                                <x-form.input-error :messages="$errors->get('status')" class="mt-1" />
                            </div>

                            <div id="tenant_field" class="md:col-span-3">
                                <x-form.input-label value="Select Tenant" :required="true" class="mb-1" />
                                <x-form.input-select
                                    name="tenant_id"
                                    id="tenant_id"
                                    :options="$tenants"
                                    valueField="id"
                                    labelField="user.name"
                                    :value="old('tenant_id')"
                                />
                                <x-form.input-error :messages="$errors->get('tenant_id')" class="mt-1" />
                            </div>

                            {{-- 2. Select Lease --}}
                            <div id="lease_select_container" class="md:col-span-3 hidden">
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

                        </div>

                        <div id="property_select_type" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- 1. 左邊：選擇租賃類型 --}}
                            <div>
                               <x-form.input-label value="Select Properties Type" :required="true" class="mb-1" />
                                <x-form.input-select
                                    name="lease_selection"
                                    id="lease_selection"
                                    :options="[
                                        'property' => 'Entire Property',
                                        'unit' => 'Specific Unit',
                                        'room' => 'Specific Room'
                                    ]"
                                    :value="old('lease_selection', 'property')"
                                    onchange="toggleLeaseInput()"
                                />
                            </div>

                            {{-- 2. 右邊：動態切換的 Select Fields --}}
                            <div>
                                <div id="property_field" class="lease-field">
                                    <x-form.input-label value="Select Property" :required="true" class="mb-1" />
                                    <x-form.input-select
                                        name="property_id"
                                        id="property_select_input"
                                        :options="$properties"
                                        valueField="id"
                                        labelField="name"
                                        :value="old('property_id')"
                                    />
                                    <x-form.input-error :messages="$errors->get('property_id')" class="mt-1" />
                                </div>

                                <div id="unit_field" class="lease-field hidden">
                                    <x-form.input-label value="Select Unit" :required="true" class="mb-1" />
                                    <x-form.input-select
                                        name="unit_id"
                                        id="unit_select_input"
                                        :options="$units"
                                        valueField="id"
                                        labelField="unit_no"
                                        :value="old('unit_id')"
                                    />
                                    <x-form.input-error :messages="$errors->get('unit_id')"  class="mt-1" />
                                </div>

                                <div id="room_field" class="lease-field hidden">
                                    <x-form.input-label value="Select Room" :required="true" class="mb-1" />
                                    <x-form.input-select
                                        name="room_id"
                                        id="room_select_input"
                                        :options="$rooms"
                                        valueField="id"
                                        labelField="room_no"
                                        :value="old('room_id')"
                                    />
                                    <x-form.input-error :messages="$errors->get('room_id')" class="mt-1" />
                                </div>
                            </div>
                        </div>

                        <div id="date_section" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-form.input-label value="Start Date" :required="true" class="mb-1" />
                                <x-form.date-input id="start-date" name="start_date" :value="old('start_date')" />
                                <x-form.input-error :messages="$errors->get('start_date')" class="mt-1" />
                            </div>

                            <div>
                                <x-form.input-label value="End Date" :required="true" class="mb-1" />
                                <x-form.date-input id="end-date" name="end_date" :value="old('end_date')" />
                                <x-form.input-error :messages="$errors->get('end_date')" class="mt-1" />
                            </div>
                        </div>

                        <div id="check_out_section" class="mt-4 hidden">
                            <x-form.input-label value="Check Out Date" class="mb-1" />
                            <x-form.date-input id="check-out-date" name="checked_out_at" :value="old('checked_out_at')" />
                            <x-form.input-error :messages="$errors->get('checked_out_at')" class="mt-1" />
                        </div>

                        <div id="agreement_end_section" class="mt-4 hidden">
                            <x-form.input-label value="Agreement Ended Date" class="mb-1" />
                            <x-form.date-input id="agreement-end-date" name="agreement_ended_at" :value="old('agreement_ended_at')" />
                            <x-form.input-error :messages="$errors->get('agreement_ended_at')" class="mt-1" />
                        </div>

                        <div id="agreement_template_section" class="mt-4">
                            <div class="flex justify-between items-center mb-1">
                                <x-form.input-label value="Agreements Template" :required="true" />
                                <button type="button" id="preview-btn" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors uppercase tracking-wider">
                                    Preview Template
                                </button>
                            </div>
                            
                            <select id="document_id" name="document_id"
                                class="block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                <option value="">-- Select Template --</option>
                                @foreach($templates as $template)
                                    <option value="{{ $template->id }}" data-agreement-user-id="{{$template->user->id}}" {{ old('document_id') == $template->id ? 'selected' : '' }}>
                                        {{ $template->title }} (v{{ $template->version }}) - {{ $template->user->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-form.input-error :messages="$errors->get('document_id')" class="mt-1" />
                        </div>

                        <x-preview-agreement-modal />

                        <div class="space-y-4 border-t border-gray-200 pt-6">
                            <div class="flex justify-between items-center">
                                <x-form.input-label value="Lease Charges & Deposits" :required="true" />
                                <button type="button" onclick="addChargeRow()" class="px-3 py-1.5 bg-indigo-50 text-indigo-600 text-xs font-semibold rounded-lg hover:bg-indigo-100 transition-colors">
                                    + Add Charge Item
                                </button>
                            </div>

                            <div id="charges-container" class="space-y-4">
                                <div class="charge-row rounded-lg border border-gray-200 bg-gray-50 p-4">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-xs font-semibold text-gray-500 tracking-wider">
                                            <span style="color: red;">* </span>CHARGE ITEM #1
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <x-form.input-label value="Charge Type" class="mb-1 text-xs" />
                                            <select name="charges[0][fee_type_id]" class="block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                                                <option value="">-- Select Fee Type --</option>
                                                @foreach($rentFeeTypes as $feeType)
                                                    @php
                                                        $period = str_contains(strtolower($feeType->name), 'daily') ? 'daily' : 
                                                                (str_contains(strtolower($feeType->name), 'weekly') ? 'weekly' : 
                                                                (str_contains(strtolower($feeType->name), 'monthly') ? 'monthly' : 
                                                                (str_contains(strtolower($feeType->name), 'yearly') ? 'yearly' : 'other')));
                                                    @endphp
                                                    <option value="{{ $feeType->id }}" data-type="rent" data-period="{{ $period }}">{{ $feeType->name }}</option>
                                                @endforeach

                                                @foreach($depositFeeTypes as $depositType)
                                                    <option value="{{ $depositType->id }}">{{ $depositType->name }} (Deposit)</option>
                                                @endforeach

                                                @foreach($managementFeeTypes as $managementType)
                                                    <option value="{{ $managementType->id }}">{{ $managementType->name }} (Management)</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <x-form.input-label value="Amount (RM)" class="mb-1 text-xs" />
                                            <x-form.text-input type="text" name="charges[0][amount]" placeholder="0.00" class="w-full text-sm" required />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <x-form.input-error :messages="$errors->get('charges')" class="mt-1" />
                        </div>

                        <div id="bring_forward_notice" class="hidden col-span-3 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <p class="text-sm text-blue-800">
                                <span class="font-semibold">Previous Deposits:</span>
                                <span id="bf-security">RM 0.00</span> (Security) |
                                <span id="bf-utilities">RM 0.00</span> (Utilities)
                                <span class="block mt-1 text-xs text-blue-600 italic">These amounts are brought forward from your previous lease.</span>
                            </p>
                        </div>

                    </div>

                    <div class="px-8 py-5 bg-white border-t border-gray-100 flex items-center justify-end">
                        <x-form.primary-button type="submit" loading="loading" class="px-5 py-2.5">
                            Create Lease
                        </x-form.primary-button>
                    </div>
                </x-form.form>
            </div>
        </div>
    </div>

@push('scripts')
    @php
        $jsRentFees = $rentFeeTypes->map(fn($f) => ['id' => $f->id, 'name' => $f->name])->values();
        $jsDepositFees = $depositFeeTypes->map(fn($f) => ['id' => $f->id, 'name' => $f->name])->values();
        
        $jsTemplates = $templates->map(fn($t) => [
            'id' => (string) $t->id, 
            'title' => $t->title, 
            'content' => $t->html_template
        ])->keyBy('id');

        // 🌟 修正點 1：加上 ?-> 確保多層關聯絕對不會報錯，並補上 owner_id
        $jsTenants = $tenants->map(fn($t) => [
            'id' => (string) $t->id,
            'name' => $t->user?->name ?? 'N/A',
            'ic_number' => $t->ic_number ?? 'N/A',
        ])->keyBy('id');

        $jsProperties = $properties->map(fn($p) => [
            'id' => (string) $p->id,
            'name' => $p->name ?? 'N/A',
            'full_address' => $p->full_address ?? 'N/A',
            'owner_name' => $p->owner?->name ?? 'N/A',
            'owner_ic' => $p->owner?->owner?->ic_number ?? ($p->owner?->ic_number ?? 'N/A'),
            'owner_id' => (string) ($p->owner?->id ?? 'N/A'),
        ])->keyBy('id');

        $jsUnits = $units->map(fn($u) => [
            'id' => (string) $u->id,
            'name' => $u->unit_no ?? 'N/A',
            'full_address' => $u->full_address ?? 'N/A',
            'owner_name' => $u->owner?->name ?? 'N/A',
            'owner_ic' => $u->owner?->owner?->ic_number ?? ($u->owner?->ic_number ?? 'N/A'),
            'owner_id' => (string) ($u->owner?->id ?? 'N/A'),
        ])->keyBy('id');

        $jsRooms = $rooms->map(fn($r) => [
            'id' => (string) $r->id,
            'name' => $r->room_no ?? 'N/A',
            'full_address' => $r->full_address ?? 'N/A',
            'owner_name' => $r->unit?->owner?->name ?? 'N/A',
            'owner_ic' => $r->unit?->owner?->owner?->ic_number ?? ($r->unit?->owner?->ic_number ?? 'N/A'),
            'owner_id' => (string) ($r->unit?->owner?->id ?? 'N/A'),
        ])->keyBy('id');
    @endphp

    <script type="application/json" id="templates-json">
        {!! json_encode($jsTemplates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
    </script>

    <div id="server-data" class="hidden" style="display: none;"
        data-create-url="{{ route('admin.leases.create') }}"
        data-rent-fees="{{ json_encode($jsRentFees) }}"
        data-deposit-fees="{{ json_encode($jsDepositFees) }}"
        data-all-leases="{{ json_encode($leases->keyBy('id')) }}"
        data-preview-data="{{ json_encode($leasePreviewData->keyBy('id')) }}"
        data-tenants="{{ json_encode($jsTenants) }}"
        data-properties="{{ json_encode($jsProperties) }}"
        data-units="{{ json_encode($jsUnits) }}"
        data-rooms="{{ json_encode($jsRooms) }}">
    </div>

    <script>
        // ==========================================
        // 0. 從 HTML 讀取安全的 Server 資料
        // ==========================================
        const serverDataEl = document.getElementById('server-data');
        const createUrl = serverDataEl.getAttribute('data-create-url');
        const rentFeeTypes = JSON.parse(serverDataEl.getAttribute('data-rent-fees') || '[]');
        const depositFeeTypes = JSON.parse(serverDataEl.getAttribute('data-deposit-fees') || '[]');
        const allLeases = JSON.parse(serverDataEl.getAttribute('data-all-leases') || '{}');
        const leasePreviewData = JSON.parse(serverDataEl.getAttribute('data-preview-data') || '{}');
        
        const allTenants = JSON.parse(serverDataEl.getAttribute('data-tenants') || '{}');
        const allProperties = JSON.parse(serverDataEl.getAttribute('data-properties') || '{}');
        const allUnits = JSON.parse(serverDataEl.getAttribute('data-units') || '{}');
        const allRooms = JSON.parse(serverDataEl.getAttribute('data-rooms') || '{}');

        const templatesJsonEl = document.getElementById('templates-json');
        const allTemplates = templatesJsonEl ? JSON.parse(templatesJsonEl.textContent) : {};

        // ==========================================
        // 1. Dynamic Charge Row Management
        // ==========================================
        let chargeIndex = 1;

        function addChargeRow() {
            const container = document.getElementById('charges-container');
            const firstSelect = container.querySelector('select');
            const optionsHtml = firstSelect ? firstSelect.innerHTML : '';

            const newRow = document.createElement('div');
            newRow.className = 'charge-row rounded-lg border border-gray-200 bg-gray-50 p-4';
            newRow.innerHTML = `
                <div class="flex justify-between items-center mb-2">
                    <span class="text-xs font-semibold text-gray-500 tracking-wider">
                        CHARGE ITEM #${container.querySelectorAll('.charge-row').length + 1}
                    </span>
                    <button type="button" class="text-sm text-red-600 hover:text-red-800" onclick="removeChargeRow(this)">
                        Remove
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block uppercase font-medium text-sm text-gray-700 mb-1 text-xs">Charge Type</label>
                        <select name="charges[${chargeIndex}][fee_type_id]" class="block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                            ${optionsHtml}
                        </select>
                    </div>
                    <div>
                        <label class="block uppercase font-medium text-sm text-gray-700 mb-1 text-xs">Amount (RM)</label>
                        <input type="text" name="charges[${chargeIndex}][amount]" placeholder="0.00" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full text-sm" required>
                    </div>
                </div>
            `;
            container.appendChild(newRow);
            chargeIndex++;
        }

        function removeChargeRow(button) {
            const row = button.closest('.charge-row');
            const container = document.getElementById('charges-container');
            if (container.querySelectorAll('.charge-row').length > 1) {
                row.remove();
            } else {
                alert('You must have at least one charge item.');
            }
        }

        // ==========================================
        // 2. Date & Fee Type Calculations
        // ==========================================
        function getFeeTypePeriod(feeTypeName) {
            const name = feeTypeName.toLowerCase();
            if (name.includes('daily')) return 'daily';
            if (name.includes('weekly')) return 'weekly';
            if (name.includes('monthly')) return 'monthly';
            if (name.includes('yearly')) return 'yearly';
            return null;
        }

        function calculateAvailableFeeTypes() {
            const startInput = document.getElementById('start-date');
            const endInput = document.getElementById('end-date');

            if (!startInput || !endInput) return;

            const startVal = startInput.value;
            const endVal = endInput.value;

            if (startVal) {
                endInput.min = startVal;
            }

            if (!startVal || !endVal) return;

            const start = new Date(startVal);
            const end = new Date(endVal);

            if (end < start) {
                updateFeeTypeOptions(['daily']);
                return;
            }

            const diffTime = end - start;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            let months = (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth());
            if (end.getDate() < start.getDate()) months--;

            let years = end.getFullYear() - start.getFullYear();
            if (end.getMonth() < start.getMonth() || (end.getMonth() === start.getMonth() && end.getDate() < start.getDate())) {
                years--;
            }

            let allowed = ['daily'];

            if (diffDays >= 7 && months < 1) {
                allowed.push('weekly');
            } else if (months >= 1 && years < 1) {
                allowed.push('weekly', 'monthly');
            } else if (years >= 1) {
                allowed.push('weekly', 'monthly', 'yearly');
            }

            updateFeeTypeOptions(allowed);
        }

        function updateFeeTypeOptions(allowedPeriods) {
            const selects = document.querySelectorAll('select[name$="[fee_type_id]"]');

            selects.forEach(select => {
                const options = Array.from(select.options);
                let selectedStillValid = false;

                options.forEach(option => {
                    if (!option.value) return;

                    const isRentType = option.getAttribute('data-type') === 'rent';

                    if (isRentType) {
                        const period = option.getAttribute('data-period');
                        const isAllowed = allowedPeriods.includes(period);

                        option.hidden = !isAllowed;
                        option.disabled = !isAllowed;

                        if (option.selected && isAllowed) {
                            selectedStillValid = true;
                        }

                        if (option.selected && !isAllowed) {
                            option.selected = false;
                        }
                    } else {
                        option.hidden = false;
                        option.disabled = false;
                        if (option.selected) {
                            selectedStillValid = true;
                        }
                    }
                });

                if (!selectedStillValid && select.value !== "") {
                    select.value = "";
                }
            });
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
                        calculateAvailableFeeTypes();
                    }
                });

                endPicker.config.onChange.push(function() {
                    calculateAvailableFeeTypes();
                });
            }
        });

        // ==========================================
        // 3. Base Form Interactions & Preview Handler
        // ==========================================
        document.addEventListener('DOMContentLoaded', function() {
            calculateAvailableFeeTypes();

            const leaseSelect = document.getElementById('lease_id');
            if (leaseSelect) {
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

                    document.getElementById('term_type').value = lease.term_type || 'Monthly';

                    const firstAmountInput = document.querySelector('input[name="charges[0][amount]"]');
                    if (firstAmountInput) {
                        firstAmountInput.value = lease.rent_price || '';
                    }

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
                        }

                        calculateAvailableFeeTypes();
                    }

                    const leaseData = leasePreviewData[leaseId];
                    const bfSec = document.getElementById('bf-security');
                    const bfUtil = document.getElementById('bf-utilities');

                    if (bfSec && leaseData) bfSec.innerText = 'RM ' + parseFloat(leaseData.cumulative_security).toFixed(2);
                    if (bfUtil && leaseData) bfUtil.innerText = 'RM ' + parseFloat(leaseData.cumulative_utilities).toFixed(2);
                });
            }
        });

        function toggleLeaseInput() {
            const leaseSelection = document.getElementById('lease_selection');
            if (!leaseSelection) return;

            const selectedType = leaseSelection.value;
            const fields = document.querySelectorAll('.lease-field');

            fields.forEach(field => {
                const select = field.querySelector('select');
                const input = field.querySelector('input');
                const isActive = field.id === `${selectedType}_field`;

                if (isActive) {
                    field.classList.remove('hidden');
                    if (select) select.disabled = false;
                    if (input) input.disabled = false;
                } else {
                    field.classList.add('hidden');
                    if (select) { select.disabled = true; select.value = ''; }
                    if (input) { input.disabled = true; input.value = ''; }
                }
            });

            filterTemplates();
        }

        function toggleLeaseSelect() {
            const statusSelect = document.getElementById('lease-status');
            const newStatus = statusSelect.value || 'New';

            const urlParams = new URLSearchParams(window.location.search);
            if (newStatus !== urlParams.get('status')) {
                window.location.href = `${createUrl}?status=${newStatus}`;
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
                el.querySelectorAll('input, select, textarea').forEach(input => {
                    input.disabled = !isVisible;
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleLeaseInput();
            toggleLeaseSelect();
        });

        // ==========================================
        // 🌟 4. 暴力且絕對有效的 Scroll 解鎖器
        // 只要你在頁面上點擊（例如點擊關閉 X 按鈕或背景），0.15 秒後檢查 Modal，隱藏了就解鎖捲軸！
        // ==========================================
        document.addEventListener('click', function(e) {
            setTimeout(() => {
                const modalEl = document.getElementById('preview-modal');
                if (!modalEl || modalEl.classList.contains('hidden') || getComputedStyle(modalEl).display === 'none') {
                    if (document.body.style.overflow === 'hidden') {
                        document.body.style.overflow = '';
                    }
                }
            }, 150);
        });

        // ESC 鍵關閉的解鎖
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                setTimeout(() => {
                    const modalEl = document.getElementById('preview-modal');
                    if (modalEl) {
                        modalEl.classList.add('hidden');
                        modalEl.style.display = '';
                    }
                    document.body.style.overflow = '';
                }, 100);
            }
        });

        // ==========================================
        // 5. Agreement Preview Template Handler
        // ==========================================
        document.addEventListener('DOMContentLoaded', function() {
            const previewBtn = document.getElementById('preview-btn');
            const agreementSelect = document.getElementById('document_id');

            if (!previewBtn) return;

            function generatePreviewContent() {
                if (!agreementSelect || !agreementSelect.value) {
                    alert("⚠️ Please select a template first.");
                    return null;
                }

                const selectedTemplateId = String(agreementSelect.value);
                const templateData = allTemplates[selectedTemplateId];

                if (!templateData || !templateData.content || templateData.content.trim() === '') {
                    alert("⚠️ The selected template has no content!");
                    return null;
                }

                let content = templateData.content;
                const title = templateData.title;

                // 🌟 修正點 2：補上 {owner_id} 在這裡，保證不漏接！
                const replacements = {
                    '{tenant_name}': 'N/A',
                    '{tenant_ic}': 'N/A',
                    '{owner_name}': 'N/A',
                    '{owner_ic}': 'N/A',
                    '{owner_id}': 'N/A', 
                    '{property_name}': 'N/A',
                    '{property_type}': 'N/A',
                    '{property_address}': 'N/A',
                    '{start_date}': 'N/A',
                    '{end_date}': 'N/A',
                    '{check_out_date}': 'N/A',
                    '{end_agreement_date}': 'N/A',
                    '{rent_price}': '0.00'
                };

                const sd = document.getElementById('start-date');
                if (sd && sd.value) replacements['{start_date}'] = sd.value;

                const ed = document.getElementById('end-date');
                if (ed && ed.value) replacements['{end_date}'] = ed.value;

                const cod = document.getElementById('check-out-date');
                if (cod && cod.value) replacements['{check_out_date}'] = cod.value;

                const aed = document.getElementById('agreement-end-date');
                if (aed && aed.value) replacements['{end_agreement_date}'] = aed.value;

                const firstAmountInput = document.querySelector('input[name="charges[0][amount]"]');
                if (firstAmountInput && firstAmountInput.value) {
                    replacements['{rent_price}'] = firstAmountInput.value;
                }

                const statusSelect = document.getElementById('lease-status');
                const isRenew = statusSelect && statusSelect.value !== 'New';

                if (isRenew) {
                    const leaseIdSelect = document.getElementById('lease_id');
                    if (leaseIdSelect && leaseIdSelect.value) {
                        const leaseId = leaseIdSelect.value;
                        const previewData = leasePreviewData[leaseId];
                        const opt = leaseIdSelect.options[leaseIdSelect.selectedIndex];

                        if (opt) {
                            replacements['{property_name}'] = opt.dataset.propertyName || previewData?.leasable_name || 'N/A';
                            replacements['{property_address}'] = opt.dataset.propertyAddress || previewData?.leasable_address || 'N/A';
                            replacements['{owner_name}'] = opt.dataset.ownerName || previewData?.owner_data?.name || 'N/A';
                            replacements['{owner_ic}'] = opt.dataset.ownerIc || previewData?.owner_data?.ic_number || 'N/A';
                            replacements['{owner_id}'] = opt.dataset.ownerId || previewData?.owner_data?.id || 'N/A';
                            replacements['{property_type}'] = (opt.dataset.propertyType || '').toUpperCase();

                            const match = opt.text.match(/^\s*(.+?)\s*\((.+?)\)/);
                            if (match) {
                                replacements['{tenant_name}'] = match[1].trim();
                                replacements['{tenant_ic}'] = match[2].trim();
                            }
                        }
                    }
                } else {
                    const tenantSelect = document.getElementById('tenant_id');
                    if (tenantSelect && tenantSelect.value && allTenants[tenantSelect.value]) {
                        const t = allTenants[tenantSelect.value];
                        replacements['{tenant_name}'] = t.name || 'N/A';
                        replacements['{tenant_ic}'] = t.ic_number || 'N/A';
                    }

                    const leaseSelectionEl = document.getElementById('lease_selection');
                    if (leaseSelectionEl) {
                        const leaseType = leaseSelectionEl.value;
                        replacements['{property_type}'] = leaseType.toUpperCase();

                        const activeSelect = document.getElementById(`${leaseType}_select_input`);
                        if (activeSelect && activeSelect.value) {
                            const targetId = activeSelect.value;
                            let targetData = null;

                            if (leaseType === 'property') targetData = allProperties[targetId];
                            else if (leaseType === 'unit') targetData = allUnits[targetId];
                            else if (leaseType === 'room') targetData = allRooms[targetId];

                            if (targetData) {
                                replacements['{property_name}'] = targetData.name || 'N/A';
                                replacements['{property_address}'] = targetData.full_address || 'N/A';
                                replacements['{owner_name}'] = targetData.owner_name || 'N/A';
                                replacements['{owner_ic}'] = targetData.owner_ic || 'N/A';
                                replacements['{owner_id}'] = targetData.owner_id || 'N/A'; // 🌟 補上從 JSON 拿 owner_id
                            }
                        }
                    }
                }

                Object.keys(replacements).forEach(placeholder => {
                    const val = replacements[placeholder] && replacements[placeholder].trim() !== '' 
                                ? replacements[placeholder] 
                                : 'N/A';
                    
                    const coreName = placeholder.replace(/[{}]/g, '').trim();
                    
                    // 🌟 修改：我們不用帶有靛藍色的 class，改用普通加粗字體
                    const safeVal = `<strong>${val}</strong>`;

                    // 武器 A：連同 GrapesJS 產生的帶有 id 顏色的外層 <span data-variable="..."> 整個吞掉替換！
                    const dataVarRegex = new RegExp(`<[^>]+data-variable=["']${coreName}["'][^>]*>[\\s\\S]*?<\\/\\w+>`, 'gi');
                    content = content.replace(dataVarRegex, safeVal);

                    // 武器 B：對付純文字格式的
                    const safeCoreName = coreName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    const textRegex = new RegExp(`(?:\\{|&#123;|&lcub;){1,2}(?:[\\s\\u200B\\u200C\\u200D\\uFEFF&nbsp;]|<[^>]*>)*${safeCoreName}(?:[\\s\\u200B\\u200C\\u200D\\uFEFF&nbsp;]|<[^>]*>)*(?:\\}|&#125;|&rcub;){1,2}`, 'gi');
                    content = content.replace(textRegex, safeVal);
                });

                return { content, title };
            }

            previewBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation(); 
                
                try {
                    const result = generatePreviewContent();
                    if (result) {
                        const modal = document.getElementById('preview-modal');
                        const modalContent = document.getElementById('modal-content');
                        const modalTitle = document.getElementById('modal-title');
                        
                       if (modalTitle) modalTitle.innerText = "Preview: " + result.title;
                        
                        if (modalContent) {
                            // 🌟 注入還原 CSS：抵抗 Tailwind 的樣式重置，拯救標題與清單
                            const resetStyles = `
                                <style>
                                    .grapes-preview-box h1 { font-size: 2.2em !important; font-weight: bold !important; margin: 0.67em 0 !important; line-height: 1.2 !important; }
                                    .grapes-preview-box h2 { font-size: 1.6em !important; font-weight: bold !important; margin: 0.83em 0 !important; line-height: 1.3 !important; }
                                    .grapes-preview-box h3 { font-size: 1.2em !important; font-weight: bold !important; margin: 1em 0 !important; }
                                    .grapes-preview-box p { margin: 1em 0 !important; }
                                    .grapes-preview-box ul { list-style-type: disc !important; padding-left: 40px !important; margin: 1em 0 !important; }
                                    .grapes-preview-box ol { list-style-type: decimal !important; padding-left: 40px !important; margin: 1em 0 !important; }
                                    .grapes-preview-box li { display: list-item !important; margin-bottom: 0.5em !important; }
                                    .grapes-preview-box strong, .grapes-preview-box b { font-weight: bold !important; color: #000 !important; }
                                    .grapes-preview-box hr { margin: 1.5em 0 !important; border-top: 1px solid #ccc !important; }
                                </style>
                            `;
                            // 用 grapes-preview-box 把內容包起來
                            modalContent.innerHTML = `<div class="grapes-preview-box">${resetStyles}${result.content}</div>`;
                        }
                        
                        if (modal) {
                            modal.classList.remove('hidden');
                            modal.style.display = 'block'; 
                        }

                        // 鎖死背景捲軸
                        document.body.style.overflow = 'hidden';
                        
                        window.dispatchEvent(new CustomEvent('open-preview-modal', {
                            detail: { title: result.title, content: result.content }
                        }));
                    }
                } catch (error) {
                    console.error("🚨 Preview Error: ", error);
                    alert("Something went wrong while generating the preview: " + error.message);
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

            const agreementSelect = document.getElementById('document_id');
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
    @endpush
</x-app-layout>