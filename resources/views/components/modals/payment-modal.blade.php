    {{-- components/payment-modal.blade.php --}}
    <template x-teleport="body">
        <div x-show="openPayment" 
            x-cloak
            x-effect="if (openPayment) { 
                $nextTick(() => { 
                    const first = $el.querySelector('input:not([type=hidden]):not([disabled])');
                    if (first) {
                        first.focus();
                    }
                }); 
            }"
            class="fixed inset-0 z-[100] overflow-y-auto">
            
            <div class="flex items-center justify-center min-h-screen px-4 py-6">
                
                {{-- 背景遮罩层 --}}
                <div class="absolute inset-0">
                    <x-ui.blur-overlay 
                        show="openPayment" {{-- 保持与外层变量一致 --}}
                        onClose="shakePayment = true; setTimeout(() => shakePayment = false, 400)" 
                    />
                </div>

                {{-- 模态框主体 --}}
                <div class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden transition-all duration-200 z-[102]"
                    x-show="openPayment"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    {{-- 绑定抖动动画 --}}
                    :class="{ 'animate-shake': shakePayment }"
                    @click.stop>
                    
                    <div class="p-6 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-800 m-0">
                            Record Payment: <span class="text-indigo-600" x-text="paymentData.invoiceNo"></span>
                        </h3>
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
                    ];
                    @endphp

                    <x-form.form x-bind:action="paymentData.actionUrl" method="PATCH" x-data="{ method: 'Cash' }">
                        
                        <div class="p-6 space-y-4">
                            {{-- Amount Paid --}}
                            <div>
                                <x-form.input-label value="Amount Paid (RM)" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                                <x-form.text-input type="number" step="0.01" name="amount_paid"
                                    x-bind:value="paymentData.amountDue" required
                                    class="block w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 transition-all font-semibold text-lg" />
                                <p class="text-xs text-gray-400 mt-2">Outstanding: RM <span x-text="paymentData.amountDue"></span></p>
                                <x-form.input-error :messages="$errors->get('amount_paid')" class="mt-1" />
                            </div>

                            {{-- Date & Method --}}
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <x-form.input-label value="Payment Date" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                                    <x-form.text-input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required
                                        class="block w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm" />
                                    <x-form.input-error :messages="$errors->get('payment_date')" class="mt-1" />
                                </div>

                                <div>
                                    <x-form.input-label value="Received Via" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                                    <x-form.input-select name="received_via" 
                                             x-model="method"
                                             :options="$paymentMethods"
                                             required 
                                             class="block w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm" />
                                </div>
                            </div>

                            <div class="mt-4 space-y-4">
                                {{-- Cheque --}}
                                <div x-show="method === 'Cheque'" x-transition x-cloak>
                                    <x-form.input-label value="Cheque Number" class="!text-xs !font-bold !text-indigo-600 uppercase tracking-wider mb-1" />
                                    <x-form.text-input type="text" name="transaction_ref" 
                                        x-bind:required="method === 'Cheque'"
                                        x-bind:disabled="method !== 'Cheque'"
                                        placeholder="e.g. 123456"
                                        class="block w-full px-4 py-3 bg-white border border-indigo-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm shadow-sm" />
                                    <x-form.input-error :messages="$errors->get('transaction_ref')" class="mt-1" />
                                </div>

                                {{-- Bank Transfer / e-Wallet / Digital Bank --}}
                                <div x-show="['Bank Transfer', 'e-Wallet / Digital Wallet', 'Digital Bank'].includes(method)" x-transition x-cloak>
                                    <x-form.input-label value="Reference Number (Ref ID)" class="!text-xs !font-bold !text-indigo-600 uppercase tracking-wider mb-1" />
                                    <x-form.text-input type="text" name="transaction_ref" 
                                        x-bind:required="['Bank Transfer', 'e-Wallet / Digital Wallet', 'Digital Bank'].includes(method)"
                                        x-bind:disabled="!['Bank Transfer', 'e-Wallet / Digital Wallet', 'Digital Bank'].includes(method)"
                                        placeholder="e.g. MYR88231... or TNG-992..."
                                        class="block w-full px-4 py-3 bg-white border border-indigo-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm shadow-sm" />
                                    <x-form.input-error :messages="$errors->get('transaction_ref')" class="mt-1" />
                                </div>

                                {{-- Cards --}}
                                <div x-show="['Credit Card', 'Debit Card'].includes(method)" x-transition x-cloak>
                                    <x-form.input-label value="Last 4 Digits / Auth Code" class="!text-xs !font-bold !text-indigo-600 uppercase tracking-wider mb-1" />
                                    <x-form.text-input type="text" name="transaction_ref" 
                                        x-bind:required="['Credit Card', 'Debit Card'].includes(method)"
                                        x-bind:disabled="!['Credit Card', 'Debit Card'].includes(method)"
                                        placeholder="e.g. 8890"
                                        class="block w-full px-4 py-3 bg-white border border-indigo-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm shadow-sm" />
                                    <x-form.input-error :messages="$errors->get('transaction_ref')" class="mt-1" />
                                </div>

                                {{-- Cash --}}
                                <div x-show="method === 'Cash'" x-transition x-cloak class="p-3 bg-amber-50 rounded-lg border border-amber-100">
                                    <p class="text-xs text-amber-700 italic flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"></path>
                                        </svg>
                                        No reference number required for cash payments.
                                    </p>
                                    {{-- If it's not Cash, we disable this so it doesn't overwrite other methods --}}
                                    <input type="hidden" name="transaction_ref" value="CASH" x-bind:disabled="method !== 'Cash'">
                                </div>
                            </div>

                            {{-- Remarks --}}
                            <div>
                                <x-form.input-label value="Remarks" class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1" />
                                <textarea name="remarks" rows="2" placeholder="Internal notes..."
                                        class="block w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm"></textarea>
                                <x-form.input-error :messages="$errors->get('remarks')" class="mt-1" />
                            </div>
                        </div>

                        <div class="p-6 bg-gray-50 border-t border-gray-100 flex gap-3">
                            <button type="button" @click="openPayment = false"
                                    class="flex-1 px-4 py-3 border border-gray-200 text-gray-600 rounded-xl font-bold text-sm hover:bg-white transition-colors">
                                Cancel
                            </button>
                            <x-form.primary-button type="submit" 
                                loading="loading"
                                class="flex-1 px-4 py-3 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 shadow-lg shadow-indigo-100 transition-all">
                                <span>Confirm Payment</span>
                            </x-form.primary-button>
                        </div>
                    </x-form.form>
                </div>
            </div>
        </div>
    </template>