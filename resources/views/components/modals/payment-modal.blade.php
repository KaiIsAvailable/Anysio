    {{-- components/payment-modal.blade.php --}}
    <template x-teleport="body">
        <div x-show="openPayment" 
            x-cloak
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
                    
                    {{-- ### 关键修改：在这里添加 x-data ### --}}
                    <form :action="paymentData.actionUrl" method="POST" x-data="{ method: '01' loading: false }" @submit="loading = true">
                        @csrf
                        @method('PATCH')
                        
                        <div class="p-6 space-y-4">
                            {{-- Amount Paid --}}
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Amount Paid (RM)</label>
                                <input type="number" step="0.01" name="amount_paid"
                                    :value="paymentData.amountDue" required
                                    class="block w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 transition-all font-semibold text-lg">
                                <p class="text-xs text-gray-400 mt-2">Outstanding: RM <span x-text="paymentData.amountDue"></span></p>
                                @error('amount_paid')
                                    <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Date & Method --}}
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Payment Date</label>
                                    <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required
                                        class="block w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
                                    @error('payment_date')
                                        <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Received Via</label>
                                    <select name="received_via" 
                                            x-model="method"
                                            required 
                                            class="block w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
                                        <option value="Cash">Cash (01)</option>
                                        <option value="Cheque">Cheque (02)</option>
                                        <option value="Bank Transfer">Bank Transfer (03)</option>
                                        <option value="Credit Card">Credit Card (04)</option>
                                        <option value="Debit Card">Debit Card (05)</option>
                                        <option value="e-Wallet / Digital Wallet">e-Wallet / Digital Wallet (06)</option>
                                        <option value="Digital Bank">Digital Bank (07)</option>
                                        <option value="Others">Others (08)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-4 space-y-4">
                                {{-- Cheque --}}
                                <div x-show="method === 'Cheque'" x-transition x-cloak>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1 text-indigo-600">Cheque Number</label>
                                    <input type="text" name="transaction_ref" 
                                        :required="method === 'Cheque'"
                                        :disabled="method !== 'Cheque'"
                                        placeholder="e.g. 123456"
                                        class="block w-full px-4 py-3 bg-white border border-indigo-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm shadow-sm">
                                    @error('transaction_ref')
                                        <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Bank Transfer / e-Wallet / Digital Bank --}}
                                <div x-show="['Bank Transfer', 'e-Wallet / Digital Wallet', 'Digital Bank'].includes(method)" x-transition x-cloak>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1 text-indigo-600">Reference Number (Ref ID)</label>
                                    <input type="text" name="transaction_ref" 
                                        :required="['Bank Transfer', 'e-Wallet / Digital Wallet', 'Digital Bank'].includes(method)"
                                        :disabled="!['Bank Transfer', 'e-Wallet / Digital Wallet', 'Digital Bank'].includes(method)"
                                        placeholder="e.g. MYR88231... or TNG-992..."
                                        class="block w-full px-4 py-3 bg-white border border-indigo-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm shadow-sm">
                                    @error('transaction_ref')
                                        <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Cards --}}
                                <div x-show="['Credit Card', 'Debit Card'].includes(method)" x-transition x-cloak>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1 text-indigo-600">Last 4 Digits / Auth Code</label>
                                    <input type="text" name="transaction_ref" 
                                        :required="['Credit Card', 'Debit Card'].includes(method)"
                                        :disabled="!['Credit Card', 'Debit Card'].includes(method)"
                                        placeholder="e.g. 8890"
                                        class="block w-full px-4 py-3 bg-white border border-indigo-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm shadow-sm">
                                    @error('transaction_ref')
                                        <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                                    @enderror
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
                                    <input type="hidden" name="transaction_ref" value="CASH" :disabled="method !== 'Cash'">
                                </div>
                            </div>

                            {{-- Remarks --}}
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Remarks</label>
                                <textarea name="remarks" rows="2" placeholder="Internal notes..."
                                        class="block w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm"></textarea>
                                @error('remarks')
                                    <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="p-6 bg-gray-50 border-t border-gray-100 flex gap-3">
                            <button type="button" @click="openPayment = false" loading="loading"
                                    class="flex-1 px-4 py-3 border border-gray-200 text-gray-600 rounded-xl font-bold text-sm hover:bg-white transition-colors">
                                Cancel
                            </button>
                            <x-primary-button type="submit" 
                                loading="loading"
                                class="flex-1 px-4 py-3 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 shadow-lg shadow-indigo-100 transition-all">
                                <span>Confirm Payment</span>
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </template>