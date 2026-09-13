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

            dynamicPenalty: null,
            form: {
                payment_date: '',
                due_date: '',
                amount_balance: 0
            },

            async updatePenalty() {
                if (!this.paymentData?.id || !this.form.payment_date) {
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
                            due_date: this.form.due_date,        
                            total_amount: this.form.amount_balance, 
                            payment_date: this.form.payment_date  
                        })
                    });
                    
                    let data = await response.json();
                    this.dynamicPenalty = data; 

                    console.log('[Debug] Penalty calculation response:', data);
                } catch (error) {
                    console.error('Failed to calculate penalty:', error);
                } finally {
                    this.penaltyLoading = false; // <-- Always turn off loading when done (success or error)
                }
            },
            
            get penaltyDetails() {
                // If dynamicPenalty is empty, null, or missing an amount, ignore it
                if (!this.dynamicPenalty || Object.keys(this.dynamicPenalty).length === 0 || !this.dynamicPenalty.amount) {
                    return this.paymentData?.initialPenalty || null;
                }
                return this.dynamicPenalty;
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

                if (this.penaltyDetails && this.payFull && this.penaltyDetails.amount) {
                    let penaltyAmount = String(this.penaltyDetails.amount).replace(/,/g, '');
                    baseAmount += parseFloat(penaltyAmount) || 0;
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
                    <div class="flex flex-col max-h-[85vh]">
                        <!-- Scrollable Form Body -->
                        <div class="p-5 space-y-3 overflow-y-auto flex-1 pr-1">
                            {{-- Invoice Items & Penalty Breakdown --}}
                            <div class="bg-gray-50 p-3.5 rounded-xl border border-gray-200 space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Payment Breakdown</span>
                                    <span class="text-xs font-semibold text-gray-400" x-text="((paymentData.invoiceItems || []).length + (penaltyDetails ? 1 : 0)) + ' Item(s)'"></span>
                                </div>

                                <div class="divide-y divide-gray-200 max-h-32 overflow-y-auto pr-1">
                                    {{-- Standard Invoice Items --}}
                                    <template x-for="(item, index) in (paymentData.invoiceItems || [])" :key="index">
                                        <div class="py-2 flex items-center justify-between text-sm">
                                            <span class="font-medium text-gray-800 capitalize" x-text="item.description"></span>
                                            <span class="font-semibold text-gray-900" x-text="'RM ' + (parseFloat(String(item.amount || 0).replace(/,/g, ''))).toFixed(2)"></span>
                                        </div>
                                    </template>

                                    {{-- Dynamic Penalty Row with Settings Details --}}
                                    <template x-if="penaltyDetails">
                                        <div class="py-2 px-2.5 flex items-center justify-between text-sm bg-amber-50/80 border border-amber-200/60 rounded-lg my-1">
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-amber-900" x-text="penaltyDetails.title"></span>
                                                <span class="text-xs text-amber-700/80" x-text="'Calculation: ' + penaltyDetails?.calculation"></span>
                                            </div>
                                            <span class="font-bold text-amber-900" x-text="'+ RM ' + (Number(penaltyDetails.totalAmount || penaltyDetails.amount || 0)).toFixed(2)"></span>
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
                                            @foreach($paymentMethods as $key => $label)
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

                            <input type="hidden" name="penalty_details[amount]" :value="penaltyDetails?.amount || ''">
                            <input type="hidden" name="penalty_details[title]" :value="penaltyDetails?.title || ''">
                        </div>

                        {{-- Sticky Footer Actions --}}
                        <div class="p-4 bg-gray-50 border-t border-gray-100 flex gap-3 shrink-0">
                            <button type="button" @click="openPayment = false"
                                    class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-600 rounded-xl font-bold text-sm hover:bg-white transition-colors">
                                Cancel
                            </button>
                            <x-form.primary-button type="submit" 
                                loading="loading"
                                x-bind:disabled="penaltyLoading"
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