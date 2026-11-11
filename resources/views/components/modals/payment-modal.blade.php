{{-- components/payment-modal.blade.php --}}
@php
    $processingFeeSettings = app(\App\Services\SettingService::class)->getEffectiveSettings();
    $processingFeeConfig = [
        'enabled' => filter_var(data_get($processingFeeSettings, 'processing_fee_config.is_active', false), FILTER_VALIDATE_BOOLEAN),
        'amount' => (float) data_get($processingFeeSettings, 'processing_fee_config.value.processing amount', 0),
        'methods' => data_get($processingFeeSettings, 'processing_fee_config.value.applicable_payment_method', []),
    ];
@endphp
<template x-teleport="body">
    <div x-show="openPayment" 
        x-cloak
        @open-payment.window="
            openPayment = true; 
            paymentError = '';
            paymentData = $event.detail;
            walletBalance = parseFloat(String(paymentData.walletBalance || '0').replace(/,/g, '')) || 0;
            useWallet = false;
            method = 'Cash';
            previousMethod = 'Cash';
            $el.querySelectorAll('[name=transaction_ref]:not([type=hidden])').forEach(input => input.value = '');
            penaltyCancelled = false;
            overridePenaltyAmount = null;
            overrideProcessingFeeAmount = null;
            processingFeeCancelled = false;
            if (paymentData.invoiceItems) {
                selectedItems = paymentData.invoiceItems.map((_, i) => i);
                payFull = true;
            }
            form.payment_date = '{{ date('Y-m-d') }}';
            form.due_date = paymentData.dueDate || '';
            form.amount_balance = parseFloat(String(paymentData.totalAmount || '0').replace(/,/g, '')) || 0;
            updatePenalty();
        "

        x-data="{
            method: 'Cash',
            previousMethod: 'Cash',
            submittingPayment: false,
            paymentError: '',
            selectedItems: [],
            payFull: true,
            useWallet: false,
            walletBalance: 0,
            penaltyLoading: false,
            penaltyError: '',
            penaltySequence: 0,

            dynamicPenalty: null,
            penaltyCancelled: false,
            overridePenaltyAmount: null,
            processingFeeConfig: @js($processingFeeConfig),
            overrideProcessingFeeAmount: null,
            processingFeeCancelled: false,

            form: {
                payment_date: '',
                due_date: '',
                amount_balance: 0
            },

            async updatePenalty() {
                const sequence = ++this.penaltySequence;
                this.dynamicPenalty = null;
                this.overridePenaltyAmount = null;
                this.penaltyCancelled = false;
                this.penaltyError = '';
                if (!this.paymentData?.id || !this.form.payment_date) {
                    this.penaltyError = 'Select an invoice and payment date to calculate the penalty.';
                    this.penaltyLoading = false;
                    return;
                }

                this.penaltyLoading = true;

                try {
                    let response = await fetch(`{{ route('admin.setting.calculate-penalty', '__ID__') }}`.replace('__ID__', this.paymentData.id), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            payment_date: this.form.payment_date  
                        })
                    });
                    
                    let data = await response.json();
                    if (sequence !== this.penaltySequence) return;
                    if (!response.ok) {
                        const message = data.errors ? Object.values(data.errors).flat().join(' ') : data.message || 'Unable to calculate the penalty.';
                        window.dispatchEvent(new CustomEvent('session-status', { detail: data.session_status || { type: 'error', message } }));
                        throw new Error(message);
                    }
                    this.dynamicPenalty = data; 
                    this.overridePenaltyAmount = data?.amount || data?.totalAmount || 0;
                } catch (error) {
                    if (sequence === this.penaltySequence) this.penaltyError = error.message || 'Unable to calculate the penalty. Please try again.';
                } finally {
                    if (sequence === this.penaltySequence) this.penaltyLoading = false;
                }
            },
            
            get penaltyDetails() {
                if (this.penaltyCancelled || !this.dynamicPenalty || Object.keys(this.dynamicPenalty).length === 0) return null;
                // Allow amount to be 0 or empty for typing flexibility without hiding the row
                let currentAmount = this.overridePenaltyAmount !== null && this.overridePenaltyAmount !== '' ? parseFloat(this.overridePenaltyAmount) || 0 : 0;
                return {
                    ...this.dynamicPenalty,
                    amount: currentAmount
                };
            },
            
            get previouslyPaidAmount() {
                return Number(String(this.paymentData?.previouslyPaid || 0).replace(/,/g, '')) || 0;
            },

            get originalInvoiceAmount() {
                return Number(String(this.paymentData?.invoiceTotal || 0).replace(/,/g, '')) || 0;
            },

            get processingFeeApplicable() {
                if (!this.processingFeeConfig.enabled || this.useWallet
                    || !this.processingFeeConfig.methods.includes(this.method)) return false;

                const amount = Math.round(this.processingFeeConfig.amount * 100) / 100;
                return Number.isFinite(amount) && amount > 0;
            },

            get processingFeeInputAmount() {
                return this.overrideProcessingFeeAmount === null
                    ? this.processingFeeConfig.amount
                    : this.overrideProcessingFeeAmount;
            },

            set processingFeeInputAmount(value) {
                this.overrideProcessingFeeAmount = value;
            },

            get processingFeeDetails() {
                if (!this.processingFeeApplicable || this.processingFeeCancelled) return null;

                const amount = Math.max(0, Math.round((parseFloat(this.processingFeeInputAmount) || 0) * 100) / 100);

                return { amount };
            },

            get computedAmount() {
                if (this.useWallet) {
                    return '0.00';
                }

                let rawTotal = String(this.paymentData?.totalAmount || 0).replace(/,/g, '');
                let numericTotal = parseFloat(rawTotal) || 0;
                
                let baseAmount = (this.payFull || !this.paymentData?.invoiceItems || this.paymentData.invoiceItems.length === 0)
                    ? numericTotal
                    : this.selectedItems.reduce((sum, index) => {
                        let itemAmount = String(this.paymentData.invoiceItems[index].amount || 0).replace(/,/g, '');
                        return sum + (parseFloat(itemAmount) || 0);
                    }, 0);

                if (this.penaltyDetails && this.payFull) {
                    baseAmount += parseFloat(this.penaltyDetails.amount) || 0;
                }

                if (this.processingFeeDetails) {
                    baseAmount += this.processingFeeDetails.amount;
                }

                return baseAmount.toFixed(2);
            },

            async submitPayment(event) {
                if (this.submittingPayment || this.penaltyLoading || this.penaltyError) return;
                this.submittingPayment = true;
                this.paymentError = '';
                this.$dispatch('form-loading', true);
                let saved = false;
                try {
                    const body = new FormData(event.target);
                    if (!this.paymentData?.asyncRefresh) body.append('preserve_payment_summary', '1');
                    const response = await fetch(event.target.action, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        const message = data.errors ? Object.values(data.errors).flat().join(' ')
                            : (data.message || 'Payment could not be recorded. Please review the details.');
                        this.openPayment = true;
                        window.dispatchEvent(new CustomEvent('session-status', {
                            detail: data.session_status || { type: 'error', message }
                        }));
                        return;
                    }
                    saved = true;
                    this.openPayment = false;
                    const detail = { ...data, handled: false };
                    window.dispatchEvent(new CustomEvent('payment-recorded', { detail }));
                    if (!detail.handled) {
                        // Other payment screens retain their existing navigation behavior.
                        window.location.reload();
                        return;
                    }
                    if (data.wallet_summary_html) {
                        window.dispatchEvent(new CustomEvent('wallet-payment-summary', { detail: { html: data.wallet_summary_html } }));
                    }
                    window.dispatchEvent(new CustomEvent('session-status', {
                        detail: data.session_status || { type: 'success', message: data.message }
                    }));
                } catch (error) {
                    this.paymentError = saved ? 'Payment was saved. Refresh the page to see the latest records.'
                        : 'The payment status could not be confirmed. Refresh and check the invoice before submitting again.';
                } finally {
                    this.submittingPayment = false;
                    this.$dispatch('form-loading', false);
                }
            },

            handleWalletToggle(event) {
                event.target.form.querySelectorAll('[name=transaction_ref]:not([type=hidden])').forEach(input => input.value = '');
                if (event.target.checked) {
                    if (this.method !== 'Wallet') {
                        this.previousMethod = this.method;
                    }
                    this.method = 'Wallet';
                } else {
                    this.method = this.previousMethod || 'Cash';
                }
            }
        }"
        class="fixed inset-0 z-[100] overflow-y-auto">

        <div class="flex items-center justify-center min-h-screen px-3 py-4 sm:px-6">
            
            {{-- 背景遮罩层 --}}
            <div class="absolute inset-0">
                <x-ui.blur-overlay 
                    show="openPayment" 
                    onClose="shakePayment = true; setTimeout(() => shakePayment = false, 400)" 
                />
            </div>

            {{-- 模态框主体 --}}
            <div class="relative flex flex-col max-h-[calc(100dvh-2rem)] bg-white rounded-2xl shadow-2xl max-w-5xl w-full overflow-hidden transition-all duration-200 z-[102]"
                x-show="openPayment"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                :class="{ 'animate-shake': shakePayment }"
                @click.stop>
                
                <div class="px-5 py-4 sm:px-6 border-b border-slate-200 bg-white flex items-center justify-between gap-4 shrink-0">
                    <div class="min-w-0">
                        <h3 class="text-lg font-bold text-slate-800 m-0">
                            Record Payment
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">Invoice <span class="font-semibold text-indigo-600 break-all" x-text="paymentData.invoiceNo"></span></p>
                    </div>
                    <button type="button" @click="openPayment = false" :disabled="submittingPayment"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-400 hover:bg-slate-100 hover:text-gray-600 text-2xl transition-colors"
                            aria-label="Close record payment">
                        &times;
                    </button>
                </div>
                
                <x-form.form class="flex flex-col flex-1 min-h-0" x-bind:action="paymentData.actionUrl" method="POST" :async="true" @submit.prevent="submitPayment($event)">
                    <input type="hidden" name="penalty_amount" x-bind:value="penaltyDetails ? penaltyDetails.amount : 0">
                    <input type="hidden" name="processing_fee_amount" x-bind:value="processingFeeDetails ? processingFeeDetails.amount : 0">
                    <input type="hidden" name="processing_fee_cancelled" x-bind:value="processingFeeApplicable && processingFeeCancelled ? 1 : 0">

                    <div class="flex flex-col flex-1 min-h-0">
                        <div x-show="penaltyError || paymentError" x-cloak
                             class="mx-5 mt-4 p-3 bg-red-50 border border-red-200 rounded-lg space-y-1 shrink-0">
                            <p x-show="penaltyError" x-text="penaltyError" role="alert" class="text-sm text-red-700"></p>
                            <p x-show="paymentError" x-text="paymentError" role="alert" class="text-sm text-red-700"></p>
                        </div>
                        <!-- Scrollable Form Body -->
                        <div class="overflow-y-auto flex-1 min-h-0 bg-slate-50">
                          <div class="grid items-start lg:grid-cols-2">
                          <div class="min-w-0 p-5 sm:p-6 space-y-4">
                            {{-- Invoice Items & Penalty Breakdown --}}
                            <div class="bg-white p-4 rounded-xl border border-slate-200 space-y-3 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Payment Breakdown</span>
                                    <span class="text-xs font-semibold text-gray-400" x-text="((paymentData.invoiceItems || []).length + (penaltyDetails && Object.keys(penaltyDetails).length > 0 ? 1 : 0) + (processingFeeDetails ? 1 : 0)) + ' Item(s)'"></span>
                                </div>

                                <div class="divide-y divide-slate-100">
                                    {{-- Standard Invoice Items --}}
                                    <template x-for="(item, index) in (paymentData.invoiceItems || [])" :key="index">
                                        <div class="py-3 flex items-start justify-between gap-4 text-sm">
                                            <span class="min-w-0 break-words font-medium text-gray-800 capitalize" x-text="item.name || item.description"></span>
                                            <span class="shrink-0 tabular-nums font-semibold text-gray-900" x-text="'RM ' + (parseFloat(String(item.amount || 0).replace(/,/g, ''))).toFixed(2)"></span>
                                        </div>
                                    </template>

                                    {{-- Reconcile the full invoice items with earlier payments. --}}
                                    <div x-show="previouslyPaidAmount > 0" x-cloak class="border-t border-slate-200 py-3 space-y-2 text-sm" aria-live="polite">
                                        <div class="flex justify-between gap-4">
                                            <span class="text-gray-600">Invoice total</span>
                                            <span class="font-semibold tabular-nums" x-text="'RM ' + originalInvoiceAmount.toFixed(2)"></span>
                                        </div>
                                        <div class="flex justify-between gap-4 text-emerald-700">
                                            <span>Previously paid</span>
                                            <span class="font-semibold tabular-nums" x-text="'− RM ' + previouslyPaidAmount.toFixed(2)"></span>
                                        </div>
                                        <div class="flex justify-between gap-4 border-t border-slate-100 pt-2 font-semibold text-gray-900">
                                            <span>Remaining invoice balance</span>
                                            <span class="tabular-nums" x-text="'RM ' + Number(String(paymentData.totalAmount || 0).replace(/,/g, '')).toFixed(2)"></span>
                                        </div>
                                    </div>

                                    {{-- Dynamic Editable/Cancellable Penalty Row (Only shows when penalty rule/data exists) --}}
                                    <template x-if="penaltyDetails">
                                        <div class="py-2 px-2.5 flex items-center justify-between text-sm bg-amber-50/80 border border-amber-200/60 rounded-lg my-1 gap-2">
                                            <div class="flex flex-col flex-1 min-w-0">
                                                <span class="font-semibold text-amber-900 truncate" x-text="dynamicPenalty.title"></span>
                                                <span class="text-xs text-amber-700/80 truncate" x-text="'Calculation: ' + dynamicPenalty?.calculation"></span>
                                            </div>
                                            
                                            <div class="flex items-center space-x-2 shrink-0">
                                                <div class="flex items-center space-x-1">
                                                    <span class="text-xs font-bold text-amber-900">+ RM</span>
                                                    <input type="number" step="0.01" 
                                                        x-model.number="overridePenaltyAmount"
                                                        class="w-24 px-2 py-1 text-sm font-bold text-amber-900 bg-white border border-amber-300 rounded-md focus:ring-1 focus:ring-amber-500 focus:outline-none text-right @canany(['super-admin', 'invoice.edit penalty']) @else bg-gray-100 text-gray-500 cursor-not-allowed opacity-75 @endcanany"
                                                        @wheel="$event.preventDefault()"
                                                        @canany(['super-admin', 'invoice.edit penalty']) @else readonly @endcanany />
                                                </div>
                                                
                                                @canany(['super-admin', 'invoice.remove penalty'])
                                                <button type="button" @click="penaltyCancelled = true" class="text-amber-700 hover:text-red-600 p-1 transition-colors" title="Cancel/Remove Penalty">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                                @endcanany
                                            </div>
                                        </div>
                                    </template>

                                    {{-- Processing fee follows the configured external payment methods. --}}
                                    <template x-if="processingFeeDetails">
                                        <div class="py-2 px-2.5 flex items-center justify-between text-sm bg-amber-50/80 border border-amber-200/60 rounded-lg my-1 gap-2">
                                            <div class="flex flex-col flex-1 min-w-0">
                                                <span class="font-semibold text-amber-900 truncate">{{ __('Processing Fee') }}</span>
                                                <span class="text-xs text-amber-700/80 truncate" x-text="'Payment method: ' + method"></span>
                                            </div>
                                            <div class="flex items-center space-x-2 shrink-0">
                                                <div class="flex items-center space-x-1">
                                                    <span class="text-xs font-bold text-amber-900">+ RM</span>
                                                    <input type="number" step="0.01" min="0" aria-label="{{ __('Processing Fee Amount') }}"
                                                        x-model.number="processingFeeInputAmount"
                                                        class="w-24 px-2 py-1 text-sm font-bold text-amber-900 bg-white border border-amber-300 rounded-md focus:ring-1 focus:ring-amber-500 focus:outline-none text-right @canany(['super-admin', 'invoice.edit processing fee']) @else bg-gray-100 text-gray-500 cursor-not-allowed opacity-75 @endcanany"
                                                        @wheel="$event.preventDefault()"
                                                        @canany(['super-admin', 'invoice.edit processing fee']) @else readonly @endcanany />
                                                </div>
                                                @canany(['super-admin', 'invoice.remove processing fee'])
                                                <button type="button" @click="processingFeeCancelled = true" class="text-amber-700 hover:text-red-600 p-1 transition-colors" title="{{ __('Cancel/Remove Processing Fee') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                                @endcanany
                                            </div>
                                        </div>
                                    </template>
                                    @canany(['super-admin', 'invoice.remove processing fee'])
                                    <template x-if="processingFeeCancelled && processingFeeApplicable">
                                        <div class="py-1.5 px-2.5 flex items-center justify-between text-xs bg-gray-100 border border-gray-200 rounded-lg my-1">
                                            <span class="text-gray-500 italic">{{ __('Processing fee cancelled / removed') }}</span>
                                            <button type="button" @click="processingFeeCancelled = false" class="text-indigo-600 font-semibold hover:underline">{{ __('Restore') }}</button>
                                        </div>
                                    </template>
                                    @endcanany

                                    {{-- Cancelled Penalty State with Restore Option (Only shows if a penalty rule actually existed) --}}
                                    <template x-if="penaltyCancelled && dynamicPenalty && Object.keys(dynamicPenalty).length > 0">
                                        <div class="py-1.5 px-2.5 flex items-center justify-between text-xs bg-gray-100 border border-gray-200 rounded-lg my-1">
                                            <span class="text-gray-500 italic">Penalty cancelled / removed</span>
                                            <button type="button" @click="penaltyCancelled = false" class="text-indigo-600 font-semibold hover:underline">Restore</button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Wallet Balance Option --}}
                            <div x-show="walletBalance > 0" class="p-3.5 bg-indigo-50 rounded-xl border border-indigo-100" x-cloak>
                              <div class="flex items-center justify-between gap-3">
                                <div>
                                    <span class="text-xs font-bold text-indigo-900 uppercase tracking-wider block">Tenant Wallet Available</span>
                                    <span class="text-sm font-semibold text-indigo-700" x-text="'RM ' + walletBalance.toFixed(2)"></span>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="use_wallet" x-model="useWallet" value="1" 
                                        @change="handleWalletToggle($event)"
                                        class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                    <span class="ml-2 text-xs font-semibold text-gray-700">Apply Wallet</span>
                                </label>
                              </div>
                              <dl x-show="useWallet" class="mt-4 border-t border-indigo-200 pt-3 space-y-2 text-sm" aria-live="polite">
                                  <div class="flex justify-between gap-3">
                                      <dt class="text-indigo-700">Wallet used for this payment</dt>
                                      <dd class="shrink-0 font-semibold tabular-nums text-indigo-900" x-text="'− RM ' + Math.min(walletBalance, parseFloat(String(paymentData.totalAmount || 0).replace(/,/g, '')) + (penaltyDetails ? penaltyDetails.amount : 0)).toFixed(2)"></dd>
                                  </div>
                                  <div class="flex justify-between gap-3 rounded-lg bg-white p-3">
                                      <dt class="font-semibold text-indigo-900">Wallet balance after payment</dt>
                                      <dd class="shrink-0 font-bold tabular-nums text-indigo-900" x-text="'RM ' + Math.max(0, walletBalance - Math.min(walletBalance, parseFloat(String(paymentData.totalAmount || 0).replace(/,/g, '')) + (penaltyDetails ? penaltyDetails.amount : 0))).toFixed(2)"></dd>
                                  </div>
                                  <p class="text-xs text-indigo-600">Estimated balance after confirming this payment.</p>
                              </dl>
                            </div>

                          </div>
                          <div class="min-w-0 p-5 sm:p-6 space-y-5 bg-white border-t border-slate-200 lg:border-t-0 lg:border-l lg:sticky lg:top-0">
                            <div>
                                <h4 class="text-sm font-semibold text-slate-900">Payment details</h4>
                                <p class="mt-1 text-xs text-slate-500">Review the amount and payment information before confirming.</p>
                            </div>
                            {{-- Amount Paid --}}
                            <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">
                                <x-form.input-label value="Amount Paid (RM)" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                                <x-form.text-input type="number" step="0.01" name="amount_paid"
                                    x-bind:value="computedAmount" 
                                    x-bind:readonly="useWallet"
                                    required
                                    @wheel="$event.preventDefault()"
                                    class="block w-full px-3.5 py-3 border border-indigo-200 rounded-xl focus:ring-2 focus:ring-indigo-500 transition-all font-semibold !text-2xl tabular-nums"
                                    ::class="useWallet ? 'bg-gray-200 cursor-not-allowed text-gray-500' : 'bg-gray-50'" />
                                <p class="text-xs text-gray-400 mt-1.5">
                                    Total Invoice Balance: RM <span x-text="paymentData.totalAmount"></span>
                                </p>
                                <x-form.input-error :messages="$errors->get('amount_paid')" class="mt-1" />
                            </div>

                            {{-- Date & Method --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-form.input-label value="Payment Date" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                                    <x-form.date-input id="payment-date" name="payment_date" label="Payment Date" value="{{ date('Y-m-d') }}" 
                                        x-model="form.payment_date"
                                        max-date="today"
                                        @change="updatePenalty()" />
                                    <x-form.input-error :messages="$errors->get('payment_date')" class="mt-1" />
                                </div>

                                <div>
                                    <x-form.input-label value="Payment Method" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                                    
                                    <template x-if="!useWallet">
                                        <div>
                                            <x-form.input-select
                                                name="payment_method"
                                                :options="\App\Enums\PaymentMethod::options()"
                                                :value="\App\Enums\PaymentMethod::CASH->value"
                                                :ignore-checkout-or-renew="true"
                                                placeholder="Select payment method"
                                                x-effect="selectedValues = [method]"
                                                @change="
                                                    if ($event.detail && $event.detail.value && $event.detail.value !== method) {
                                                        method = $event.detail.value;
                                                        $el.closest('form').querySelectorAll('[name=transaction_ref]:not([type=hidden])').forEach(input => input.value = '');
                                                    }
                                                " />
                                        </div>
                                    </template>

                                    <div x-show="useWallet" style="display: none;">
                                        <input type="text" value="Wallet" disabled 
                                            class="block w-full px-3.5 py-2.5 border border-gray-200 rounded-xl bg-gray-200 text-gray-500 text-sm cursor-not-allowed">
                                        <input type="hidden" name="payment_method" value="Wallet" disabled x-bind:disabled="!useWallet">
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3">
                                {{-- Cheque --}}
                                <div x-show="method === 'Cheque'" x-transition x-cloak>
                                    <x-form.input-label value="Cheque Number" class="!text-xs !font-bold !text-indigo-600 uppercase tracking-wider mb-1" />
                                    <x-form.text-input type="text" name="transaction_ref" 
                                        x-bind:required="method === 'Cheque'"
                                        x-bind:disabled="useWallet || method !== 'Cheque'"
                                        placeholder="e.g. 123456"
                                        class="block w-full px-3.5 py-2.5 bg-white border border-indigo-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm shadow-sm" />
                                    <x-form.input-error :messages="$errors->get('transaction_ref')" class="mt-1" />
                                </div>

                                {{-- Bank Transfer / e-Wallet / Digital Bank --}}
                                <div x-show="['Bank Transfer', 'e-Wallet / Digital Wallet', 'Digital Bank'].includes(method)" x-transition x-cloak>
                                    <x-form.input-label value="Reference Number (Ref ID)" class="!text-xs !font-bold !text-indigo-600 uppercase tracking-wider mb-1" />
                                    <x-form.text-input type="text" name="transaction_ref" 
                                        x-bind:required="['Bank Transfer', 'e-Wallet / Digital Wallet', 'Digital Bank'].includes(method)"
                                        x-bind:disabled="useWallet || !['Bank Transfer', 'e-Wallet / Digital Wallet', 'Digital Bank'].includes(method)"
                                        placeholder="e.g. MYR88231... or TNG-992..."
                                        class="block w-full px-3.5 py-2.5 bg-white border border-indigo-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm shadow-sm" />
                                    <x-form.input-error :messages="$errors->get('transaction_ref')" class="mt-1" />
                                </div>

                                {{-- Cards --}}
                                <div x-show="['Credit Card', 'Debit Card'].includes(method)" x-transition x-cloak>
                                    <x-form.input-label value="Last 4 Digits / Auth Code" class="!text-xs !font-bold !text-indigo-600 uppercase tracking-wider mb-1" />
                                    <x-form.text-input type="text" name="transaction_ref" 
                                        x-bind:required="['Credit Card', 'Debit Card'].includes(method)"
                                        x-bind:disabled="useWallet || !['Credit Card', 'Debit Card'].includes(method)"
                                        placeholder="e.g. 8890"
                                        class="block w-full px-3.5 py-2.5 bg-white border border-indigo-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm shadow-sm" />
                                    <x-form.input-error :messages="$errors->get('transaction_ref')" class="mt-1" />
                                </div>

                                {{-- Cash --}}
                                <div x-show="method === 'Cash'" x-transition x-cloak class="p-2.5 bg-amber-50 rounded-lg border border-amber-100">
                                    <p class="text-xs text-amber-700 italic flex items-center">
                                        <svg class="w-4 h-4 mr-2 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"></path>
                                        </svg>
                                        No reference number required for cash payments.
                                    </p>
                                    <input type="hidden" name="transaction_ref" value="CASH" x-bind:disabled="useWallet || method !== 'Cash'">
                                </div>
                            </div>

                            {{-- Remarks --}}
                            <div>
                                <x-form.input-label value="Remarks" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                                <textarea name="remarks" rows="2" placeholder="Internal notes..."
                                        class="block w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm"></textarea>
                                <x-form.input-error :messages="$errors->get('remarks')" class="mt-1" />
                            </div>

                          </div>
                          </div>
                        </div>

                        {{-- Sticky Footer Actions --}}
                        <div class="px-5 py-4 sm:px-6 bg-white border-t border-slate-200 flex justify-end gap-3 shrink-0">
                            <button type="button" @click="openPayment = false" :disabled="submittingPayment"
                                    class="px-5 py-2.5 border border-gray-200 text-gray-600 rounded-xl font-semibold text-sm hover:bg-slate-50 transition-colors">
                                Cancel
                            </button>
                            <x-form.primary-button type="submit" 
                                loading="submittingPayment"
                                x-bind:disabled="submittingPayment || penaltyLoading || !!penaltyError"
                                class="px-5 py-2.5 bg-indigo-600 text-white rounded-xl font-semibold text-sm hover:bg-indigo-700 shadow-sm transition-all">
                                <span>Confirm Payment</span>
                            </x-form.primary-button>
                        </div>
                    </div>
                </x-form.form>
            </div>
        </div>
    </div>
</template>
