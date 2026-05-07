<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- Header --}}
            <div class="mb-6">
                <a href="{{ route('admin.packages.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to Packages
                </a>
                <h1 class="text-2xl font-bold text-slate-900 mt-2">Create New Package</h1>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <form action="{{ route('admin.packages.store') }}" method="POST" class="p-8">
                    @csrf
                    
                    <div class="space-y-8">
                        {{-- Section 1: Basic Info --}}
                        <div>
                            <h3 class="text-sm font-bold text-indigo-600 uppercase tracking-wider mb-4">Basic Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="name" class="block text-sm font-semibold text-gray-700">Package Name</label>
                                    <input type="text" name="name" id="name" value="{{ old('name') }}" placeholder="e.g. PREMIUM P1" required 
                                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 uppercase">
                                    @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="ref_code" class="block text-sm font-semibold text-gray-700">Reference Code</label>
                                    <input type="text" name="ref_code" id="ref_code" value="{{ old('ref_code') }}" placeholder="AUTO-GENERATED" readonly 
                                           class="mt-1 block w-full rounded-lg border-gray-200 bg-gray-50 shadow-sm font-mono uppercase cursor-not-allowed">
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-4">Billing Configuration</h3>
                            
                            {{-- 1. 周期选择 --}}
                            <div class="mb-6 w-1/2">
                                <label for="price_mode" class="block text-sm font-semibold text-gray-700">Billing Cycle</label>
                                <select name="price_mode" id="price_mode" required 
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 uppercase text-sm">
                                    <option value="MONTHLY">MONTHLY</option>
                                    <option value="YEARLY">YEARLY</option>
                                </select>
                            </div>

                            {{-- 2. 模式切换：决定是按 % 还是按固定 Price --}}
                            <div class="mb-6">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Revenue Model</label>
                                <div class="flex gap-4">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="comm_type" value="percentage" checked class="text-indigo-600 focus:ring-indigo-500">
                                        <span class="ml-2 text-sm text-gray-600 font-medium">Percentage of Rental (%)</span>
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="comm_type" value="fixed" class="text-indigo-600 focus:ring-indigo-500">
                                        <span class="ml-2 text-sm text-gray-600 font-medium">Fixed Subscription Fee (RM)</span>
                                    </label>
                                </div>
                            </div>

                            {{-- 3. 动态输入框 --}}
                            <div class="grid grid-cols-1 md:grid-cols-1 gap-6">
                                {{-- 百分比输入 --}}
                                <div id="section_percentage" class="animate-fadeIn">
                                    <label class="block text-sm font-semibold text-gray-700">Billing Rate (%)</label>
                                    <div class="mt-1 relative w-full"> {{-- 改为 w-full 配合外部 grid --}}
                                        <input type="number" 
                                            name="commission_display" 
                                            id="commission_display"
                                            value="{{ old('commission_display') }}"
                                            min="1" {{-- 物理最小值 --}}
                                            max="100" 
                                            step="0.01" 
                                            placeholder="0" 
                                            class="block w-full rounded-lg border-gray-300 pr-10 focus:ring-indigo-500 focus:border-indigo-500"
                                            onwheel="this.blur()"
                                            onkeypress="return event.charCode >= 48 && event.charCode <= 57 || event.charCode === 46">
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 text-sm">%</span>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-[10px] text-gray-400 italic">Enter a value between 1 and 100.</p>
                                </div>

                                {{-- 固定价格输入 --}}
                                <div id="section_fixed" class="hidden animate-fadeIn">
                                    <label class="block text-sm font-semibold text-gray-700">Fixed Price (RM)</label>
                                    <div class="mt-1 relative w-100">
                                        <input type="number" step="0.01" name="price_display" id="price_display" value="{{ old('price_display') }}"
                                            placeholder="0.00" class="block w-full rounded-lg border-gray-300 pl-12 focus:ring-indigo-500"
                                            onwheel="this.blur()"                                            onkeypress="return event.charCode >= 48 && event.charCode <= 57 || event.charCode === 46">
                                    </div>
                                    <p class="mt-1 text-[10px] text-gray-400 italic">Example: Fixed RM 100 per billing cycle.</p>
                                </div>
                            </div>
                        </div>

                        {{-- Section 3: Lease Expansion (基础 + 增购逻辑) --}}
                        <div>
                            <h3 class="text-sm font-bold text-indigo-600 uppercase tracking-wider mb-4">Lease Expansion Logic</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-6 bg-indigo-50/50 rounded-xl border border-indigo-100">
                                
                                {{-- 1. 原本支持多少 Lease --}}
                                <div>
                                    <label for="base_lease" class="block text-sm font-semibold text-slate-700">Base Lease Quota</label>
                                    <div class="mt-1 relative">
                                        <input type="number" name="base_lease" id="base_lease" 
                                            value="{{ old('base_lease') }}" placeholder="0"  required 
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            onwheel="this.blur()"
                                            oninput="if(this.value.length > 9) this.value = this.value.slice(0, 9);"
                                            onkeypress="return event.charCode >= 48 && event.charCode <= 57 || event.charCode === 46">
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-400 text-xs">Leases</span>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-[11px] text-gray-500 italic">Included in the package price by default.</p>
                                </div>

                                {{-- 2. Add-on Price (加一个多少钱) --}}
                                <div>
                                    <label for="add_on_price" class="block text-sm font-semibold text-slate-700">Add-on Price (Per Lease)(RM)</label>
                                    <div class="mt-1 relative">
                                        <input type="number" 
                                            step="0.01" 
                                            name="add_on_price_display" 
                                            id="add_on_price_display"
                                            value="{{ old('add_on_price_display') }}"
                                            placeholder="0.00" 
                                            required
                                            class="pl-12 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                            onwheel="this.blur()"
                                            onkeypress="return event.charCode >= 48 && event.charCode <= 57 || event.charCode === 46">
                                    </div>
                                    <p class="mt-2 text-[11px] text-gray-500 italic">Charge for each additional lease added.</p>
                                </div>

                                {{-- 3. Lease Limit (最多加购多少) --}}
                                <div>
                                    <label for="add_on_limit" class="block text-sm font-semibold text-slate-700">Max Add-on Limit</label>
                                    <div class="mt-1 relative">
                                        <input type="number" name="add_on_limit" id="add_on_limit" 
                                            value="{{ old('add_on_limit') }}" placeholder="0"  required 
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            onwheel="this.blur()"
                                            onkeypress="return event.charCode >= 48 && event.charCode <= 57 || event.charCode === 46">
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-400 text-xs">Leases</span>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-[11px] text-gray-500 italic">Maximum extra leases allow to purchase.</p>
                                </div>

                            </div>
                            
                            {{-- 逻辑预览提示 (小贴士) --}}
                            <div class="mt-4 px-4 py-2 bg-slate-100 rounded-lg border-l-4 border-slate-400">
                                <p class="text-xs text-slate-600">
                                    <strong>Business Logic:</strong> This package starts with <span id="preview_base" class="font-bold text-indigo-600">10</span> leases. 
                                    Owner can purchase up to <span id="preview_limit" class="font-bold text-indigo-600">50</span> more, 
                                    making the total capacity <span id="preview_total" class="font-bold text-indigo-600">60</span> leases.
                                </p>
                            </div>
                        </div>

                        {{-- Final Actions --}}
                        <div class="pt-6 border-t border-gray-100 flex justify-end gap-3">
                            <a href="{{ route('admin.packages.index') }}" class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                Cancel
                            </a>
                            <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-md transition-all">
                                Create Package
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.getElementById('name');
            const refCodeInput = document.getElementById('ref_code');
            const commRadios = document.querySelectorAll('input[name="comm_type"]');
            const secPercentage = document.getElementById('section_percentage');
            const secFixed = document.getElementById('section_fixed');

            // 1. 自动生成 Ref Code & 全大写
            nameInput.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
                // 生成逻辑：提取大写字母和数字，加上时间戳后四位或随机字符
                let slug = this.value.replace(/[^A-Z0-9]/g, '').substring(0, 5);
                if(slug) {
                    refCodeInput.value = slug + '-' + Math.random().toString(36).substring(2, 6).toUpperCase();
                } else {
                    refCodeInput.value = '';
                }
            });

            // 2. % 和 Price 二选一显示
            commRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'percentage') {
                        secPercentage.classList.remove('hidden');
                        secFixed.classList.add('hidden');
                        document.getElementById('commission_fixed_display').value = '';
                    } else {
                        secFixed.classList.remove('hidden');
                        secPercentage.classList.add('hidden');
                        document.getElementById('commission_display').value = '';
                    }
                });
            });
        });
    </script>

    <style>
        .animate-fadeIn {
            animation: fadeIn 0.3s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</x-app-layout>