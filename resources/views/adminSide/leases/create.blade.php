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
                                        //'End Agreement' => 'End Agreement'
                                    ]"
                                    ::value="old('status', request('status', 'New'))"
                                    @change="toggleLeaseSelect()"
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
                                    <x-form.input-label value="Select Existing Lease" :required="true" class="mb-1" />
                                    <x-form.input-select 
                                        name="lease_id" 
                                        id="lease_id"
                                        value="{{ old('lease_id') }}"
                                        :options="$leases"
                                        value-field="id"
                                        label-field="computed_label"
                                        class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                        @change="window.handleLeaseChange($event)"
                                    />
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
                                    @change="toggleLeaseInput()"
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
                                        @change="filterTemplates()"
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
                                        labelField="display_label"
                                        :value="old('unit_id')"
                                        @change="filterTemplates()"
                                    />
                                    <x-form.input-error :messages="$errors->get('unit_id')" class="mt-1" />
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
                                        @change="filterTemplates()"
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
                                        <span class="text-xs font-semibold text-gray-500 tracking-wider">CHARGE ITEM #1</span>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <x-form.input-label value="Charge Type" class="mb-1 text-xs" info="You may hide the charge type if not using it in 'Setting (Lease Settings)'"/>
                                            <select name="charges[0][fee_type_id]" class="block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                                <option value="">-- Select Fee Type --</option>
                                                @foreach($rentFeeTypes as $feeType)
                                                    <option value="{{ $feeType->id }}" data-type="rent">{{ $feeType->name }}</option>
                                                @endforeach

                                                @foreach($serviceFeeTypes as $serviceType)
                                                    <option value="{{ $serviceType->id }}">{{ $serviceType->name }} (Service)</option>
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
                                            <x-form.text-input type="text" name="charges[0][amount]" placeholder="0.00" class="w-full text-sm" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <x-form.input-error :messages="$errors->get('charges')" class="mt-1" />
                        </div>

                        <div id="bring_forward_notice" class="hidden col-span-3 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <p class="text-sm text-blue-800">
                                <span class="font-semibold">Previous Deposits & Charges:</span>
                                <span id="bring-forward-items-container" class="inline">
                                    <!-- Dynamically injected items will appear here -->
                                </span>
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
                    }
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
            console.log("toggleLeaseSelect function executed successfully.");

            const statusSelect = document.getElementById('lease-status');
            if (!statusSelect) {
                console.log("Element with ID 'lease-status' not found.");
                return;
            }

            const newStatus = statusSelect.value || 'New';
            console.log("Current selected status:", newStatus);

            const sections = {
                'lease_select_container': ['Renew', 'Check Out', 'End Agreement'].includes(newStatus),
                'property_select_type': newStatus === 'New',
                'tenant_field': newStatus === 'New',
                'date_section': ['New', 'Renew'].includes(newStatus),
                'bring_forward_notice': newStatus === 'Renew',
                'check_out_section': newStatus === 'Check Out',
                'agreement_end_section': newStatus === 'End Agreement',
                'agreement_template_section': ['New', 'Renew'].includes(newStatus)
            };

            Object.keys(sections).forEach(id => {
                const el = document.getElementById(id);
                if (!el) {
                    console.log(`Section element missing from DOM: ${id}`);
                    return;
                }
                const isVisible = sections[id];
                el.classList.toggle('hidden', !isVisible);
                el.querySelectorAll('input, select, textarea').forEach(input => {
                    input.disabled = !isVisible;
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            console.log("DOM fully loaded. Triggering lease handlers.");
            if (typeof toggleLeaseInput === 'function') {
                toggleLeaseInput();
            }
            
            // 🌟 Ensure status toggle runs immediately, and once more after a tiny delay 
            // to catch any dynamic select components initializing their values
            toggleLeaseSelect();
            setTimeout(() => {
                toggleLeaseSelect();
            }, 50);
        });

        // ==========================================
        // 🌟 4. 暴力且絕對有效的 Scroll 解鎖器
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

                // 🌟 新增：全面支援所有押金與管理費變數
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
                    '{rent_price}': '0.00',
                    '{security_deposit}': '0.00',
                    '{utilities_deposit}': '0.00',
                    '{combined_deposit}': '0.00',
                    '{total_deposit}': '0.00',
                    '{management_fee}': '0.00'
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

                // 🌟 核心計算邏輯：自動分類並加總所有費用
                let securitySum = 0;
                let utilitiesSum = 0;
                let combinedSum = 0;
                let totalDepositSum = 0;
                let managementSum = 0;

                document.querySelectorAll('.charge-row').forEach(row => {
                    const select = row.querySelector('select');
                    const amountInput = row.querySelector('input[name$="[amount]"]');
                    
                    if (select && amountInput && amountInput.value) {
                        const opt = select.options[select.selectedIndex];
                        if(opt) {
                            const text = opt.text.toLowerCase(); 
                            const amount = parseFloat(amountInput.value || 0);

                            // 如果選項包含 (deposit)
                            if (text.includes('(deposit)')) {
                                totalDepositSum += amount;
                                
                                if (text.includes('security and utilities')) {
                                    combinedSum += amount;
                                } else if (text.includes('security')) {
                                    securitySum += amount;
                                } else if (text.includes('utilities')) {
                                    utilitiesSum += amount;
                                }
                            }

                            // 如果選項包含 (management)
                            if (text.includes('(management)')) {
                                managementSum += amount;
                            }
                        }
                    }
                });

                // 將計算結果寫入 replacements
                replacements['{security_deposit}'] = securitySum.toFixed(2);
                replacements['{utilities_deposit}'] = utilitiesSum.toFixed(2);
                replacements['{combined_deposit}'] = combinedSum.toFixed(2);
                replacements['{total_deposit}'] = totalDepositSum.toFixed(2);
                replacements['{management_fee}'] = managementSum.toFixed(2);

                // --- 以下為基本屬性與關聯替換保持原樣 ---
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
                                replacements['{owner_id}'] = targetData.owner_id || 'N/A';
                            }
                        }
                    }
                }

                Object.keys(replacements).forEach(placeholder => {
                    const val = replacements[placeholder] && replacements[placeholder].trim() !== '' 
                                ? replacements[placeholder] 
                                : 'N/A';
                    
                    const coreName = placeholder.replace(/[{}]/g, '').trim();
                    const safeVal = `<strong>${val}</strong>`;

                    const dataVarRegex = new RegExp(`<[^>]+data-variable=["']${coreName}["'][^>]*>[\\s\\S]*?<\\/\\w+>`, 'gi');
                    content = content.replace(dataVarRegex, safeVal);

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
                            modalContent.innerHTML = `<div class="grapes-preview-box">${resetStyles}${result.content}</div>`;
                        }
                        
                        if (modal) {
                            modal.classList.remove('hidden');
                            modal.style.display = 'block'; 
                        }

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

        const currentUserId = @js(function_exists('get_effective_user') ? get_effective_user()->id : null);
        console.log("Current User ID for template filtering:", currentUserId);
        function filterTemplates(overrideOwnerId = null) {
            console.log("filterTemplates function executed.");
            let ownerId = overrideOwnerId; // Use passed-in ownerId if available

            // 1. If no override ownerId was passed, check if an existing lease is selected
            if (!ownerId) {
                const leaseSelect = document.getElementById('lease_id');
                if (leaseSelect && leaseSelect.value !== "") {
                    const selectedLease = leaseSelect.options[leaseSelect.selectedIndex];
                    if (selectedLease) {
                        ownerId = selectedLease.getAttribute('data-owner-id');
                    }
                }
            }

            // 2. If still no ownerId, check property type selection
            if (!ownerId) {
                const typeEl = document.getElementById('lease_selection');
                if (typeEl && typeEl.value) {
                    const leaseType = typeEl.value; // 'property', 'unit', or 'room'
                    const activeSelectInput = document.getElementById(leaseType + '_select_input');
                    
                    if (activeSelectInput && activeSelectInput.value) {
                        const targetId = activeSelectInput.value;

                        // Look up owner_id directly from the preloaded JS data objects
                        if (leaseType === 'property' && allProperties[targetId]) {
                            ownerId = allProperties[targetId].owner_id;
                        } else if (leaseType === 'unit' && allUnits[targetId]) {
                            ownerId = allUnits[targetId].owner_id;
                        } else if (leaseType === 'room' && allRooms[targetId]) {
                            ownerId = allRooms[targetId].owner_id;
                        }
                    }
                }
            }

            const agreementSelect = document.getElementById('document_id');
            if (!agreementSelect) return;

            const debugTableData = [];

            // Filter template dropdown options based on the ownerId
            Array.from(agreementSelect.options).forEach(option => {
                if (option.value === "") return;
                const templateUserId = option.getAttribute('data-agreement-user-id');
                const documentId = option.value;
                const documentName = option.text.trim();
                
                const matchesOwner = ownerId && String(templateUserId) === String(ownerId);
                const matchesAuthUser = currentUserId && String(templateUserId) === String(currentUserId);

                let displayStatus = 'hidden';
                if (matchesOwner || matchesAuthUser) {
                    option.style.display = 'block';
                    displayStatus = 'visible';
                } else {
                    option.style.display = 'none';
                }

                debugTableData.push({
                    "Owner ID (Context)": ownerId || 'None',
                    "Document ID": documentId,
                    "Document Name": documentName,
                    "Template User ID": templateUserId,
                    "Status": displayStatus
                });
            });

            console.log("Template filtering completed. Owner ID:", ownerId, "Current User ID:", currentUserId);
            console.table(debugTableData);

            if (agreementSelect.selectedIndex > 0 && agreementSelect.options[agreementSelect.selectedIndex].style.display === 'none') {
                agreementSelect.value = "";
            }
        }

        function handleLeaseChange(eventOrId) {
            let leaseId = null;

            // 1. Safely extract value whether it's an Alpine event, custom detail object, or direct string/number ID
            if (eventOrId !== undefined && eventOrId !== null) {
                if (typeof eventOrId === 'object') {
                    leaseId = eventOrId.detail?.value || eventOrId.target?.value || eventOrId.value || null;
                } else {
                    leaseId = eventOrId;
                }
            }

            // 2. Fallback: Read straight from the DOM element if still not found
            if (!leaseId) {
                const leaseSelectInput = document.getElementById('lease_id');
                if (leaseSelectInput) {
                    leaseId = leaseSelectInput.value;
                }
            }

            if (!leaseId || !allLeases || !allLeases[leaseId]) {
                return; // Exit silently if no valid lease is selected yet
            }

            const selectedLease = allLeases[leaseId];

            // 🌟 Console log the full lease details object
            console.log("📄 Selected Lease Details:", selectedLease);

            // 3. Handle Start Date calculation
            if (selectedLease.end_date) {
                const endDate = new Date(selectedLease.end_date);
                endDate.setDate(endDate.getDate() + 1);
                const nextDayStr = endDate.toISOString().split('T')[0];

                const startInput = document.querySelector('#start-date');
                if (startInput) {
                    startInput.value = nextDayStr;
                    if (startInput._flatpickr) {
                        startInput._flatpickr.setDate(nextDayStr, true);
                    }
                }
            }

            // 4. Extract owner ID from the backend-computed property
            const ownerId = selectedLease.owner_id || null;

            const leaseSelect = document.getElementById('lease_id');
            if (leaseSelect && ownerId) {
                const currentOption = leaseSelect.options ? leaseSelect.options[leaseSelect.selectedIndex] : null;
                if (currentOption) {
                    currentOption.setAttribute('data-owner-id', ownerId);
                }
            }

            // 5. Auto-select the document/template BEFORE filtering so it doesn't get wiped or hidden
            const docId = selectedLease.document_id || selectedLease.agreement_id || selectedLease.template_id;
            if (docId) {
                const documentSelect = document.getElementById('document_id');
                if (documentSelect) {
                    documentSelect.value = docId;

                    // Use the allTemplates JSON object directly to get the clean title
                    let selectedDocumentName = 'N/A';
                    if (allTemplates && allTemplates[String(docId)]) {
                        selectedDocumentName = allTemplates[String(docId)].title;
                    } else {
                        // Fallback to searching the option text if needed
                        const targetOption = Array.from(documentSelect.options).find(opt => String(opt.value) === String(docId));
                        if (targetOption) {
                            selectedDocumentName = targetOption.text.trim();
                        }
                    }
                    
                    console.log("Autofilled Document ID:", docId);
                    console.log("Autofilled Document Name:", selectedDocumentName);
                }
            }

            // 🌟 7. Load and callback the selected lease's charges / preview data
            const noticeBox = document.getElementById('bring_forward_notice');
            const itemsContainer = document.getElementById('bring-forward-items-container'); 

            const leaseData = leasePreviewData[leaseId];

            if (leaseData && itemsContainer && noticeBox) {
                itemsContainer.innerHTML = ''; // Clear out old list

                // 1. Get ONLY the CURRENT lease's charges (from leaseData directly)
                const currentCharges = leaseData.charges || [];

                const currentChargeElements = currentCharges.length > 0 
                    ? currentCharges.map(charge => {
                        const name = charge.feeType?.name || charge.fee_type?.name || charge.name || 'Charge';
                        const amount = parseFloat(charge.amount / 100 || 0);
                        return `<div>• RM ${amount.toFixed(2)} (${name})</div>`;
                    }).join('')
                    : '<div class="text-gray-500 italic">No charges found in this lease.</div>';

                // 2. Recursively gather ALL charges across the entire lineage (for total deposit sum)
                function collectFullHistory(lease, accumulator = []) {
                    if (!lease) return accumulator;
                    if (lease.charges) {
                        accumulator.push(...lease.charges);
                    }
                    const parentLease = lease.parent_lease || lease.parentLease;
                    return collectFullHistory(parentLease, accumulator);
                }

                const allHistoryCharges = collectFullHistory(leaseData);

                // 3. Filter strictly for deposit categories across ALL history
                const depositCharges = allHistoryCharges.filter(charge => {
                    const category = charge.feeType?.category || charge.fee_type?.category;
                    return category === 'deposit';
                });

                let totalDeposit = 0;
                depositCharges.forEach(charge => {
                    totalDeposit += parseFloat(charge.amount / 100 || 0);
                });

                // Show the notice box if either current charges or deposits exist
                if (currentCharges.length > 0 || depositCharges.length > 0) {
                    noticeBox.classList.remove('hidden'); 

                    // 4. Render structured HTML
                    itemsContainer.innerHTML = `
                        <div class="mb-3">
                            <span class="font-semibold block text-blue-900 mb-1">Lease Charges:</span>
                            <div class="text-gray-700 space-y-1">${currentChargeElements}</div>
                        </div>
                        <div class="border-t border-blue-200 pt-2">
                            <span class="font-semibold text-blue-900">Total Deposit Collected:</span> 
                            <span class="font-bold text-gray-900">RM ${totalDeposit.toFixed(2)}</span>
                        </div>
                    `;
                } else {
                    noticeBox.classList.add('hidden'); 
                }
            }

            // 6. Run filterTemplates
            if (typeof filterTemplates === 'function') {
                filterTemplates(ownerId);
            }
        }
    </script>
@endpush
</x-app-layout>
