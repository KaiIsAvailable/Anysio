{{-- components/payment-modal.blade.php --}}
<template x-teleport="body">
    <div x-show="openPayment" 
        x-cloak
        @open-payment.window="
            openPayment = true; 
            paymentData = $event.detail;
            walletBalance = parseFloat(String(paymentData.walletBalance || '0').replace(/,/g, '')) || 0;
            useWallet = false;
            method = 'Cash';
            penaltyCancelled = false;
            overridePenaltyAmount = null;
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
                    if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : data.message || 'Unable to calculate the penalty.');
                    this.dynamicPenalty = data; 
                    this.overridePenaltyAmount = data.amount || data.totalAmount || 0;
                } catch (error) {
                    if (sequence === this.penaltySequence) this.penaltyError = error.message || 'Unable to calculate the penalty. Please try again.';
                } finally {
                    if (sequence === this.penaltySequence) this.penaltyLoading = false;
                }
            },
            
            get penaltyDetails() {
                if (this.penaltyCancelled || !this.dynamicPenalty) return null;
                // Allow amount to be 0 or empty for typing flexibility without hiding the row
                let currentAmount = this.overridePenaltyAmount !== null && this.overridePenaltyAmount !== '' ? parseFloat(this.overridePenaltyAmount) || 0 : 0;
                return {
                    ...this.dynamicPenalty,
                    amount: currentAmount
                };
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

                return baseAmount.toFixed(2);
            },

            handleWalletToggle(event) {
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

        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            
            {{-- 背景遮罩层 --}}
            <div class="absolute inset-0">
                <x-ui.blur-overlay 
                    show="openPayment" 
                    onClose="shakePayment = true; setTimeout(() => shakePayment = false, 400)" 
                />
            </div>

            {{-- 模态框主体 --}}
            <div class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden transition-all duration-200 z-[102]"
                x-show="openPayment"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                :class="{ 'animate-shake': shakePayment }"
                @click.stop>
                
                <div class="p-6 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800 m-0">
                            Record Payment: <span class="text-indigo-600" x-text="paymentData.invoiceNo"></span>
                        </h3>
                    </div>
                    <button type="button" @click="openPayment = false" 
                            class="text-gray-400 hover:text-gray-600 text-2xl transition-transform hover:scale-110"
                            style="margin-top: -4px;">
                        &times;
                    </button>
                </div>
                
                @php
                $paymentMethods = [
                    'Cash' => 'Cash (01)',
                    'Cheque' => 'Cheque (02)',
                    'Bank Transfer' => 'Bank Transfer (03)',
                    'Credit Card' => 'Credit Card (04)',
                    'Debit Card' => 'Debit Card (05)',
                    'e-Wallet / Digital Wallet' => 'e-Wallet / Digital Wallet (06)',
                    'Digital Bank' => 'Digital Bank (07)',
                    'Others' => 'Others (08)',
                    'Wallet' => 'Wallet',
                ];
                @endphp

                <x-form.form x-bind:action="paymentData.actionUrl" method="POST">
                    <input type="hidden" name="penalty_amount" x-bind:value="penaltyDetails ? penaltyDetails.amount : 0">

                    <div class="flex flex-col max-h-[85vh]">
                        <!-- Scrollable Form Body -->
                        <div class="p-5 space-y-3 overflow-y-auto flex-1 pr-1">
                            {{-- Invoice Items & Penalty Breakdown --}}
                            <div class="bg-gray-50 p-3.5 rounded-xl border border-gray-200 space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Payment Breakdown</span>
                                    <span class="text-xs font-semibold text-gray-400" x-text="((paymentData.invoiceItems || []).length + (penaltyDetails && Object.keys(penaltyDetails).length > 0 ? 1 : 0)) + ' Item(s)'"></span>
                                </div>

                                <div class="divide-y divide-gray-200 max-h-32 overflow-y-auto pr-1">
                                    {{-- Standard Invoice Items --}}
                                    <template x-for="(item, index) in (paymentData.invoiceItems || [])" :key="index">
                                        <div class="py-2 flex items-center justify-between text-sm">
                                            <span class="font-medium text-gray-800 capitalize" x-text="item.name || item.description"></span>
                                            <span class="font-semibold text-gray-900" x-text="'RM ' + (parseFloat(String(item.amount || 0).replace(/,/g, ''))).toFixed(2)"></span>
                                        </div>
                                    </template>

                                    {{-- Dynamic Editable/Cancellable Penalty Row (Only shows when penalty rule/data exists) --}}
                                    <template x-if="!penaltyCancelled && dynamicPenalty && Object.keys(dynamicPenalty).length > 0">
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
                                                        class="w-24 px-2 py-1 text-sm font-bold text-amber-900 bg-white border border-amber-300 rounded-md focus:ring-1 focus:ring-amber-500 focus:outline-none text-right @canany(['owner-admin', 'invoice.edit penalty']) @else bg-gray-100 text-gray-500 cursor-not-allowed opacity-75 @endcanany"
                                                        @wheel="$event.preventDefault()"
                                                        @canany(['owner-admin', 'invoice.edit penalty']) @else readonly @endcanany />
                                                </div>
                                                
                                                @canany(['owner-admin', 'invoice.remove penalty'])
                                                <button type="button" @click="penaltyCancelled = true" class="text-amber-700 hover:text-red-600 p-1 transition-colors" title="Cancel/Remove Penalty">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                                @endcanany
                                            </div>
                                        </div>
                                    </template>

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
                            <div x-show="walletBalance > 0" class="p-3.5 bg-indigo-50 rounded-xl border border-indigo-100 flex items-center justify-between" x-cloak>
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

                            {{-- Amount Paid --}}
                            <div>
                                <x-form.input-label value="Amount Paid (RM)" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                                <x-form.text-input type="number" step="0.01" name="amount_paid"
                                    x-bind:value="computedAmount" 
                                    x-bind:readonly="useWallet"
                                    required
                                    @wheel="$event.preventDefault()"
                                    class="block w-full px-3.5 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 transition-all font-semibold text-base"
                                    ::class="useWallet ? 'bg-gray-200 cursor-not-allowed text-gray-500' : 'bg-gray-50'" />
                                <p class="text-xs text-gray-400 mt-1.5">
                                    Total Invoice Balance: RM <span x-text="paymentData.totalAmount"></span>
                                    <span x-show="useWallet" class="text-indigo-600 font-medium" 
                                        x-text="' (After RM ' + Math.min(walletBalance, parseFloat(String(paymentData.totalAmount || 0).replace(/,/g, '')) + (penaltyDetails ? penaltyDetails.amount : 0)).toFixed(2) + ' wallet deduction)'"></span>
                                </p>
                                <x-form.input-error :messages="$errors->get('amount_paid')" class="mt-1" />
                            </div>

                            {{-- Date & Method --}}
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <x-form.input-label value="Payment Date" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                                    <x-form.date-input id="payment-date" name="payment_date" label="Payment Date" value="{{ date('Y-m-d') }}" 
                                        x-model="form.payment_date" 
                                        @change="updatePenalty()" />
                                    <x-form.input-error :messages="$errors->get('payment_date')" class="mt-1" />
                                </div>

                                <div>
                                    <x-form.input-label value="Payment Method" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                                    
                                    <div x-show="!useWallet">
                                        <select name="payment_method" 
                                            x-model="method"
                                            required 
                                            class="block w-full px-3.5 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm bg-gray-50">
                                            @foreach($paymentMethods as $key =>$label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div x-show="useWallet" style="display: none;">
                                        <input type="text" value="Wallet" disabled 
                                            class="block w-full px-3.5 py-2.5 border border-gray-200 rounded-xl bg-gray-200 text-gray-500 text-sm cursor-not-allowed">
                                        <input type="hidden" name="payment_method" value="Wallet">
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3">
                                {{-- Cheque --}}
                                <div x-show="method === 'Cheque'" x-transition x-cloak>
                                    <x-form.input-label value="Cheque Number" class="!text-xs !font-bold !text-indigo-600 uppercase tracking-wider mb-1" />
                                    <x-form.text-input type="text" name="transaction_ref" 
                                        x-bind:required="method === 'Cheque'"
                                        x-bind:disabled="method !== 'Cheque'"
                                        placeholder="e.g. 123456"
                                        class="block w-full px-3.5 py-2.5 bg-white border border-indigo-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm shadow-sm" />
                                    <x-form.input-error :messages="$errors->get('transaction_ref')" class="mt-1" />
                                </div>

                                {{-- Bank Transfer / e-Wallet / Digital Bank --}}
                                <div x-show="['Bank Transfer', 'e-Wallet / Digital Wallet', 'Digital Bank'].includes(method)" x-transition x-cloak>
                                    <x-form.input-label value="Reference Number (Ref ID)" class="!text-xs !font-bold !text-indigo-600 uppercase tracking-wider mb-1" />
                                    <x-form.text-input type="text" name="transaction_ref" 
                                        x-bind:required="['Bank Transfer', 'e-Wallet / Digital Wallet', 'Digital Bank'].includes(method)"
                                        x-bind:disabled="!['Bank Transfer', 'e-Wallet / Digital Wallet', 'Digital Bank'].includes(method)"
                                        placeholder="e.g. MYR88231... or TNG-992..."
                                        class="block w-full px-3.5 py-2.5 bg-white border border-indigo-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm shadow-sm" />
                                    <x-form.input-error :messages="$errors->get('transaction_ref')" class="mt-1" />
                                </div>

                                {{-- Cards --}}
                                <div x-show="['Credit Card', 'Debit Card'].includes(method)" x-transition x-cloak>
                                    <x-form.input-label value="Last 4 Digits / Auth Code" class="!text-xs !font-bold !text-indigo-600 uppercase tracking-wider mb-1" />
                                    <x-form.text-input type="text" name="transaction_ref" 
                                        x-bind:required="['Credit Card', 'Debit Card'].includes(method)"
                                        x-bind:disabled="!['Credit Card', 'Debit Card'].includes(method)"
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
                                    <input type="hidden" name="transaction_ref" value="CASH" x-bind:disabled="method !== 'Cash'">
                                </div>
                            </div>

                            {{-- Remarks --}}
                            <div>
                                <x-form.input-label value="Remarks" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                                <textarea name="remarks" rows="2" placeholder="Internal notes..."
                                        class="block w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm"></textarea>
                                <x-form.input-error :messages="$errors->get('remarks')" class="mt-1" />
                            </div>

                            <p x-show="penaltyError" x-text="penaltyError" role="alert" class="text-sm text-red-700"></p>
                        </div>

                        {{-- Sticky Footer Actions --}}
                        <div class="p-4 bg-gray-50 border-t border-gray-100 flex gap-3 shrink-0">
                            <button type="button" @click="openPayment = false"
                                    class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-600 rounded-xl font-bold text-sm hover:bg-white transition-colors">
                                Cancel
                            </button>
                            <x-form.primary-button type="submit" 
                                loading="loading"
                                x-bind:disabled="penaltyLoading || !!penaltyError"
                                class="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 shadow-lg shadow-indigo-100 transition-all">
                                <span>Confirm Payment</span>
                            </x-form.primary-button>
                        </div>
                    </div>
                </x-form.form>
            </div>
        </div>
    </div>
</template>