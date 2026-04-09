{{-- components/manual-invoice-modal.blade.php --}}
@props(['tenantId']) {{-- 这里其实只需要接收一些初始 ID，如果不传也可以 --}}

<template x-teleport="body">
    <div x-data="{ 
            openManual: false, 
            actionUrl: '',
            shake: false {{-- 1. 新增 shake 变量 --}}
         }" 
         @open-manual-modal.window="
            actionUrl = $event.detail.action; 
            openManual = true;
         "
         x-show="openManual" 
         x-cloak
         class="fixed inset-0 z-[110] overflow-y-auto">
        
        <div class="flex items-center justify-center min-h-screen px-4 py-6 text-center sm:block sm:p-0">
            
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm" 
                 x-show="openManual"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 @click="shake = true; setTimeout(() => shake = false, 400)"> {{-- 2. 这里的点击不再关闭 Modal --}}
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                 :class="{ 'animate-shake': shake }" {{-- 3. 这里的绑定会触发你在 app.css 里的样式 --}}
                 x-show="openManual"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                
                {{-- ### 关键修改点：使用 :action 而不是 {{ $actionUrl }} ### --}}
                <form :action="actionUrl" method="POST">
                    @csrf
                    <div class="px-6 pt-6 pb-4 bg-white">
                        <div class="flex items-center justify-between mb-4 border-b pb-3">
                            <h3 class="text-xl font-bold text-slate-800">Create Manual Invoice</h3>
                            <button type="button" @click="openManual = false" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                        </div>
                        
                        <div class="space-y-5">
                            <div>
                                <label class="block text-xs font-bold uppercase text-gray-500 tracking-wider mb-1">Payment Type</label>
                                <select name="payment_type" class="block w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 transition-all text-sm" required>
                                    <option value="Water Bill">Water Bill</option>
                                    <option value="Electricity Bill">Electricity Bill</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Deposit">Deposit</option>
                                    <option value="Surcharge">Surcharge</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase text-gray-500 tracking-wider mb-1">Amount (RM)</label>
                                <input type="number" step="0.01" name="amount_due" required placeholder="0.00"
                                       class="block w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 transition-all font-semibold text-lg">
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase text-gray-500 tracking-wider mb-1">Billing Month</label>
                                <input type="month" name="period" required value="{{ date('Y-m') }}"
                                       class="block w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase text-gray-500 tracking-wider mb-1">Remarks</label>
                                <textarea name="remarks" rows="2" placeholder="What is this for?"
                                          class="block w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 flex flex-row-reverse gap-3">
                        <button type="submit" class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 shadow-lg shadow-indigo-100 transition-all uppercase tracking-widest">
                            Generate
                        </button>
                        <button type="button" @click="openManual = false" class="flex-1 px-6 py-3 border border-gray-200 text-gray-600 rounded-xl font-bold text-sm hover:bg-white transition-colors uppercase tracking-widest">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>