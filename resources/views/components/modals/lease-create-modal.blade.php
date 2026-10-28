@props(['tenants', 'leases', 'properties', 'units', 'rooms', 'templates', 'refundTemplates','rentFeeTypes', 'serviceFeeTypes', 'depositFeeTypes', 'managementFeeTypes', 'itemFeeTypes', 'leasePreviewData'])

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

        get refundStatus() {
            if (!this.formLeaseId || !window.leasePreviewData) return 'none';
            const preview = window.leasePreviewData[this.formLeaseId];
            return preview?.active_refund?.status || 'none';
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

                            <x-preview-agreement-modal />
                            
                            <!-- CHECKOUT REFUND SECTION -->
                            <div id="checkout_refund_section" class="mt-4 hidden space-y-4"
                            x-data="{
                                manualDeductions: [],
                                availableFeeTypes: allFeeTypes,
                                checkoutDate: '',
                                isEarlyCheckout: false,
                                earlyDays: 0,
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
                                    const preview = window.leasePreviewData[this.formLeaseId];
                                    return preview?.active_refund || null;
                                },
                                get refundStatus() {
                                    if (!this.formLeaseId || !window.leasePreviewData) return 'none';
                                    const preview = window.leasePreviewData[this.formLeaseId];
                                    // Handle both string and Enum object serialization safely
                                    const status = preview?.active_refund?.status;
                                    if (!status) return 'none';
                                    return typeof status === 'object' ? (status.value || 'none') : String(status).toLowerCase();
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
                                        <button type="button" id="preview-refund-template-btn" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors uppercase tracking-wider">
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
                                            const map = { 'none': 1, 'draft': 2, 'pending': 3, 'approved': 4, 'paid': 5 };
                                            return map[this.refundStatus] || 1;
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
                                            <button type="button" @click="$dispatch('open-fee-type-modal', { category: 'item' })" class="inline-flex items-center text-xs font-bold text-gray-600 hover:text-indigo-600 transition-colors">
                                                + Add Fee Type
                                            </button>
                                            <button type="button" @click="addDeductionRow()" class="inline-flex items-center text-xs font-bold text-indigo-600 hover:text-indigo-700 transition-colors">
                                                + Add Deduction Item
                                            </button>
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
                                                        <button type="button" 
                                                                x-show="['none', 'draft'].includes(refundStatus)"
                                                                @click="manualDeductions.splice(index, 1)"
                                                                class="text-slate-400 hover:text-red-600 p-1 rounded transition"
                                                                title="Remove item">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                            </svg>
                                                        </button>

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
                                                <span id="checkout-net-refund" class="text-indigo-600 text-lg font-bold" x-text="'RM ' + Math.max(0, (parseFloat(window.checkoutTotalDepositValue || 0) - (autoDeductionTotal + calculateManualTotal()))).toFixed(2)">RM 0.00</span>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>

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

                        {{-- Dynamic Footer Buttons based on Refund Lifecycle (Fixed / Sticky to Modal Bottom) --}}
                        <div class="sticky bottom-0 z-30 px-8 py-4 bg-white/95 backdrop-blur-sm border-t border-slate-200 shadow-lg flex items-center justify-between gap-3 w-full"
                            x-data="{
                                get activeRefund() {
                                    if (!this.formLeaseId || !window.leasePreviewData) return null;
                                    const preview = window.leasePreviewData[this.formLeaseId];
                                    return preview?.active_refund || null;
                                },
                                get currentRefundId() {
                                    return this.activeRefund?.id || '';
                                }
                            }">
                            
                            {{-- Hidden input to pass active refund ID to controller when clicking approve/disburse --}}
                            <input type="hidden" name="refund_id" :value="currentRefundId">

                            {{-- Left Side / Secondary Actions if needed (or keep empty to push buttons right) --}}
                            <div></div>

                            <div class="flex items-center gap-3">
                                <button type="button" @click="open = false" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-colors">
                                    Cancel
                                </button>
                                
                                <template x-if="action === 'checkout'">
                                    <div class="flex items-center gap-2" x-data="{ 
                                        subAction: '',
                                        submitCheckout(routeUrl, actionName) {
                                            this.subAction = actionName;
                                            const form = $el.closest('form');
                                            form.action = routeUrl;
                                            form.submit();
                                        }
                                    }">
                                        
                                        {{-- STEP 1 & 2: Save as Draft or Direct Confirm & Set as Pending --}}
                                        <template x-if="refundStatus === 'none' || refundStatus === 'draft'">
                                            <div class="flex items-center gap-2">
                                                {{-- Save as Draft Button --}}
                                                <x-form.secondary-button 
                                                    type="button" 
                                                    loading="subAction === 'draft'" 
                                                    @click="submitCheckout('{{ route('admin.refunds.store-draft') }}', 'draft')"
                                                    class="px-4 py-2.5">
                                                    <span>Save as Draft</span>
                                                </x-form.secondary-button>

                                                {{-- Confirm & Set as Pending Button (Direct Action) --}}
                                                <x-form.primary-button 
                                                    type="button" 
                                                    loading="subAction === 'pending'" 
                                                    @click="submitCheckout('{{ route('admin.refunds.submit-pending') }}', 'pending')"
                                                    class="px-4 py-2.5 bg-amber-600 hover:bg-amber-700">
                                                    <span>Confirm & Set as Pending</span>
                                                </x-form.primary-button>
                                            </div>
                                        </template>

                                        {{-- STEP 3: PENDING -> Approve or Reject Refund --}}
                                        <template x-if="refundStatus === 'pending'">
                                            <div class="flex items-center gap-2">
                                                {{-- Reject / Send Back Button --}}
                                                <x-form.secondary-button 
                                                    loading="subAction === 'reject'" 
                                                    @click="submitCheckout('{{ route('admin.refunds.reject') }}', 'reject')"
                                                    class="!bg-red-600 !text-white !border-red-600 hover:!bg-red-700 focus:!ring-red-500">
                                                    Reject / Send Back
                                                </x-form.secondary-button>

                                                {{-- Approve Button --}}
                                                <x-form.primary-button 
                                                    type="button" 
                                                    loading="subAction === 'approve'" 
                                                    @click="submitCheckout('{{ route('admin.refunds.approve') }}', 'approve')"
                                                    class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700">
                                                    <span>Approve Refund</span>
                                                </x-form.primary-button>
                                            </div>
                                        </template>

                                        {{-- STEP 4: APPROVED -> Proceed to Final Refund Payout --}}
                                        <template x-if="refundStatus === 'approved'">
                                            <x-form.primary-button 
                                                type="button" 
                                                loading="subAction === 'disburse'" 
                                                @click="submitCheckout('{{ route('admin.refunds.disburse') }}', 'disburse')"
                                                class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700">
                                                <span>Proceed to Refund</span>
                                            </x-form.primary-button>
                                        </template>

                                        {{-- STEP 5: PAID / REFUNDED (Finished State) --}}
                                        <template x-if="refundStatus === 'paid'">
                                            <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-3 py-2 rounded-lg border border-emerald-200">
                                                ✓ Refund Completed
                                            </span>
                                        </template>

                                    </div>
                                </template>

                                <template x-if="action !== 'checkout'">
                                    <x-form.primary-button type="submit" loading="loading" class="px-5 py-2.5">
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
        window.leasePreviewData = JSON.parse(serverDataEl.getAttribute('data-preview-data') || '{}');
        
        const allTenants = JSON.parse(serverDataEl.getAttribute('data-tenants') || '{}');
        const allProperties = JSON.parse(serverDataEl.getAttribute('data-properties') || '{}');
        const allUnits = JSON.parse(serverDataEl.getAttribute('data-units') || '{}');
        const allRooms = JSON.parse(serverDataEl.getAttribute('data-rooms') || '{}');

        const templatesJsonEl = document.getElementById('templates-json');
        const allTemplates = templatesJsonEl ? JSON.parse(templatesJsonEl.textContent) : {};
        const refundTemplatesJsonEl = document.getElementById('refund-templates-json');
        const allRefundTemplates = refundTemplatesJsonEl ? JSON.parse(refundTemplatesJsonEl.textContent) : {};

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
            function generatePreviewContent(selectElement, templateCollection) {
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

                let content = templateData.content;
                const title = templateData.title;
                const isRefundTemplate = templateCollection === allRefundTemplates;
                const selectedLeaseId = document.getElementById('lease_id')?.value || '';
                const selectedLeaseData = selectedLeaseId
                    ? window.leasePreviewData?.[selectedLeaseId]
                    : null;
                const activeRefund = selectedLeaseData?.active_refund || {};
                const refundItems = Array.isArray(activeRefund.items) ? activeRefund.items : [];
                const unpaidInvoiceItems = Array.isArray(selectedLeaseData?.unpaid_invoices)
                    ? selectedLeaseData.unpaid_invoices.map(invoice => ({
                        description: invoice.description || `Unpaid Invoice ${invoice.invoice_no || ''}`.trim(),
                        type: 'utility',
                        qty: 1,
                        amount_cents: Number(invoice.balance_cents || 0)
                    }))
                    : [];
                const dynamicRefundItems = refundItems.length ? refundItems : unpaidInvoiceItems;
                const autoDeductionCents = Number(selectedLeaseData?.auto_deduction_total_cents || 0);
                const totalDepositCents = Number(selectedLeaseData?.total_deposit_collected_cents || 0);
                const customRefundItems = refundItems.filter(item => {
                    const type = typeof item.type === 'object' ? item.type.value : item.type;
                    return type !== 'utility' || !String(item.description || '').includes('Unpaid Invoice');
                });
                const customDeductionCents = customRefundItems.reduce(
                    (total, item) => total + Number(item.amount_cents || 0),
                    0
                );
                const nettRefundCents = Number(
                    activeRefund.net_amount_cents
                    ?? (totalDepositCents - autoDeductionCents - customDeductionCents)
                );
                const formatAmount = cents => (Number(cents || 0) / 100).toFixed(2);
                const escapeHtml = value => String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
                const itemDescription = item => escapeHtml(item.description || item.fee_type?.name || 'Refund deduction');
                const itemType = item => {
                    const type = typeof item.type === 'object' ? item.type.value : item.type;
                    return escapeHtml(String(type || 'adjustment').replace(/_/g, ' '));
                };
                const dynamicRefundRows = dynamicRefundItems.length
                    ? dynamicRefundItems.map((item, index) => `
                        <tr>
                            <td style="border: 1px solid #cbd5e1; padding: 10px;">${index + 1}</td>
                            <td style="border: 1px solid #cbd5e1; padding: 10px;">
                                ${itemDescription(item)} <span style="color: #64748b;">(${itemType(item)})</span>
                            </td>
                            <td style="border: 1px solid #cbd5e1; padding: 10px; text-align: center;">${escapeHtml(item.qty || 1)}</td>
                            <td style="border: 1px solid #cbd5e1; padding: 10px; text-align: right;">RM ${formatAmount(item.amount_cents)}</td>
                        </tr>
                    `).join('')
                    : `<tr>
                        <td colspan="4" style="border: 1px dashed #bbf7d0; padding: 20px; text-align: center; color: #166534; font-weight: bold;">
                            No refund deduction items
                        </td>
                    </tr>`;

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

                if (isRefundTemplate) {
                    replacements['{checkout_date}'] = document.getElementById('check-out-date')?.value || 'N/A';
                    replacements['{total_deposit_collected}'] = formatAmount(totalDepositCents);
                    replacements['{auto_deductions_total}'] = formatAmount(autoDeductionCents);
                    replacements['{custom_deductions_total}'] = formatAmount(customDeductionCents);
                    replacements['{nett_refund_amount}'] = formatAmount(nettRefundCents);
                    replacements['{unpaid_invoices_breakdown}'] = formatAmount(autoDeductionCents);
                    replacements['{custom_items_breakdown}'] = formatAmount(customDeductionCents);
                }

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

                        if (previewData) {
                            replacements['{property_name}'] = previewData.leasable_name || 'N/A';
                            replacements['{property_address}'] = previewData.leasable_address || 'N/A';
                            replacements['{owner_name}'] = previewData.owner_data?.name || 'N/A';
                            replacements['{owner_ic}'] = previewData.owner_data?.ic_number || 'N/A';
                            replacements['{owner_id}'] = previewData.owner_data?.id || 'N/A';
                            replacements['{property_type}'] = (previewData.leasable_type_label || previewData.leasable_type || '').toUpperCase();
                            replacements['{tenant_name}'] = previewData.tenant_data?.name || previewData.tenant_name || 'N/A';
                            replacements['{tenant_ic}'] = previewData.tenant_data?.ic_number || previewData.tenant_ic || 'N/A';
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

                if (isRefundTemplate) {
                    content = content.replace(
                        /<tbody\b[^>]*id=["']dynamic-refund-tbody["'][^>]*>[\s\S]*?<\/tbody>/i,
                        `<tbody id="dynamic-refund-tbody">${dynamicRefundRows}</tbody>`
                    );
                }

                return { content, title };
            }

            function showPreview(result) {
                if (!result) return;

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

                document.body.style.overflow = 'hidden';
                window.dispatchEvent(new CustomEvent('open-preview-modal', {
                    detail: { title: result.title, content: result.content }
                }));
            }

            function handlePreviewClick(event, selectElement, templateCollection) {
                event.preventDefault();
                event.stopPropagation();

                try {
                    showPreview(generatePreviewContent(selectElement, templateCollection));
                } catch (error) {
                    console.error("🚨 Preview Error: ", error);
                    alert("Something went wrong while generating the preview: " + error.message);
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

        function handleLeaseChange(eventOrId) {
            let leaseId = eventOrId;
            if (typeof eventOrId === 'object') {
                leaseId = eventOrId.detail?.value || eventOrId.target?.value || eventOrId.value || null;
            }
            if (!leaseId) {
                const leaseSelectInput = document.getElementById('lease_id');
                if (leaseSelectInput) leaseId = leaseSelectInput.value;
            }

            if (!leaseId || !window.leasePreviewData || !window.leasePreviewData[leaseId]) return;

            const leaseData = window.leasePreviewData[leaseId];

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
        }
    </script>
@endpush
