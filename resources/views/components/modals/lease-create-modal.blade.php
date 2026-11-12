@props(['tenants', 'leases', 'properties', 'units', 'rooms', 'templates', 'refundTemplates','rentFeeTypes', 'serviceFeeTypes', 'depositFeeTypes', 'managementFeeTypes', 'itemFeeTypes', 'leasePreviewData'])

@php
    $leaseCreateErrorFields = [
        'status',
        'lease_id',
        'lease_selection',
        'property_id',
        'unit_id',
        'room_id',
        'tenant_id',
        'start_date',
        'end_date',
        'checked_out_at',
        'refund_document_id',
        'agreement_ended_at',
        'charges',
        'document_id',
        'type',
        'reason',
        'internal_notes',
        'items',
        'auto_deductions',
        'bank_detail_id',
        'method',
        'reference',
        'transition_reason',
        'settlement_fingerprint',
        'refund',
    ];
@endphp

<x-modals.add-fee-type-modal />

<div id="lease-create-modal-root" x-data="{
        open: @js($errors->hasAny($leaseCreateErrorFields)),
        action: '{{ old('status') === 'Renew' ? 'renew' : (old('status') === 'Check Out' ? 'checkout' : 'create') }}', 
        formStatus: @js(old('status', 'New')), 
        formLeaseId: @js(old('lease_id', '')),
        renewalStartDate: @js(old('start_date', '')),
        submittingLease: false,
        checkoutSubmitting: false,
        submitError: '',
        refundData: {},
        refundLoading: false,
        refundReady: false,
        refundLoadError: '',
        refundLoadSequence: 0,
        refundForceRefresh: false,
        isEditMode: false,
        loading: false,
        shake: false,

        get isCheckoutOrRenew() {
            return this.action === 'renew' || this.action === 'checkout';
        },

        syncRenewalStartDate(input) {
            const status = Array.isArray(this.formStatus) ? this.formStatus[0] : this.formStatus;
            const leaseId = String(Array.isArray(this.formLeaseId) ? (this.formLeaseId[0] || '') : (this.formLeaseId || ''));
            const minimum = status === 'Renew' ? (window.allLeases?.[leaseId]?.renewal_min_start_date || null) : null;
            const picker = input._flatpickr;
            if (!picker) return;
            const selected = picker.selectedDates[0];
            const invalid = minimum && selected && picker.formatDate(selected, 'Y-m-d') < minimum;
            picker.set('minDate', minimum);
            if (invalid) picker.clear();
        },

        syncRenewalEndDate(input) {
            const status = Array.isArray(this.formStatus) ? this.formStatus[0] : this.formStatus;
            const minimum = status === 'Renew' ? (this.renewalStartDate || null) : null;
            const picker = input._flatpickr;
            if (!picker) return;
            const selected = picker.selectedDates[0];
            const invalid = minimum && selected && picker.formatDate(selected, 'Y-m-d') < minimum;
            picker.set('minDate', minimum);
            if (invalid) picker.clear();
        },

        async sendLeaseForm(form, url) {
            this.submitError = '';
            try {
                const response = await fetch(url, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form)
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    this.open = true;
                    this.submitError = data.errors && Object.keys(data.errors).length
                        ? Object.values(data.errors).flat().join(' ') : (data.message || 'Unable to process the lease. Review the details and try again.');
                    window.dispatchEvent(new CustomEvent('session-status', {
                        detail: data.session_status || { type: 'error', message: this.submitError }
                    }));
                    return;
                }
                if (data.redirect) window.location.assign(data.redirect);
                else window.location.reload();
                return true;
            } catch (error) {
                this.open = true;
                this.submitError = 'The result could not be confirmed. Refresh and check the lease before submitting again.';
                window.dispatchEvent(new CustomEvent('session-status', { detail: { type: 'error', message: this.submitError } }));
            }
        },

        async submitLease(event) {
            event.preventDefault();
            if (this.submittingLease || (this.action === 'checkout' && !this.refundReady)) {
                return;
            }
            this.submittingLease = true;
            this.$dispatch('form-loading', true);
            let success = false;
            try { success = await this.sendLeaseForm(event.target, event.target.action); }
            finally { if (!success) { this.submittingLease = false; this.$dispatch('form-loading', false); } }
        },

        get refundStatus() {
            if (!this.formLeaseId || !window.leasePreviewData) return 'none';
            const preview = this.refundData[this.formLeaseId]
                || window.leasePreviewData[this.formLeaseId];
            const status = preview?.active_refund?.status;
            return typeof status === 'object'
                ? String(status.value || 'none').toLowerCase()
                : String(status || 'none').toLowerCase();
        },
        
        openModal(detail = {}) {
            console.log('🚀 [Debug] openModal received detail:', detail);

            let rawStatus = detail.status ? String(detail.status) : 'New';
            if (rawStatus === 'R' || rawStatus === 'Renew') rawStatus = 'Renew';
            else if (rawStatus === 'C' || rawStatus === 'Check Out') rawStatus = 'Check Out';
            else rawStatus = 'New';

            this.formStatus = rawStatus;
            this.submitError = '';
            this.refundLoadSequence++;
            this.refundReady = false;
            this.refundLoading = rawStatus === 'Check Out';
            this.refundLoadError = '';
            this.refundForceRefresh = rawStatus === 'Check Out';
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
                        if (this.action === 'checkout') {
                            handleLeaseChange(this.formLeaseId);
                        }
                    }
                }, 50);
            });
        }
     }"
     @open-lease-modal.window="openModal($event.detail)"
     x-init="if (open && action === 'checkout' && formLeaseId) $nextTick(() => { toggleLeaseSelect(formStatus); handleLeaseChange(formLeaseId); })"
     @keydown.escape.window="if (!submittingLease && !checkoutSubmitting) open = false"
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
        <template x-if="open">
            <div x-show="open" 
                x-transition.scale
                :class="{ 'animate-shake': shake }"
                class="relative w-full max-w-4xl bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-100 z-10 my-8">

                {{-- Dynamic Header --}}
                <div class="px-8 py-5 bg-gray-50 border-b border-gray-100 flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight" 
                        x-text="action === 'renew' ? 'Renew Lease' : (action === 'checkout' ? 'Check Out Lease' : 'Create Lease')">
                    </h1>
                    <button type="button" @click="open = false" :disabled="submittingLease || checkoutSubmitting" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="max-h-[80vh] overflow-y-auto">
                    <x-form.form method="POST" action="{{ route('admin.leases.store') }}" :async="true" @submit="submitLease($event)">
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
                                        @change="if (action === 'checkout') window.handleLeaseChange($event)"
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
                                    <x-form.date-input id="start-date" name="start_date" :value="old('start_date')" x-model="renewalStartDate" x-effect="syncRenewalStartDate($el)" />
                                    <x-form.input-error :messages="$errors->get('start_date')" class="mt-1" />
                                </div>

                                <div>
                                    <x-form.input-label value="End Date" :required="true" class="mb-1" />
                                    <x-form.date-input id="end-date" name="end_date" :value="old('end_date')" x-effect="syncRenewalEndDate($el)" />
                                    <x-form.input-error :messages="$errors->get('end_date')" class="mt-1" />
                                </div>
                            </div>

                            <div id="agreement_end_section" class="mt-4 hidden">
                                <x-form.input-label value="Agreement Ended Date" class="mb-1" />
                                <x-form.date-input id="agreement-end-date" name="agreement_ended_at" :value="old('agreement_ended_at')" />
                                <x-form.input-error :messages="$errors->get('agreement_ended_at')" class="mt-1" />
                            </div>

                            <div id="agreement_template_section" class="mt-4">
                                <div class="flex justify-between items-center mb-1">
                                    <x-form.input-label value="Agreements Template" :required="true" />
                                    <button type="button" id="preview-btn" class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors uppercase tracking-wider">
                                        <span class="preview-loading-spinner hidden" aria-hidden="true">
                                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </span>
                                        Preview Template
                                    </button>
                                </div>
                                
                                <x-form.input-select
                                    name="document_id"
                                    id="document_id"
                                    placeholder="-- Select Template --"
                                    :options="$templates->mapWithKeys(fn ($template) => [
                                        $template->id => $template->title . ' (v' . $template->version . ') - ' . $template->user->name,
                                    ])->toArray()"
                                    :value="old('document_id')"
                                    :ignore-checkout-or-renew="true"
                                />
                                
                                <x-form.input-error :messages="$errors->get('document_id')" class="mt-1" />
                            </div>

                            
                            <!-- CHECKOUT REFUND SECTION -->
                            <div x-show="action === 'checkout' && !refundReady" x-cloak class="mt-4">
                                <x-loading-skeleton variant="checkout" label="Preparing checkout settlement" x-show="refundLoading" ::aria-busy="refundLoading" />
                                <div x-show="refundLoadError && !refundLoading" role="alert" class="rounded-xl border border-slate-200 bg-slate-50 p-6 text-center">
                                    <p class="text-sm font-semibold text-slate-900">Unable to open settlement</p>
                                    <p class="mt-2 text-sm text-slate-600" x-text="refundLoadError"></p>
                                    <button type="button" @click="handleLeaseChange(formLeaseId)" class="mt-4 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Try again</button>
                                </div>
                            </div>
                            <div id="checkout_refund_section" class="mt-4 hidden space-y-4"
                            x-show="refundReady" :inert="!refundReady"
                            x-data="{
                                manualDeductions: [],
                                refundData: {},
                                availableFeeTypes: window.allFeeTypes || [],
                                checkoutDate: '',
                                isEarlyCheckout: false,
                                earlyDays: 0,
                                autoDeductionTotal: 0,
                                walletRefundAmount: 0,
                                tenantBankDetails: [],
                                selectedBankId: '',

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

                                    const selectedLease = (window.allLeases || {})[leaseSelect.value];
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
                                    
                                    this.isEarlyCheckout = checkoutDateObj < leaseEndDateObj;

                                    if (this.isEarlyCheckout) {
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
                                    let feeTypeId = eventOrId;
                                    if (eventOrId && typeof eventOrId === 'object') {
                                        feeTypeId = eventOrId.detail?.value || eventOrId.target?.value || eventOrId.value;
                                    }
                                    
                                    // Explicitly assign it to the reactive item object
                                    this.manualDeductions[index].fee_type_id = feeTypeId;

                                    let found = this.availableFeeTypes.find(f => f.id == feeTypeId);
                                    if (found) {
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
                                },
                                get activeRefund() {
                                    if (!this.formLeaseId || !window.leasePreviewData) return null;
                                    const preview = this.refundData[this.formLeaseId]
                                        || window.leasePreviewData[this.formLeaseId];
                                    return preview?.active_refund || null;
                                },
                                get refundStatus() {
                                    if (!this.formLeaseId || !window.leasePreviewData) return 'none';
                                    const preview = this.refundData[this.formLeaseId]
                                        || window.leasePreviewData[this.formLeaseId];
                                    // Handle both string and Enum object serialization safely
                                    const status = preview?.active_refund?.status;
                                    if (!status) return 'none';
                                    return typeof status === 'object'
                                        ? String(status.value || 'none').toLowerCase()
                                        : String(status).toLowerCase();
                                }
                            }"
                            @fee-type-added.window="handleFeeTypeAdded($event.detail)">

                                <div id="check_out_section" class="mb-4">
                                    <x-form.input-label value="Check Out Date" class="mb-1" :required="true"/>
                                    <x-form.date-input 
                                        id="check-out-date" 
                                        name="checked_out_at" 
                                        x-model="checkoutDate"
                                        :value="old('checked_out_at')" 
                                    />
                                    <x-form.input-error :messages="$errors->get('checked_out_at')" class="mt-1" />
                                </div>

                                @php
                                    $refundTemplateOptions = $refundTemplates->map(function ($template) {
                                        return [
                                            'value' => $template->id,
                                            'label' => "{$template->title} (v{$template->version}) - {$template->user->name}",
                                        ];
                                    })->values()->all();
                                @endphp

                                {{-- Refund Agreement Template Section --}}
                                <div class="mb-4">
                                    <div class="flex justify-between items-center mb-1">
                                        <x-form.input-label value="Refund Agreement Template" :required="true" />
                                        <button type="button" id="preview-refund-template-btn" class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors uppercase tracking-wider">
                                            <span class="preview-loading-spinner hidden" aria-hidden="true">
                                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </span>
                                            Preview Refund Template
                                        </button>
                                    </div>
                                    
                                    <x-form.input-select
                                        name="refund_document_id"
                                        id="refund_document_id"
                                        :options="$refundTemplateOptions"
                                        :value="old('refund_document_id')"
                                        placeholder="-- Select Refund Template --"
                                        :ignore-checkout-or-renew="true"
                                    />
                                    
                                    <x-form.input-error :messages="$errors->get('refund_document_id')" class="mt-1" />
                                </div>

                                <template x-if="refundStatus === 'approved'">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <x-form.input-label value="Refund Bank Account" class="mb-1" :required="true" />
                                            <x-form.input-select
                                                name="bank_detail_id"
                                                x-model="selectedBankId"
                                                :options="[]"
                                                placeholder="Select tenant bank account"
                                                :ignore-checkout-or-renew="true"
                                                x-effect="options = (tenantBankDetails || []).map(bank => ({
                                                    value: String(bank.id),
                                                    label: `${bank.bank_name} - ${bank.account_holder_name} (****${bank.account_last_four || 'N/A'})`
                                                }))"
                                            />
                                        </div>

                                        <div>
                                            <x-form.input-label value="Payment Reference" class="mb-1" />
                                            <x-form.text-input
                                                type="text"
                                                name="reference"
                                                placeholder="Payment reference"
                                                class="w-full"
                                                aria-label="Refund payment reference"
                                            />
                                        </div>

                                        <input type="hidden" name="method" value="bank_transfer">
                                    </div>
                                </template>
                                
                                {{-- Professional Process Stepper Header --}}
                                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs mb-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                                            {{ __('Refund Workflow Progress') }}
                                        </h4>
                                        <span x-show="isEarlyCheckout" class="px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider bg-red-100 text-red-700 rounded-full border border-red-200" x-cloak>
                                            Early Check Out (<span x-text="earlyDays"></span> days early)
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-9 gap-1 items-center text-center text-xs font-semibold w-full" x-data="{
                                        getCurrentStep() {
                                            const map = { none: 1, draft: 2, pending: 3, approved: 4, processing: 4, paid: 5 };
                                            return map[String(this.refundStatus || 'none').toLowerCase()] || 1;
                                        }
                                    }">
                                        {{-- Step 1: Initial --}}
                                        <div class="col-span-1 flex flex-col items-center gap-1.5">
                                            <div :class="getCurrentStep() > 1 ? 'bg-indigo-500 text-white' : (getCurrentStep() === 1 ? 'bg-indigo-600 text-white ring-4 ring-indigo-100' : 'bg-slate-200 text-slate-600')" 
                                                class="w-7 h-7 rounded-full flex items-center justify-center font-bold transition-all text-xs mx-auto">
                                                1
                                            </div>
                                            <span :class="getCurrentStep() === 1 ? 'text-indigo-600 font-bold' : (getCurrentStep() > 1 ? 'text-indigo-600 font-medium' : 'text-slate-500')" class="whitespace-nowrap">Initial</span>
                                        </div>

                                        <div class="col-span-1 h-0.5 -mt-5 transition-all" :class="getCurrentStep() > 1 ? 'bg-indigo-500' : 'bg-slate-200'"></div>

                                        {{-- Step 2: Draft --}}
                                        <div class="col-span-1 flex flex-col items-center gap-1.5">
                                            <div :class="getCurrentStep() > 2 ? 'bg-indigo-500 text-white' : (getCurrentStep() === 2 ? 'bg-indigo-600 text-white ring-4 ring-indigo-100' : 'bg-slate-200 text-slate-600')" 
                                                class="w-7 h-7 rounded-full flex items-center justify-center font-bold transition-all text-xs mx-auto">
                                                2
                                            </div>
                                            <span :class="getCurrentStep() === 2 ? 'text-indigo-600 font-bold' : (getCurrentStep() > 2 ? 'text-indigo-600 font-medium' : 'text-slate-500')" class="whitespace-nowrap">Draft</span>
                                        </div>

                                        <div class="col-span-1 h-0.5 -mt-5 transition-all" :class="getCurrentStep() > 2 ? 'bg-indigo-500' : 'bg-slate-200'"></div>

                                        {{-- Step 3: Pending --}}
                                        <div class="col-span-1 flex flex-col items-center gap-1.5">
                                            <div :class="getCurrentStep() > 3 ? 'bg-indigo-500 text-white' : (getCurrentStep() === 3 ? 'bg-indigo-600 text-white ring-4 ring-indigo-100' : 'bg-slate-200 text-slate-600')" 
                                                class="w-7 h-7 rounded-full flex items-center justify-center font-bold transition-all text-xs mx-auto">
                                                3
                                            </div>
                                            <span :class="getCurrentStep() === 3 ? 'text-indigo-600 font-bold' : (getCurrentStep() > 3 ? 'text-indigo-600 font-medium' : 'text-slate-500')" class="whitespace-nowrap">Pending</span>
                                        </div>

                                        <div class="col-span-1 h-0.5 -mt-5 transition-all" :class="getCurrentStep() > 3 ? 'bg-indigo-500' : 'bg-slate-200'"></div>

                                        {{-- Step 4: Approved --}}
                                        <div class="col-span-1 flex flex-col items-center gap-1.5">
                                            <div :class="getCurrentStep() > 4 ? 'bg-indigo-500 text-white' : (getCurrentStep() === 4 ? 'bg-indigo-600 text-white ring-4 ring-indigo-100' : 'bg-slate-200 text-slate-600')" 
                                                class="w-7 h-7 rounded-full flex items-center justify-center font-bold transition-all text-xs mx-auto">
                                                4
                                            </div>
                                            <span :class="getCurrentStep() === 4 ? 'text-indigo-600 font-bold' : (getCurrentStep() > 4 ? 'text-indigo-600 font-medium' : 'text-slate-500')" class="whitespace-nowrap">Approved</span>
                                        </div>

                                        <div class="col-span-1 h-0.5 -mt-5 transition-all" :class="getCurrentStep() > 4 ? 'bg-indigo-500' : 'bg-slate-200'"></div>

                                        {{-- Step 5: Refunded --}}
                                        <div class="col-span-1 flex flex-col items-center gap-1.5">
                                            <div :class="getCurrentStep() === 5 ? 'bg-emerald-600 text-white ring-4 ring-emerald-100' : 'bg-slate-200 text-slate-600'" 
                                                class="w-7 h-7 rounded-full flex items-center justify-center font-bold transition-all text-xs mx-auto">
                                                5
                                            </div>
                                            <span :class="getCurrentStep() === 5 ? 'text-emerald-600 font-bold' : 'text-slate-500'" class="whitespace-nowrap">Refunded</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Unified Container for Refund Settlement --}}
                                <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden space-y-4">
                                    
                                    {{-- Sticky Header Bar (Now sticky relative to the unified card wrapper) --}}
                                    <div class="sticky top-0 z-20 bg-white py-3 px-4 border-b border-slate-200 flex items-center justify-between">
                                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                                            {{ __('Refund Settlement Items') }}
                                        </h4>

                                        {{-- Action Buttons --}}
                                        <div class="flex items-center gap-3" x-show="['none', 'draft'].includes(refundStatus)">
                                            @canany(['super-admin', 'leases.add fee type'])
                                            <button type="button" @click="$dispatch('open-fee-type-modal', { category: 'item' })" class="inline-flex items-center text-xs font-bold text-gray-600 hover:text-indigo-600 transition-colors">
                                                + Add Fee Type
                                            </button>
                                            @endcanany
                                            @canany(['super-admin', 'leases.add deduction item'])
                                            <button type="button" @click="addDeductionRow()" class="inline-flex items-center text-xs font-bold text-indigo-600 hover:text-indigo-700 transition-colors">
                                                + Add Deduction Item
                                            </button>
                                            @endcanany
                                        </div>
                                    </div>

                                    {{-- Main Summary Content Body (Padded inside the unified wrapper) --}}
                                    <div class="p-4 space-y-4">
                                        
                                        {{-- Total Deposit Collected --}}
                                        <div class="flex justify-between items-center pb-3 border-b border-slate-100">
                                            <span class="text-xs font-bold uppercase tracking-wider text-gray-500">{{ __('Total Deposit Collected') }}</span>
                                            <span id="checkout-total-deposit" class="text-sm font-bold text-gray-900">RM 0.00</span>
                                        </div>

                                        {{-- 1. Auto-detected Unpaid Invoices Container --}}
                                        <div x-show="walletRefundAmount > 0" x-cloak class="space-y-2 pb-3 border-b border-slate-100">
                                            <span class="text-[11px] font-bold text-slate-600 uppercase tracking-wider block">{{ __('Auto-Detected Wallet Balance') }}</span>
                                            <div class="flex justify-between items-center bg-white p-2 rounded border border-gray-200 text-xs">
                                                <span>{{ __('Wallet Balance to Refund') }}</span>
                                                <span class="font-semibold text-gray-900" x-text="'+ RM ' + walletRefundAmount.toFixed(2)"></span>
                                            </div>
                                        </div>

                                        <div id="auto-deductions-container" class="hidden space-y-2 pb-3 border-b border-slate-100">
                                            <span class="text-[11px] font-bold text-slate-600 uppercase tracking-wider block">{{ __('Auto-Detected Unpaid Invoices') }}</span>
                                            <div id="auto-deductions-list" class="space-y-2 text-xs">
                                                <!-- Dynamically injected unpaid invoices will appear here -->
                                            </div>
                                        </div>

                                        {{-- 2. Custom Deductions & Charges Section --}}
                                        <div class="space-y-3 pt-1" x-show="manualDeductions.length > 0 || refundStatus === 'none' || refundStatus === 'draft'" x-transition>
                                            <div class="flex items-center justify-between" x-show="['none', 'draft'].includes(refundStatus) || manualDeductions.length > 0">
                                                <span class="text-[11px] font-bold text-slate-600 uppercase tracking-wider">{{ __('Custom Deductions & Charges') }}</span>
                                            </div>

                                            <div class="space-y-2">
                                                <template x-for="(item, index) in manualDeductions" :key="index">
                                                    <div class="flex items-center gap-2 mb-2 bg-slate-50 p-2 rounded-lg border border-slate-200">
                                                        
                                                        {{-- Remove Button (Hidden if not in Draft/None) --}}
                                                        @canany(['super-admin', 'leases.add deduction item'])
                                                        <button type="button" 
                                                                x-show="['none', 'draft'].includes(refundStatus)"
                                                                @click="manualDeductions.splice(index, 1)"
                                                                class="text-slate-400 hover:text-red-600 p-1 rounded transition"
                                                                title="Remove item">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                            </svg>
                                                        </button>
                                                        @endcanany

                                                        <!-- Fee Type Select -->
                                                        <div class="flex-1">
                                                            <!-- Editable Select Component (Shown only during None or Draft) -->
                                                            <div x-show="['none', 'draft'].includes(refundStatus)">
                                                                <x-form.input-select
                                                                    x-model="item.fee_type_id"
                                                                    ::name="'items['+index+'][fee_type_id]'"
                                                                    :options="$itemFeeTypes->map(fn($f) => ['value' => (string)$f->id, 'label' => $f->name . ' (Item)'])->values()"
                                                                    placeholder="Select Item"
                                                                    :ignore-checkout-or-renew="true"
                                                                    @change="updateItemPrice(index, $event)" 
                                                                />
                                                                <input type="hidden" :name="'items['+index+'][fee_type_id]'" x-model="item.fee_type_id">
                                                            </div>

                                                            <!-- Read-Only Text Span (Shown during Pending, Approved, or Paid) -->
                                                            <div x-show="!['none', 'draft'].includes(refundStatus)" class="py-2 text-sm font-medium text-slate-700">
                                                                <span x-text="
                                                                    (() => {
                                                                        let found = availableFeeTypes.find(f => String(f.id) === String(item.fee_type_id));
                                                                        return found ? found.name + ' (Item)' : 'N/A';
                                                                    })()
                                                                "></span>
                                                            </div>
                                                        </div>

                                                        <!-- Quantity Display / Input -->
                                                        <div class="w-20">
                                                            <!-- Editable Input (Shown only during None or Draft) -->
                                                            <div x-show="['none', 'draft'].includes(refundStatus)">
                                                                <x-form.text-input 
                                                                    type="number"
                                                                    x-model.number="item.qty" 
                                                                    min="1" 
                                                                    @wheel.prevent 
                                                                />
                                                                <input type="hidden" :name="'items['+index+'][qty]'" x-model="item.qty">
                                                            </div>

                                                            <!-- Read-Only Text Span (Shown during Pending, Approved, or Paid) -->
                                                            <div x-show="!['none', 'draft'].includes(refundStatus)" class="py-2 text-sm font-medium text-slate-700 text-center">
                                                                <span x-text="item.qty || 1"></span>
                                                            </div>
                                                        </div>

                                                        <!-- Hidden Input for Sell Price -->
                                                        <input type="hidden" :name="'items['+index+'][sell_price]'" x-model="item.sell_price">

                                                        <!-- Computed Total Display -->
                                                        <div class="w-28 text-right pr-1">
                                                            <span class="text-sm font-semibold text-red-600" 
                                                                x-text="'RM ' + ((item.qty || 1) * (item.sell_price || 0)).toFixed(2)">
                                                            </span>
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
                                                <span id="checkout-net-refund" class="text-indigo-600 text-lg font-bold" x-text="'RM ' + Math.max(0, (parseFloat(window.checkoutTotalDepositValue || 0) + walletRefundAmount - (autoDeductionTotal + calculateManualTotal()))).toFixed(2)">RM 0.00</span>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <div x-show="action === 'checkout' && refundReady" x-cloak class="space-y-2">
                                <x-form.input-label for="refund-transition-reason" value="Reason for returning or revoking approval" />
                                <textarea id="refund-transition-reason" name="transition_reason" rows="2" maxlength="1000" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Required for Return to Draft or Revoke Approval">{{ old('transition_reason') }}</textarea>
                            </div>


                            <div id="lease_charges_section" class="space-y-4 border-t border-gray-200 pt-6">
                                <div class="flex justify-between items-center">
                                    <x-form.input-label value="Lease Charges & Deposits" :required="true" />
                                    <button type="button" onclick="addChargeRow()" class="px-3 py-1.5 bg-indigo-50 text-indigo-600 text-xs font-semibold rounded-lg hover:bg-indigo-100 transition-colors">
                                        + Add Charge Item
                                    </button>
                                </div>

                                @php
                                    $chargeTypeOptions = collect()
                                        ->concat($rentFeeTypes->map(fn($fee) => ['value' => (string) $fee->id, 'label' => $fee->name]))
                                        ->concat($serviceFeeTypes->map(fn($fee) => ['value' => (string) $fee->id, 'label' => $fee->name . ' (Service)']))
                                        ->concat($depositFeeTypes->map(fn($fee) => ['value' => (string) $fee->id, 'label' => $fee->name . ' (Deposit)']))
                                        ->concat($managementFeeTypes->map(fn($fee) => ['value' => (string) $fee->id, 'label' => $fee->name . ' (Management)']));
                                @endphp
                                <template id="charge-type-select-template">
                                    <x-form.input-select name="charges[__CHARGE_INDEX__][fee_type_id]"
                                        :options="$chargeTypeOptions" placeholder="-- Select Fee Type --" />
                                </template>
                                <div id="charges-container" class="space-y-4">
                                    <div class="charge-row rounded-lg border border-gray-200 bg-gray-50 p-4">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-xs font-semibold text-gray-500 tracking-wider">CHARGE ITEM #1</span>
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <x-form.input-label value="Charge Type" class="mb-1 text-xs" info="You may hide the charge type if not using it in 'Setting (Lease Settings)'"/>
                                                <x-form.input-select name="charges[0][fee_type_id]"
                                                    :options="$chargeTypeOptions" placeholder="-- Select Fee Type --"
                                                    :value="old('charges.0.fee_type_id')"
                                                    :ignore-checkout-or-renew="true"  />
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

                        {{-- Dynamic Footer Buttons based on Refund Lifecycle (Fixed / Sticky to Modal Bottom) --}}
                        <div class="sticky bottom-0 z-30 px-8 py-4 bg-white/95 backdrop-blur-sm border-t border-slate-200 shadow-lg flex items-center justify-between gap-3 w-full"
                            x-data="{
                                get activeRefund() {
                                    if (!this.formLeaseId || !window.leasePreviewData) return null;
                                    const preview = this.refundData?.[this.formLeaseId]
                                        || window.leasePreviewData[this.formLeaseId];
                                    return preview?.active_refund || null;
                                },
                                get currentRefundId() {
                                    return this.activeRefund?.id || '';
                                }
                            }">
                            
                            {{-- Hidden input to pass active refund ID to controller when clicking approve/disburse --}}
                            <input type="hidden" name="refund_id" :value="currentRefundId">
                            <input type="hidden" name="settlement_fingerprint" :value="activeRefund?.settlement_fingerprint || ''">

                            {{-- Left Side / Secondary Actions if needed (or keep empty to push buttons right) --}}
                            <div></div>

                            <div class="flex flex-wrap items-center justify-end gap-3">
                                <button type="button" @click="open = false" :disabled="submittingLease || checkoutSubmitting" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-colors">
                                    Cancel
                                </button>
                                
                                <template x-if="action === 'checkout' && refundReady">
                                    <div class="flex flex-wrap items-center justify-end gap-2" x-data="{
                                        subAction: '',
                                        async submitCheckout(routeUrl, actionName) {
                                            if (this.checkoutSubmitting || !this.refundReady || this.refundLoading || this.refundLoadError) return;
                                            this.checkoutSubmitting = true;
                                            this.subAction = actionName;
                                            const form = $el.closest('form');
                                            let success = false;
                                            try { success = await this.sendLeaseForm(form, routeUrl); }
                                            finally { if (!success) { this.subAction = ''; this.checkoutSubmitting = false; } }
                                        }
                                    }">
                                        
                                        {{-- STEP 1 & 2: Save as Draft or Direct Confirm & Set as Pending --}}
                                            <div class="flex items-center gap-2" x-show="['none', 'draft'].includes(refundStatus)" x-cloak>
                                                {{-- Save as Draft Button --}}
                                                @canany(['super-admin', 'leases.save draft'])
                                                <x-form.secondary-button 
                                                    type="button" 
                                                    loading="subAction === 'draft'" 
                                                    @click="submitCheckout('{{ route('admin.refunds.store-draft') }}', 'draft')"
                                                    class="px-4 py-2.5">
                                                    <span>Save as Draft</span>
                                                </x-form.secondary-button>
                                                @endcanany

                                                {{-- Confirm & Set as Pending Button (Direct Action) --}}
                                                @canany(['super-admin', 'leases.set as pending'])
                                                <x-form.primary-button 
                                                    type="button" 
                                                    loading="subAction === 'pending'" 
                                                    @click="submitCheckout('{{ route('admin.refunds.submit-pending') }}', 'pending')"
                                                    class="px-4 py-2.5 bg-amber-600 hover:bg-amber-700">
                                                    <span>Confirm & Set as Pending</span>
                                                </x-form.primary-button>
                                                @endcanany
                                            </div>

                                        {{-- STEP 3: PENDING -> Approve or Reject Refund --}}
                                            <div class="flex items-center gap-2" x-show="refundStatus === 'pending'" x-cloak>
                                                {{-- Reject / Send Back Button --}}
                                                @canany(['super-admin', 'leases.reject refund'])
                                                <x-form.secondary-button 
                                                    type="button"
                                                    loading="subAction === 'reject'" 
                                                    @click="submitCheckout('{{ route('admin.refunds.reject') }}', 'reject')"
                                                    class="!bg-red-600 !text-white !border-red-600 hover:!bg-red-700 focus:!ring-red-500">
                                                    Return to Draft
                                                </x-form.secondary-button>
                                                @endcanany

                                                {{-- Approve Button --}}
                                                @canany(['super-admin', 'leases.approve refund'])
                                                <x-form.primary-button 
                                                    type="button" 
                                                    loading="subAction === 'approve'" 
                                                    @click="submitCheckout('{{ route('admin.refunds.approve') }}', 'approve')"
                                                    class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700">
                                                    <span>Approve Refund</span>
                                                </x-form.primary-button>
                                                @endcanany
                                            </div>

                                        {{-- STEP 4: APPROVED -> Proceed to Final Refund Payout --}}
                                            <div class="flex items-center gap-2" x-show="refundStatus === 'approved'" x-cloak>
                                                @canany(['super-admin', 'leases.revoke refund approval'])
                                                <x-form.secondary-button type="button" loading="subAction === 'revoke'" @click="submitCheckout('{{ route('admin.refunds.revoke-approval') }}', 'revoke')">
                                                    Revoke Approval
                                                </x-form.secondary-button>
                                                @endcanany
                                                @canany(['super-admin', 'leases.proceed refund'])
                                                <x-form.primary-button
                                                    type="button"
                                                    loading="subAction === 'disburse'"
                                                    @click="submitCheckout('{{ route('admin.refunds.disburse') }}', 'disburse')"
                                                    class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700">
                                                    <span>Proceed to Refund</span>
                                                </x-form.primary-button>
                                                @endcanany
                                            </div>

                                        {{-- STEP 5: PAID / REFUNDED (Finished State) --}}
                                        <template x-if="refundStatus === 'processing'">
                                            <span class="text-sm text-gray-700">Refund payment is processing</span>
                                        </template>
                                        <template x-if="refundStatus === 'paid'">
                                            <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-3 py-2 rounded-lg border border-emerald-200">
                                                ✓ Refund Completed
                                            </span>
                                        </template>

                                    </div>
                                </template>

                                <template x-if="action !== 'checkout'">
                                    <x-form.primary-button type="submit" loading="submittingLease" class="px-5 py-2.5">
                                        <span x-text="action === 'renew' ? 'Process Renewal' : 'Create Lease'"></span>
                                    </x-form.primary-button>
                                </template>
                            </div>
                        </div>
                    </x-form.form>
                </div>
            </div>
        </template>
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
            'label' => $t->title . ' (v' . $t->version . ') - ' . $t->user->name,
            'content' => $t->html_template,
            'user_id' => (string) $t->user_id,
        ])->keyBy('id');

        $jsRefundTemplates = $refundTemplates->map(fn($t) => [
            'id' => (string) $t->id,
            'title' => $t->title,
            'label' => $t->title . ' (v' . $t->version . ') - ' . $t->user->name,
            'content' => $t->html_template,
            'user_id' => (string) $t->user_id,
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
    <script type="application/json" id="refund-templates-json">
        {!! json_encode($jsRefundTemplates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
    </script>

    <div id="server-data" class="hidden" style="display: none;"
        data-create-url="{{ route('admin.leases.create') }}"
        data-all-fee-types="{{ json_encode($jsAllFeeTypes) }}"
        data-rent-fees="{{ json_encode($jsRentFees) }}"
        data-deposit-fees="{{ json_encode($jsDepositFees) }}"
        data-all-leases="{{ json_encode($leases->mapWithKeys(fn ($lease) => [(string) $lease->id => array_merge($lease->toArray(), ['renewal_min_start_date' => $lease->end_date?->copy()->addDay()->toDateString()])])) }}"
        data-preview-data="{{ json_encode($leasePreviewData->keyBy('id')) }}"
        data-tenants="{{ json_encode($jsTenants) }}"
        data-properties="{{ json_encode($jsProperties) }}"
        data-units="{{ json_encode($jsUnits) }}"
        data-rooms="{{ json_encode($jsRooms) }}">
    </div>

    <script>
        const serverDataEl = document.getElementById('server-data');
        const allFeeTypes = window.allFeeTypes = JSON.parse(serverDataEl.getAttribute('data-all-fee-types') || '[]');
        const createUrl = serverDataEl.getAttribute('data-create-url');
        const rentFeeTypes = JSON.parse(serverDataEl.getAttribute('data-rent-fees') || '[]');
        const depositFeeTypes = JSON.parse(serverDataEl.getAttribute('data-deposit-fees') || '[]');
        const allLeases = window.allLeases = JSON.parse(serverDataEl.getAttribute('data-all-leases') || '{}');
        window.leasePreviewData = JSON.parse(serverDataEl.getAttribute('data-preview-data') || '{}');
        
        const allTenants = JSON.parse(serverDataEl.getAttribute('data-tenants') || '{}');
        const allProperties = JSON.parse(serverDataEl.getAttribute('data-properties') || '{}');
        const allUnits = JSON.parse(serverDataEl.getAttribute('data-units') || '{}');
        const allRooms = JSON.parse(serverDataEl.getAttribute('data-rooms') || '{}');
        window.leasePreviewRequests = window.leasePreviewRequests || {};

        const templatesJsonEl = document.getElementById('templates-json');
        const allTemplates = templatesJsonEl ? JSON.parse(templatesJsonEl.textContent) : {};
        const refundTemplatesJsonEl = document.getElementById('refund-templates-json');
        const allRefundTemplates = refundTemplatesJsonEl ? JSON.parse(refundTemplatesJsonEl.textContent) : {};

        let chargeIndex = 1;

        function addChargeRow() {
            const container = document.getElementById('charges-container');
            const selectHtml = document.getElementById('charge-type-select-template').innerHTML
                .replaceAll('__CHARGE_INDEX__', String(chargeIndex));

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
                        ${selectHtml}
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
                'checkout_refund_section': newStatus === 'Check Out',
                'check_out_section': newStatus === 'Check Out',
                'agreement_end_section': newStatus === 'End Agreement',
                'agreement_template_section': ['New', 'Renew'].includes(newStatus)
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
            async function generatePreviewContent(selectElement, templateCollection) {
                if (!selectElement || !selectElement.value) {
                    alert("⚠️ Please select a template first.");
                    return;
                }

                const selectedTemplateId = String(selectElement.value);
                const templateData = templateCollection[selectedTemplateId];

                if (!templateData || !templateData.content || templateData.content.trim() === '') {
                    alert("⚠ The selected template has no content!");
                    return null;
                }

                const isRefundTemplate = templateCollection === allRefundTemplates;
                const form = selectElement.closest('form');
                const formData = new FormData(form);
                const selectedLeaseId = String(formData.get('lease_id') || '');
                const selectedLeaseData = selectedLeaseId
                    ? window.leasePreviewData?.[selectedLeaseId]
                    : null;
                const ownerId = selectedLeaseData?.owner_data?.id
                    || String(formData.get('owner_id') || '');

                formData.set('template_id', selectedTemplateId);
                formData.set('category', isRefundTemplate ? 'refund' : 'agreement');
                formData.set('owner_id', ownerId);
                if (selectedLeaseId) {
                    formData.set('lease_id', selectedLeaseId);
                }

                const checkoutDate = document.getElementById('check-out-date')?.value || '';
                if (checkoutDate || isRefundTemplate) {
                    formData.set('check_out_date', checkoutDate);
                }

                if (isRefundTemplate) {
                    const refundSection = document.getElementById('checkout_refund_section');
                    const checkoutData = refundSection && window.Alpine
                        ? window.Alpine.$data(refundSection)
                        : null;
                    const refundContext = {
                        wallet_refund_cents: Math.round(Number(checkoutData?.walletRefundAmount || 0) * 100),
                        total_deposit_collected_cents: Number(
                            selectedLeaseData?.total_deposit_collected_cents || 0
                        ),
                        auto_deduction_total_cents: Number(
                            selectedLeaseData?.auto_deduction_total_cents || 0
                        ),
                        unpaid_invoices: selectedLeaseData?.unpaid_invoices || [],
                        active_refund: {
                            items: (checkoutData?.manualDeductions || []).map(item => ({
                                description: item.description
                                    || checkoutData.availableFeeTypes?.find(feeType =>
                                        String(feeType.id) === String(item.fee_type_id)
                                    )?.name
                                    || '',
                                type: item.type || 'adjustment',
                                qty: Number(item.qty || 1),
                                amount_cents: Math.round(
                                    Number(item.qty || 1) * Number(item.sell_price || 0) * 100
                                ),
                            })).filter(item => item.amount_cents > 0),
                        },
                    };
                    formData.set('refund_context', JSON.stringify(refundContext));
                }

                console.groupCollapsed('[Lease Preview Debug]');
                console.log('Selected template:', {
                    id: selectedTemplateId,
                    category: isRefundTemplate ? 'refund' : 'agreement',
                });
                console.log('Selected lease ID:', selectedLeaseId);
                console.log('Selected lease preview data:', selectedLeaseData);
                console.log('Lease inputs in DOM:', Array.from(
                    document.querySelectorAll('input[name="lease_id"]')
                ).map(input => ({
                    id: input.id,
                    value: input.value,
                    disabled: input.disabled,
                })));
                console.log('Preview FormData:', Array.from(formData.entries()).map(([key, value]) => [
                    key,
                    value instanceof File ? `[File: ${value.name}]` : value,
                ]));
                console.log('Template variable names:', Array.from(
                    templateData.content.matchAll(/data-variable=["']([^"']+)["']|\x7B\x7B\s*([a-zA-Z0-9_]+)\s*\x7D\x7D/g)
                ).map(match => match[1] || match[2]));
                console.groupEnd();

                const response = await fetch('{{ route('admin.leases.preview-template') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                if (!response.ok) {
                    const error = await response.json().catch(() => ({}));
                    throw new Error(error.message || 'Unable to generate preview.');
                }

                const rendered = await response.json();
                console.log('[Lease Preview Debug] Backend response:', rendered);
                return { content: rendered.content, title: rendered.title };
            }

            function showPreview(result) {
                if (!result) return;

                const modalContent = document.getElementById('modal-content');
                const modalTitle = document.getElementById('modal-title');
                
                if (modalTitle) modalTitle.innerText = "Preview: " + result.title;
                
                if (modalContent) {
                    const parsedTemplate = new DOMParser().parseFromString(result.content, 'text/html');
                    const templateStyles = Array.from(parsedTemplate.querySelectorAll('style'))
                        .map(style => style.textContent || '')
                        .join('\n');
                    const templateHtml = parsedTemplate.body.innerHTML;

                    modalContent.innerHTML = `
                        <style>${templateStyles}</style>
                        <div class="grapes-preview-box">${templateHtml}</div>
                    `;
                }

                document.body.style.overflow = 'hidden';
                window.dispatchEvent(new CustomEvent('open-preview-modal', {
                    detail: { title: result.title, content: result.content }
                }));
            }

            async function handlePreviewClick(event, selectElement, templateCollection) {
                event.preventDefault();
                event.stopPropagation();

                const button = event.target.closest('#preview-btn, #preview-refund-template-btn');
                const spinner = button?.querySelector('.preview-loading-spinner');

                if (button) {
                    button.disabled = true;
                    button.classList.add('opacity-60', 'cursor-wait');
                }
                spinner?.classList.remove('hidden');

                try {
                    showPreview(await generatePreviewContent(selectElement, templateCollection));
                } catch (error) {
                    console.error("🚨 Preview Error: ", error);
                    alert("Something went wrong while generating the preview: " + error.message);
                } finally {
                    spinner?.classList.add('hidden');
                    if (button) {
                        button.disabled = false;
                        button.classList.remove('opacity-60', 'cursor-wait');
                    }
                }
            }

            document.addEventListener('click', function(event) {
                const previewButton = event.target.closest('#preview-btn');
                const refundPreviewButton = event.target.closest('#preview-refund-template-btn');

                if (previewButton) {
                    handlePreviewClick(event, document.getElementById('document_id'), allTemplates);
                } else if (refundPreviewButton) {
                    handlePreviewClick(event, document.getElementById('refund_document_id'), allRefundTemplates);
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
                    ownerId = window.leasePreviewData?.[leaseSelect.value]?.owner_data?.id || null;
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

            const agreementComponent = agreementSelect.closest('[x-data]');
            const agreementData = agreementComponent && window.Alpine
                ? window.Alpine.$data(agreementComponent)
                : null;

            if (!agreementData || !Array.isArray(agreementData.options)) return;

            agreementData.options = agreementData.options.filter(option => {
                const template = allTemplates[String(option.value)];
                const templateUserId = template?.user_id;

                return !ownerId
                    || String(templateUserId) === String(ownerId)
                    || String(templateUserId) === String(currentUserId);
            });

            if (agreementSelect.value && !agreementData.options.some(option => String(option.value) === String(agreementSelect.value))) {
                agreementSelect.value = "";
            }
        }

        function applyStoredCheckoutFields(leaseData) {
            if (!leaseData) return;

            const checkoutDateInput = document.getElementById('check-out-date');
            const storedCheckoutDate = leaseData.checked_out_at || '';

            if (checkoutDateInput && storedCheckoutDate && !checkoutDateInput.value) {
                if (checkoutDateInput._flatpickr) {
                    checkoutDateInput._flatpickr.setDate(storedCheckoutDate, true);
                } else {
                    checkoutDateInput.value = storedCheckoutDate;
                    checkoutDateInput.dispatchEvent(new Event('input', { bubbles: true }));
                    checkoutDateInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            const storedRefundDocumentId = leaseData.refund_document_id
                ? String(leaseData.refund_document_id)
                : '';
            const refundDocumentInput = document.getElementById('refund_document_id');

            if (storedRefundDocumentId && refundDocumentInput && !refundDocumentInput.value) {
                const refundDocumentComponent = refundDocumentInput.closest('[x-data]');
                const refundDocumentData = refundDocumentComponent && window.Alpine
                    ? window.Alpine.$data(refundDocumentComponent)
                    : null;

                if (refundDocumentData) {
                    refundDocumentData.selectedValues = [storedRefundDocumentId];
                }
            }
        }

        async function handleLeaseChange(eventOrId) {
            const actionRoot = document.getElementById('lease-create-modal-root');
            const modalData = actionRoot && window.Alpine
                ? window.Alpine.$data(actionRoot)
                : null;
            if (!modalData || modalData.action !== 'checkout') return;

            let leaseId = eventOrId;
            if (typeof eventOrId === 'object') {
                leaseId = eventOrId.detail?.value || eventOrId.target?.value || eventOrId.value || null;
            }
            if (!leaseId) {
                const leaseSelectInput = document.getElementById('lease_id');
                if (leaseSelectInput) leaseId = leaseSelectInput.value;
            }

            if (!leaseId) return;

            const loadSequence = ++modalData.refundLoadSequence;
            const isCurrentLoad = () => modalData.refundLoadSequence === loadSequence
                && String(modalData.formLeaseId) === String(leaseId)
                && modalData.action === 'checkout';
            modalData.refundLoading = true;
            modalData.refundReady = false;
            modalData.refundLoadError = '';

            try {

            const initialLeaseData = window.leasePreviewData[leaseId];
            const initialBankDetails = initialLeaseData?.tenant_bank_details || [];
            const initialRefundSection = document.getElementById('checkout_refund_section');
            const initialCheckoutData = initialRefundSection && window.Alpine
                ? window.Alpine.$data(initialRefundSection)
                : null;

            if (initialCheckoutData && initialBankDetails.length) {
                initialCheckoutData.tenantBankDetails = initialBankDetails;
                initialCheckoutData.selectedBankId = initialBankDetails.find(bank => bank.is_default)?.id
                    || initialBankDetails[0]?.id
                    || '';
            }

            if (modalData.refundForceRefresh || !window.leasePreviewData[leaseId]?.unpaid_invoices) {
                let previewRequest = window.leasePreviewRequests[leaseId];
                if (!previewRequest) {
                    previewRequest = fetch(
                        `{{ url('admin/leases') }}/${encodeURIComponent(leaseId)}/checkout-preview`,
                        { headers: { Accept: 'application/json' } }
                    ).then(async response => {
                        if (!response.ok) {
                            throw new Error('Unable to load checkout refund details.');
                        }
                        return response.json();
                    });
                    window.leasePreviewRequests[leaseId] = previewRequest;
                }

                let loadedLeaseData;
                try {
                    loadedLeaseData = await previewRequest;
                } finally {
                    delete window.leasePreviewRequests[leaseId];
                }

                if (!isCurrentLoad()) return;
                if (!Array.isArray(loadedLeaseData?.unpaid_invoices) || !Object.hasOwn(loadedLeaseData, 'active_refund')) {
                    throw new Error('Incomplete checkout preview response.');
                }

                console.group('[Checkout Refund] Backend tenant bank details');
                console.log('Lease ID:', leaseId);
                console.log('Full checkout preview response:', loadedLeaseData);
                console.log('Tenant bank details:', loadedLeaseData.tenant_bank_details || []);
                console.groupEnd();
                window.leasePreviewData[leaseId] = loadedLeaseData;

                const refundSection = document.getElementById('checkout_refund_section');
                if (refundSection && window.Alpine) {
                    const checkoutData = window.Alpine.$data(refundSection);
                    const modalRoot = document.getElementById('lease-create-modal-root');
                    const rootData = modalRoot ? window.Alpine.$data(modalRoot) : null;
                    if (checkoutData) {
                        checkoutData.refundData = {
                            ...checkoutData.refundData,
                            [leaseId]: loadedLeaseData,
                        };
                        checkoutData.tenantBankDetails = loadedLeaseData.tenant_bank_details || [];
                        checkoutData.selectedBankId = checkoutData.tenantBankDetails.find(bank => bank.is_default)?.id
                            || checkoutData.tenantBankDetails[0]?.id
                            || '';
                    }
                    if (rootData) {
                        rootData.refundData = {
                            ...rootData.refundData,
                            [leaseId]: loadedLeaseData,
                        };
                    }
                }
            }

            const leaseData = window.leasePreviewData[leaseId];
            console.log('[Checkout Refund] Bank details used by frontend:', {
                leaseId,
                tenantBankDetails: leaseData?.tenant_bank_details || [],
            });
            const refundSection = document.getElementById('checkout_refund_section');
            const checkoutData = refundSection && window.Alpine
                ? window.Alpine.$data(refundSection)
                : null;
            const modalRoot = document.getElementById('lease-create-modal-root');
            const rootData = modalRoot && window.Alpine
                ? window.Alpine.$data(modalRoot)
                : null;

            if (checkoutData) {
                checkoutData.refundData = {
                    ...checkoutData.refundData,
                    [leaseId]: leaseData,
                };
                checkoutData.tenantBankDetails = leaseData.tenant_bank_details || [];
                checkoutData.selectedBankId = checkoutData.tenantBankDetails.find(bank => bank.is_default)?.id
                    || checkoutData.tenantBankDetails[0]?.id
                    || '';
            }
            if (rootData) {
                rootData.refundData = {
                    ...rootData.refundData,
                    [leaseId]: leaseData,
                };
            }

            applyStoredCheckoutFields(leaseData);

            // 1. Total Deposit Collected from Backend
            const totalDeposit = (leaseData.total_deposit_collected_cents || 0) / 100;
            const checkoutTotalDepositEl = document.getElementById('checkout-total-deposit');
            if (checkoutTotalDepositEl) {
                checkoutTotalDepositEl.innerText = `RM ${totalDeposit.toFixed(2)}`;
                window.checkoutTotalDepositValue = totalDeposit;
            }

            // 2. Auto-Deductions from Backend
            const autoDeductionsContainer = document.getElementById('auto-deductions-container');
            const autoDeductionsList = document.getElementById('auto-deductions-list');
            let autoDeductionSum = (leaseData.auto_deduction_total_cents || 0) / 100;

            if (autoDeductionsList && autoDeductionsContainer) {
                autoDeductionsList.innerHTML = '';
                const unpaidInvoices = leaseData.unpaid_invoices || [];

                if (unpaidInvoices.length > 0) {
                    autoDeductionsContainer.classList.remove('hidden');
                    let htmlContent = '';
                    unpaidInvoices.forEach((inv, idx) => {
                        htmlContent += `
                            <div class="flex justify-between items-center bg-white p-2 rounded border border-gray-200">
                                <span>${inv.invoice_no} (${inv.description})</span>
                                <span class="font-semibold text-red-600">RM ${inv.balance_formatted}</span>
                                <input type="hidden" name="auto_deductions[${idx}][invoice_id]" value="${inv.id}">
                                <input type="hidden" name="auto_deductions[${idx}][amount]" value="${inv.balance_formatted}">
                            </div>
                        `;
                    });
                    autoDeductionsList.innerHTML = htmlContent;
                } else {
                    autoDeductionsContainer.classList.add('hidden');
                }
            }

            // Push auto deduction total to Alpine reactive state
            window.autoDeductionTotal = autoDeductionSum;
            const refundSectionEl = document.getElementById('checkout_refund_section');
            if (refundSectionEl && window.Alpine) {
                let alpineData = window.Alpine.$data(refundSectionEl);
                if (alpineData) {
                    alpineData.autoDeductionTotal = autoDeductionSum;
                    alpineData.walletRefundAmount = Number(leaseData.wallet_refund_cents || 0) / 100;

                    // 🌟 3. POPULATE SAVED MANUAL DEDUCTIONS IF A DRAFT REFUND EXISTS
                    if (leaseData.active_refund && leaseData.active_refund.items) {
                        const customItems = leaseData.active_refund.items.filter(item => {
                            return item.type !== 'utility' || !item.description?.includes('Unpaid Invoice');
                        });

                        alpineData.manualDeductions = customItems.map(item => {
                            const amountCents = item.amount_cents || 0;
                            const qty = item.qty || 1; // 🌟 Read the actual quantity from the database item
                            return {
                                fee_type_id: item.fee_type_id || '',
                                qty: qty,                  // 🌟 Assign the real quantity
                                sell_price: (amountCents / 100) / qty // Compute unit price correctly
                            };
                        });
                    } else {
                        alpineData.manualDeductions = [];
                    }
                }
            }

            const selectedLease = allLeases[leaseId];
            if (typeof filterTemplates === 'function' && selectedLease) {
                filterTemplates(selectedLease.owner_id || null);
            }
            if (isCurrentLoad()) {
                modalData.refundReady = true;
                modalData.refundForceRefresh = false;
            }
            } catch (error) {
                if (isCurrentLoad()) {
                    modalData.refundReady = false;
                    modalData.refundLoadError = 'The settlement could not be retrieved. Please try again.';
                }
                console.error('[Checkout Refund] Preview failed:', error);
            } finally {
                if (isCurrentLoad()) modalData.refundLoading = false;
            }
        }
    </script>
@endpush
