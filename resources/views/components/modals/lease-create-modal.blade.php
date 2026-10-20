@props(['tenants', 'leases', 'properties', 'units', 'rooms', 'templates', 'rentFeeTypes', 'serviceFeeTypes', 'depositFeeTypes', 'managementFeeTypes', 'itemFeeTypes', 'leasePreviewData'])

<div x-data="{ 
        open: @js($errors->any()), 
        action: '{{ old('status') === 'Renew' ? 'renew' : (old('status') === 'Check Out' ? 'checkout' : 'create') }}', 
        formStatus: @js(old('status', 'New')), 
        formLeaseId: @js(old('lease_id', '')),
        isEditMode: false,
        loading: false,
        shake: false,

        get isCheckoutOrRenew() {
            return this.action === 'renew' || this.action === 'checkout';
        },
        
        openModal(detail = {}) {
            console.log('🚀 [Debug] openModal received detail:', detail);

            let rawStatus = detail.status ? String(detail.status) : 'New';
            if (rawStatus === 'R' || rawStatus === 'Renew') rawStatus = 'Renew';
            else if (rawStatus === 'C' || rawStatus === 'Check Out') rawStatus = 'Check Out';
            else rawStatus = 'New';

            this.formStatus = rawStatus;
            this.formLeaseId = (detail.leaseId && detail.leaseId !== '0') ? String(detail.leaseId) : '';
            this.open = true;

            // 🔍 COMPREHENSIVE LEASE, INVOICE & ITEM INSPECTOR
            if (this.formLeaseId) {
                const leaseObj = typeof allLeases !== 'undefined' ? allLeases[this.formLeaseId] : null;
                const previewObj = typeof leasePreviewData !== 'undefined' ? leasePreviewData[this.formLeaseId] : null;

                console.group(`🔍 [Lease Data Inspector for ID: ${this.formLeaseId}]`);
                console.log('📋 Full Lease Object from allLeases:', leaseObj);
                console.log('💰 Lease Preview Data from leasePreviewData:', previewObj);

                if (previewObj && previewObj.invoices) {
                    console.log('📄 Invoices found:', previewObj.invoices);
                    previewObj.invoices.forEach((inv, index) => {
                        console.group(`--- Invoice #${index + 1} (ID: ${inv.id}, No: ${inv.invoice_no || 'N/A'}) ---`);
                        console.log('Invoice Details:', inv);
                        console.log('Invoice Items / Line Items:', inv.items || inv.invoice_items || 'No items property found');
                        console.groupEnd();
                    });
                } else {
                    console.log('⚠️ No invoices array found inside leasePreviewData for this lease ID.');
                }
                console.groupEnd();
            }

            if (this.formStatus === 'Renew') {
                this.action = 'renew';
            } else if (this.formStatus === 'Check Out') {
                this.action = 'checkout';
            } else {
                this.action = 'create';
            }

            // Force visibility toggle immediately on next ticks
            this.$nextTick(() => {
                setTimeout(() => {
                    toggleLeaseSelect(this.formStatus);
                    if (this.formLeaseId && typeof handleLeaseChange === 'function') {
                        handleLeaseChange(this.formLeaseId);
                    }
                }, 50);
            });
        }
     }"
     @open-lease-modal.window="openModal($event.detail)"
     @keydown.escape.window="open = false"
     x-show="open" 
     class="fixed inset-0 z-50 overflow-y-auto" 
     style="display: none;"
     x-cloak>
    
    {{-- Backdrop (Triggers shake instead of closing) --}}
    <div x-show="open" 
         x-transition.opacity
         class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" 
         @click="shake = true; setTimeout(() => shake = false, 400)">
    </div>

    {{-- Modal Dialog --}}
    <div class="flex min-h-screen items-center justify-center p-4">
        <div x-show="open" 
             x-transition.scale
             :class="{ 'animate-shake': shake }"
             class="relative w-full max-w-4xl bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-100 z-10 my-8">

            {{-- Dynamic Header --}}
            <div class="px-8 py-5 bg-gray-50 border-b border-gray-100 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight" 
                    x-text="action === 'renew' ? 'Renew Lease' : (action === 'checkout' ? 'Check Out Lease' : 'Create Lease')">
                </h1>
                <button @click="open = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            {{-- Modal Body --}}
            <div class="max-h-[80vh] overflow-y-auto">
                <x-form.form method="POST" action="{{ route('admin.leases.store') }}" @submit="loading = true">
                    @csrf
                    
                    <input type="hidden" name="redirect_to" value="{{ request()->routeIs('admin.dashboard') ? 'dashboard' : 'index' }}">

                    <div class="p-8 space-y-8">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                            <div class="md:col-span-1">
                                <x-form.input-label value="Status" :required="true" class="mb-1" />
                                <x-form.input-select
                                    name="status"
                                    id="lease-status"
                                    x-model="formStatus"
                                    :options="[
                                        'New' => 'New',
                                        'Renew' => 'Renew',
                                        'Check Out' => 'Check Out',
                                    ]"
                                    x-bind:disabled="isCheckoutOrRenew"
                                    @change="toggleLeaseSelect()"
                                />
                            </div>

                            <div id="tenant_field" class="md:col-span-3">
                                <x-form.input-label value="Select Tenant" :required="true" class="mb-1" />
                                <x-form.input-select
                                    name="tenant_id"
                                    id="tenant_id"
                                    :options="$tenants"
                                    valueField="id"
                                    labelField="user.name"
                                />
                            </div>

                            <div id="lease_select_container" class="md:col-span-3 hidden">
                                <x-form.input-label value="Select Existing Lease" :required="true" class="mb-1" />
                                <x-form.input-select 
                                    name="lease_id" 
                                    id="lease_id"
                                    x-model="formLeaseId"
                                    :options="$leases"
                                    value-field="id"
                                    label-field="computed_label"
                                    x-bind:disabled="isCheckoutOrRenew"
                                    @change="window.handleLeaseChange($event)"
                                />
                            </div>
                        </div>

                        <div id="property_select_type" class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                            <x-form.date-input 
                                id="check-out-date" 
                                name="checked_out_at" 
                                :value="old('checked_out_at')" 
                            />
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

                        <!-- CHECKOUT REFUND SECTION -->
                        @can('asedasdasd')
                        <div id="checkout_refund_section" class="mt-4 hidden space-y-4 p-5 bg-amber-50/60 border border-amber-200/80 rounded-2xl shadow-sm"
                        x-data="{
                            manualDeductions: [],
                            availableFeeTypes: allFeeTypes,
                            checkoutDate: '',
                            isEarlyCheckout: false, // Make it a reactive property instead of a complex getter
                            earlyDays: 0, // Store the number of early days
                            autoDeductionTotal: 0,

                            init() {
                                this.$watch('checkoutDate', (value) => {
                                    console.log('🔄 [Alpine Watcher] checkoutDate changed to:', value);
                                    this.evaluateEarlyCheckout();
                                });
                            },

                            evaluateEarlyCheckout() {
                                const leaseSelect = document.getElementById('lease_id');
                                
                                if (!leaseSelect || !leaseSelect.value || !this.checkoutDate) {
                                    this.isEarlyCheckout = false;
                                    this.earlyDays = 0;
                                    return;
                                }

                                const selectedLease = allLeases[leaseSelect.value];
                                if (!selectedLease || !selectedLease.end_date) {
                                    this.isEarlyCheckout = false;
                                    this.earlyDays = 0;
                                    return;
                                }

                                let checkoutDateObj;
                                if (this.checkoutDate.includes('-')) {
                                    checkoutDateObj = new Date(this.checkoutDate);
                                } else if (this.checkoutDate.includes('/')) {
                                    const parts = this.checkoutDate.split('/');
                                    if (parts.length !== 3) {
                                        this.isEarlyCheckout = false;
                                        this.earlyDays = 0;
                                        return;
                                    }
                                    checkoutDateObj = new Date(parseInt(parts[2], 10), parseInt(parts[1], 10) - 1, parseInt(parts[0], 10));
                                }

                                const leaseEndDateObj = new Date(selectedLease.end_date.split('T')[0]);
                                
                                // Check if it's early
                                this.isEarlyCheckout = checkoutDateObj < leaseEndDateObj;

                                if (this.isEarlyCheckout) {
                                    // Calculate exact difference in milliseconds and convert to days
                                    const diffTime = Math.abs(leaseEndDateObj - checkoutDateObj);
                                    this.earlyDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                                } else {
                                    this.earlyDays = 0;
                                }

                                console.log('📅 [Early Days Calculation]:', {
                                    checkout: checkoutDateObj,
                                    leaseEnd: leaseEndDateObj,
                                    earlyDays: this.earlyDays
                                });
                            },
                            addDeductionRow() {
                                this.manualDeductions.push({ fee_type_id: '', qty: 1, sell_price: 0 });
                            },
                            removeDeductionRow(index) {
                                this.manualDeductions.splice(index, 1);
                            },
                            updateItemPrice(index, eventOrId) {
                                // Extract the ID safely whether it's passed directly or via an event detail object
                                let feeTypeId = eventOrId;
                                if (eventOrId && typeof eventOrId === 'object') {
                                    feeTypeId = eventOrId.detail?.value || eventOrId.target?.value || eventOrId.value;
                                }

                                let found = this.availableFeeTypes.find(f => f.id == feeTypeId);
                                if (found) {
                                    this.manualDeductions[index].fee_type_id = feeTypeId;
                                    this.manualDeductions[index].sell_price = (found.sell_price || 0) / 100;
                                } else {
                                    this.manualDeductions[index].sell_price = 0;
                                }
                            },
                            calculateManualTotal() {
                                return this.manualDeductions.reduce((sum, item) => {
                                    let qty = parseFloat(item.qty) || 0;
                                    let price = parseFloat(item.sell_price) || 0;
                                    return sum + (qty * price);
                                }, 0);
                            },
                            handleFeeTypeAdded(detail) {
                                this.availableFeeTypes.push(detail);
                                let lastItem = this.manualDeductions[this.manualDeductions.length - 1];
                                if (lastItem && !lastItem.fee_type_id) {
                                    lastItem.fee_type_id = detail.id;
                                    this.updateItemPrice(this.manualDeductions.length - 1, detail.id);
                                }
                            }
                        }"
                        @fee-type-added.window="handleFeeTypeAdded($event.detail)">
                            
                            {{-- Sticky Header Section --}}
                            <div class="sticky top-0 z-10 bg-amber-50/95 backdrop-blur-xs py-2 -mx-1 px-1 flex items-start justify-between border-b border-amber-200/60 mb-3">
                                <div>
                                    <h4 class="text-sm font-bold text-amber-900 mb-1">
                                        {{ __('Refund Settlement') }}
                                        <span x-show="isEarlyCheckout" class="ml-1.5 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider bg-red-100 text-red-700 rounded-full border border-red-200" x-cloak>
                                            Early Check Out (<span x-text="earlyDays"></span> days early)
                                        </span>
                                    </h4>
                                </div>

                                {{-- Action Buttons --}}
                                <div class="flex items-center gap-3 shrink-0 pt-0.5">
                                    <button type="button" @click="$dispatch('open-fee-type-modal', { category: 'item' })" class="inline-flex items-center text-xs font-bold text-gray-600 hover:text-indigo-600 transition-colors">
                                        + Add Fee Type
                                    </button>
                                    <button type="button" @click="addDeductionRow()" class="inline-flex items-center text-xs font-bold text-indigo-600 hover:text-indigo-700 transition-colors">
                                        + Add Deduction Item
                                    </button>
                                </div>
                            </div>

                            {{-- Main Summary Card --}}
                            <div class="bg-white p-4 rounded-xl border border-amber-200/60 shadow-xs space-y-4">
                                
                                {{-- Total Deposit Collected (Header Row) --}}
                                <div class="flex justify-between items-center pb-3">
                                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500">{{ __('Total Deposit Collected') }}</span>
                                    <span id="checkout-total-deposit" class="text-sm font-bold text-gray-900">RM 0.00</span>
                                </div>

                                {{-- 1. Auto-detected Unpaid Invoices Container --}}
                                <div id="auto-deductions-container" class="hidden space-y-2 pb-3">
                                    <span class="text-[11px] font-bold text-slate-600 uppercase tracking-wider block">{{ __('Auto-Detected Unpaid Invoices') }}</span>
                                    <div id="auto-deductions-list" class="space-y-2 text-xs">
                                        <!-- Dynamically injected unpaid invoices will appear here -->
                                    </div>
                                </div>

                                {{-- 2. Custom Deductions & Charges Section (Hidden automatically when empty) --}}
                                <div class="space-y-3 pt-1" x-show="manualDeductions.length > 0" x-transition>
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] font-bold text-slate-600 uppercase tracking-wider">{{ __('Custom Deductions & Charges') }}</span>
                                    </div>

                                    {{-- Custom Deduction Rows styled matching the auto-deductions layout --}}
                                    <div class="space-y-2">
                                        <template x-for="(item, index) in manualDeductions" :key="index">
                                            <div class="flex items-center justify-between bg-white p-2.5 rounded-lg border border-gray-200 gap-3">
                                                
                                                {{-- Delete / Remove Button --}}
                                                <button type="button" @click="removeDeductionRow(index)" class="shrink-0 w-6 h-6 flex items-center justify-center text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors text-base font-bold" title="Remove">
                                                    &times;
                                                </button>

                                                {{-- Fee Type Selector (Flexible width) --}}
                                                <div class="flex-1">
                                                    <x-form.input-select
                                                        x-model="item.fee_type_id"
                                                        ::name="'items['+index+'][fee_type_id]'"
                                                        :options="$itemFeeTypes->map(fn($f) => ['value' => (string)$f->id, 'label' => $f->name . ' (Item)'])->values()"
                                                        placeholder="Select Item"
                                                        :ignore-checkout-or-renew="true"
                                                        @change="updateItemPrice(index, $event)" 
                                                    />
                                                </div>

                                                {{-- Quantity Input --}}
                                                <div class="w-16 shrink-0">
                                                    <x-form.text-input 
                                                        type="text" 
                                                        min="1" 
                                                        step="1" 
                                                        ::name="'items['+index+'][qty]'" 
                                                        x-model.number="item.qty"
                                                        required 
                                                        title="Quantity"
                                                        class="block w-full px-2 py-1.5 bg-white border border-gray-200 rounded-lg text-xs text-center font-semibold focus:ring-2 focus:ring-indigo-500" 
                                                    />
                                                </div>

                                                {{-- Hidden input to store computed total amount for backend submission --}}
                                                <input type="hidden" :name="'items['+index+'][amount]'" :value="((parseFloat(item.qty) || 0) * (parseFloat(item.sell_price) || 0)).toFixed(2)">

                                                {{-- Line Total Preview matching text style --}}
                                                <div class="w-24 shrink-0 text-right text-xs font-semibold text-red-600 pr-1" 
                                                    x-text="'RM ' + ((parseFloat(item.qty) || 0) * (parseFloat(item.sell_price) || 0)).toFixed(2)">
                                                    RM 0.00
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                {{-- Final Net Summary Calculation Footer --}}
                                <div class="pt-3 border-t border-gray-200 space-y-2">
                                    <div class="flex justify-between items-center text-xs text-gray-600">
                                        <span>{{ __('Total Deductions (Auto + Custom):') }}</span>
                                        <span class="font-semibold text-red-600">- RM <span id="checkout-total-deductions" x-text="(autoDeductionTotal + calculateManualTotal()).toFixed(2)">0.00</span></span>
                                    </div>

                                    <div class="flex justify-between items-center text-base font-bold text-gray-900 pt-1">
                                        <span>{{ __('Nett Refund Amount:') }}</span>
                                        <span id="checkout-net-refund" class="text-indigo-600 text-lg" x-text="'RM ' + Math.max(0, (parseFloat(window.checkoutTotalDepositValue || 0) - (autoDeductionTotal + calculateManualTotal()))).toFixed(2)">RM 0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endcan

                        <x-modals.add-fee-type-modal />

                        <div id="lease_charges_section" class="space-y-4 border-t border-gray-200 pt-6">
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

                    {{-- Footer Buttons --}}
                    <div class="px-8 py-5 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
                        <button type="button" @click="open = false" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        
                        {{-- Use an inner span for x-text so the component's loading SVG is preserved --}}
                        <x-form.primary-button type="submit" loading="loading" class="px-5 py-2.5">
                            <span x-text="action === 'renew' ? 'Process Renewal' : (action === 'checkout' ? 'Confirm Check Out' : 'Create Lease')"></span>
                        </x-form.primary-button>
                    </div>
                </x-form.form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    @php
        $jsAllFeeTypes = collect()
            ->concat($itemFeeTypes->map(fn($f) => [
                'id' => $f->id, 
                'name' => $f->name, 
                'sell_price' => $f->sell_price ?? 0,
                'original_price' => $f->original_price ?? 0
            ]))
            ->values();

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
        data-all-fee-types="{{ json_encode($jsAllFeeTypes) }}"
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
        const serverDataEl = document.getElementById('server-data');
        const allFeeTypes = JSON.parse(serverDataEl.getAttribute('data-all-fee-types') || '[]');
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

        function toggleLeaseSelect(statusParam = null) {
            let resolvedStatus = statusParam;

            if (resolvedStatus && (typeof resolvedStatus === 'object' || Array.isArray(resolvedStatus))) {
                if (Array.isArray(resolvedStatus) || resolvedStatus instanceof Array) {
                    resolvedStatus = resolvedStatus[0] || null;
                } else {
                    resolvedStatus = resolvedStatus.detail?.value || resolvedStatus.target?.value || resolvedStatus[0] || null;
                }
            }

            if (!resolvedStatus || typeof resolvedStatus === 'object') {
                const modalEl = document.querySelector('[x-data*="openModal"]');
                if (modalEl && window.Alpine) {
                    const alpineData = window.Alpine.$data(modalEl);
                    if (alpineData && alpineData.formStatus) {
                        resolvedStatus = Array.isArray(alpineData.formStatus) ? alpineData.formStatus[0] : alpineData.formStatus;
                    }
                }
            }

            const newStatus = resolvedStatus || 'New';

            const sections = {
                'lease_select_container': ['Renew', 'Check Out', 'End Agreement'].includes(newStatus),
                'property_select_type': newStatus === 'New',
                'tenant_field': newStatus === 'New',
                'date_section': ['New', 'Renew'].includes(newStatus),
                'bring_forward_notice': newStatus === 'Renew',
                'lease_charges_section': newStatus !== 'Check Out',
                'checkout_refund_section': newStatus === 'Check Out', // <-- Added checkout summary section visibility
                'check_out_section': newStatus === 'Check Out',
                'agreement_end_section': newStatus === 'End Agreement',
                'agreement_template_section': ['New', 'Renew', 'Check Out'].includes(newStatus)
            };

            requestAnimationFrame(() => {
                Object.keys(sections).forEach(id => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    
                    const isVisible = sections[id];
                    el.classList.toggle('hidden', !isVisible);

                    el.querySelectorAll('input, select, textarea').forEach(input => {
                        if (input.hasAttribute('data-locked')) {
                            return;
                        }
                        input.disabled = !isVisible;
                    });
                });
            });
        }

        // ==========================================
        // Modal Preview Event Listeners & Handlers
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
                    alert("⚠️️ The selected template has no content!");
                    return null;
                }

                let content = templateData.content;
                const title = templateData.title;

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

                            if (text.includes('(management)')) {
                                managementSum += amount;
                            }
                        }
                    }
                });

                replacements['{security_deposit}'] = securitySum.toFixed(2);
                replacements['{utilities_deposit}'] = utilitiesSum.toFixed(2);
                replacements['{combined_deposit}'] = combinedSum.toFixed(2);
                replacements['{total_deposit}'] = totalDepositSum.toFixed(2);
                replacements['{management_fee}'] = managementSum.toFixed(2);

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

            const checkoutDateInput = document.getElementById('check-out-date');
            if (checkoutDateInput) {
                const syncCheckoutDate = (val) => {
                    const refundSection = document.getElementById('checkout_refund_section');
                    if (refundSection && window.Alpine) {
                        let alpineData = window.Alpine.$data(refundSection);
                        if (alpineData) {
                            alpineData.checkoutDate = val;
                            console.log('🔄 [Synced Checkout Date to Alpine]:', val);
                        }
                    }
                };

                checkoutDateInput.addEventListener('change', (e) => syncCheckoutDate(e.target.value));
                checkoutDateInput.addEventListener('input', (e) => syncCheckoutDate(e.target.value));

                if (checkoutDateInput._flatpickr) {
                    checkoutDateInput._flatpickr.config.onChange.push(function(selectedDates, dateStr) {
                        syncCheckoutDate(dateStr);
                    });
                }
            }
        });

        const currentUserId = @js(function_exists('get_effective_user') ? get_effective_user()->id : null);

        function filterTemplates(overrideOwnerId = null) {
            let ownerId = overrideOwnerId; 

            if (!ownerId) {
                const leaseSelect = document.getElementById('lease_id');
                if (leaseSelect && leaseSelect.value !== "") {
                    const selectedLease = leaseSelect.options[leaseSelect.selectedIndex];
                    if (selectedLease) {
                        ownerId = selectedLease.getAttribute('data-owner-id');
                    }
                }
            }

            if (!ownerId) {
                const typeEl = document.getElementById('lease_selection');
                if (typeEl && typeEl.value) {
                    const leaseType = typeEl.value; 
                    const activeSelectInput = document.getElementById(leaseType + '_select_input');
                    
                    if (activeSelectInput && activeSelectInput.value) {
                        const targetId = activeSelectInput.value;

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

            Array.from(agreementSelect.options).forEach(option => {
                if (option.value === "") return;
                const templateUserId = option.getAttribute('data-agreement-user-id');
                
                const matchesOwner = ownerId && String(templateUserId) === String(ownerId);
                const matchesAuthUser = currentUserId && String(templateUserId) === String(currentUserId);

                if (matchesOwner || matchesAuthUser) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });

            if (agreementSelect.selectedIndex > 0 && agreementSelect.options[agreementSelect.selectedIndex].style.display === 'none') {
                agreementSelect.value = "";
            }
        }

        function handleLeaseChange(eventOrId) {
            let leaseId = null;

            if (eventOrId !== undefined && eventOrId !== null) {
                if (typeof eventOrId === 'object') {
                    leaseId = eventOrId.detail?.value || eventOrId.target?.value || eventOrId.value || null;
                } else {
                    leaseId = eventOrId;
                }
            }

            if (!leaseId) {
                const leaseSelectInput = document.getElementById('lease_id');
                if (leaseSelectInput) {
                    leaseId = leaseSelectInput.value;
                }
            }

            if (!leaseId || !allLeases || !allLeases[leaseId]) {
                return; 
            }

            const selectedLease = allLeases[leaseId];

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

            const ownerId = selectedLease.owner_id || null;

            const leaseSelect = document.getElementById('lease_id');
            if (leaseSelect && ownerId) {
                const currentOption = leaseSelect.options ? leaseSelect.options[leaseSelect.selectedIndex] : null;
                if (currentOption) {
                    currentOption.setAttribute('data-owner-id', ownerId);
                }
            }

            const docId = selectedLease.document_id || selectedLease.agreement_id || selectedLease.template_id;
            if (docId) {
                const documentSelect = document.getElementById('document_id');
                if (documentSelect) {
                    documentSelect.value = docId;
                }
            }

            const noticeBox = document.getElementById('bring_forward_notice');
            const itemsContainer = document.getElementById('bring-forward-items-container'); 
            const leaseData = leasePreviewData[leaseId];

            if (leaseData) {
                function collectFullHistory(lease, accumulator = []) {
                    if (!lease) return accumulator;
                    if (lease.charges) {
                        accumulator.push(...lease.charges);
                    }
                    const parentLease = lease.parent_lease || lease.parentLease;
                    return collectFullHistory(parentLease, accumulator);
                }

                const allHistoryCharges = collectFullHistory(leaseData);
                
                // 🔍 Filter deposit charges where the invoice is PAID
                const depositCharges = allHistoryCharges.filter(charge => {
                    const category = charge.feeType?.category || charge.fee_type?.category;
                    if (category !== 'deposit') return false;

                    // Check if linked invoice status is 'paid'
                    if (charge.invoice && charge.invoice.status === 'paid') {
                        return true;
                    }

                    // Fallback check if charge itself has a direct paid status/flag
                    if (charge.status === 'paid' || charge.is_paid === true) {
                        return true;
                    }

                    // Otherwise, exclude unpaid deposits from collected total
                    return false;
                });

                let totalDeposit = 0;
                depositCharges.forEach(charge => {
                    totalDeposit += parseFloat(charge.amount / 100 || 0);
                });

                // 1. Update Bring Forward Notice Box (for Renewals)
                if (noticeBox && itemsContainer) {
                    const currentCharges = leaseData.charges || [];
                    const currentChargeElements = currentCharges.length > 0 
                        ? currentCharges.map(charge => {
                            const name = charge.feeType?.name || charge.fee_type?.name || charge.name || 'Charge';
                            const amount = parseFloat(charge.amount / 100 || 0);
                            return `<div>• RM ${amount.toFixed(2)} (${name})</div>`;
                        }).join('')
                        : '<div class="text-gray-500 italic">No charges found in this lease.</div>';

                    if (currentCharges.length > 0 || depositCharges.length > 0) {
                        noticeBox.classList.remove('hidden'); 
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

                // 2. Update Checkout Refund Section (for Check Outs)
                const checkoutTotalDepositEl = document.getElementById('checkout-total-deposit');
                const autoDeductionsContainer = document.getElementById('auto-deductions-container');
                const autoDeductionsList = document.getElementById('auto-deductions-list');

                if (checkoutTotalDepositEl) {
                    checkoutTotalDepositEl.innerText = `RM ${totalDeposit.toFixed(2)}`;
                    window.checkoutTotalDepositValue = totalDeposit; // Store for Alpine net refund calculation
                }

                // Grab auto unpaid invoices from leaseData if available (excluding paid and void)
                let autoDeductionSum = 0;
                if (leaseData && autoDeductionsList && autoDeductionsContainer) {
                    autoDeductionsList.innerHTML = '';
                    const unpaidInvoices = leaseData.invoices ? leaseData.invoices.filter(inv => inv.status !== 'paid' && inv.status !== 'void') : [];

                    if (unpaidInvoices.length > 0) {
                        autoDeductionsContainer.classList.remove('hidden');
                        let htmlContent = '';
                        unpaidInvoices.forEach((inv, idx) => {
                            let balance = parseFloat(inv.amount_balance || inv.total_amount || 0) / 100;
                            autoDeductionSum += balance;

                            let feeNames = [];
                            let lineItems = inv.items || inv.invoice_items || [];
                            if (Array.isArray(lineItems) && lineItems.length > 0) {
                                lineItems.forEach(item => {
                                    let fName = item.feeType?.name || item.fee_type?.name || item.fee_name || item.name || item.description;
                                    if (fName && !feeNames.includes(fName)) feeNames.push(fName);
                                });
                            }
                            let feeTypeText = feeNames.length > 0 ? feeNames.join(', ') : (inv.description || 'General Charge');

                            htmlContent += `
                                <div class="flex justify-between items-center bg-white p-2 rounded border border-gray-200">
                                    <span>${inv.invoice_no || inv.id} (${feeTypeText})</span>
                                    <span class="font-semibold text-red-600">RM ${balance.toFixed(2)}</span>
                                    <input type="hidden" name="auto_deductions[${idx}][invoice_id]" value="${inv.id}">
                                    <input type="hidden" name="auto_deductions[${idx}][amount]" value="${balance.toFixed(2)}">
                                </div>
                            `;
                        });
                        autoDeductionsList.innerHTML = htmlContent;
                    } else {
                        autoDeductionsContainer.classList.add('hidden');
                    }
                }
                
                // 🌟 Push to both window AND Alpine reactive state
                window.autoDeductionTotal = autoDeductionSum;
                const refundSectionEl = document.getElementById('checkout_refund_section');
                if (refundSectionEl && window.Alpine) {
                    let alpineData = window.Alpine.$data(refundSectionEl);
                    if (alpineData) {
                        alpineData.autoDeductionTotal = autoDeductionSum;
                    }
                }
            }

            if (typeof filterTemplates === 'function') {
                filterTemplates(ownerId);
            }
        }
    </script>
@endpush