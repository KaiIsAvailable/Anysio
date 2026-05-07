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
                <h1 class="text-2xl font-bold text-slate-900 mt-2">Edit Package: {{ $package->name }}</h1>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                {{-- 注意：这里使用 PUT 方法 --}}
                <form action="{{ route('admin.packages.update', $package->id) }}" method="POST" class="p-8">
                    @csrf
                    @method('PUT')
                    
                    <div class="space-y-8">
                        {{-- Section 1: Basic Info --}}
                        <div>
                            <h3 class="text-sm font-bold text-indigo-600 uppercase tracking-wider mb-4">Basic Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="name" class="block text-sm font-semibold text-gray-700">Package Name</label>
                                    <input type="text" name="name" id="name" value="{{ old('name', $package->name) }}" required 
                                           class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 uppercase">
                                </div>

                                <div>
                                    <label for="ref_code" class="block text-sm font-semibold text-gray-700">Reference Code</label>
                                    <input type="text" value="{{ $package->ref_code }}" readonly 
                                           class="mt-1 block w-full rounded-lg border-gray-200 bg-gray-50 shadow-sm font-mono uppercase cursor-not-allowed">
                                    <p class="mt-1 text-[10px] text-gray-400">Reference code cannot be changed.</p>
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
                                    <option value="MONTHLY" {{ old('price_mode', $package->price_mode) == 'MONTHLY' ? 'selected' : '' }}>MONTHLY</option>
                                    <option value="YEARLY" {{ old('price_mode', $package->price_mode) == 'YEARLY' ? 'selected' : '' }}>YEARLY</option>
                                </select>
                            </div>

                            {{-- 2. 模式切换 --}}
                            @php
                                // 逻辑判断当前是百分比还是固定价格
                                $isFixed = $package->price > 0;
                            @endphp
                            <div class="mb-6">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Revenue Model</label>
                                <div class="flex gap-4">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="comm_type" value="percentage" {{ !$isFixed ? 'checked' : '' }} class="text-indigo-600 focus:ring-indigo-500">
                                        <span class="ml-2 text-sm text-gray-600 font-medium">Percentage of Rental (%)</span>
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="comm_type" value="fixed" {{ $isFixed ? 'checked' : '' }} class="text-indigo-600 focus:ring-indigo-500">
                                        <span class="ml-2 text-sm text-gray-600 font-medium">Fixed Subscription Fee (RM)</span>
                                    </label>
                                </div>
                            </div>

                            {{-- 3. 动态输入框 --}}
                            <div class="grid grid-cols-1 md:grid-cols-1 gap-6">
                                <div id="section_percentage" class="{{ $isFixed ? 'hidden' : '' }} animate-fadeIn">
                                    <label class="block text-sm font-semibold text-gray-700">Billing Rate (%)</label>
                                    <div class="mt-1 relative w-full">
                                        <input type="number" name="commission_display" id="commission_display" 
                                               value="{{ old('commission_display', $package->commission_rate / 100) }}"
                                               min="1" max="100" step="0.01" class="block w-full rounded-lg border-gray-300 pr-10 focus:ring-indigo-500"
                                               onwheel="this.blur()">
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 text-sm">%</span>
                                        </div>
                                    </div>
                                </div>

                                <div id="section_fixed" class="{{ !$isFixed ? 'hidden' : '' }} animate-fadeIn">
                                    <label class="block text-sm font-semibold text-gray-700">Fixed Price (RM)</label>
                                    <div class="mt-1 relative w-full">
                                        <input type="number" step="0.01" name="price_display" id="price_display" 
                                               value="{{ old('price_display', $package->price / 100) }}"
                                               class="block w-full rounded-lg border-gray-300 pl-4 focus:ring-indigo-500"
                                               onwheel="this.blur()">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Section 3: Lease Expansion --}}
                        <div>
                            <h3 class="text-sm font-bold text-indigo-600 uppercase tracking-wider mb-4">Lease Expansion Logic</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-6 bg-indigo-50/50 rounded-xl border border-indigo-100">
                                <div>
                                    <label for="base_lease" class="block text-sm font-semibold text-slate-700">Base Lease Quota</label>
                                    <input type="number" name="base_lease" id="base_lease" 
                                           value="{{ old('base_lease', $package->base_lease) }}" required 
                                           class="mt-1 block w-full rounded-lg border-gray-300"
                                           oninput="if(this.value.length > 9) this.value = this.value.slice(0, 9);">
                                </div>

                                <div>
                                    <label for="add_on_price_display" class="block text-sm font-semibold text-slate-700">Add-on Price (RM)</label>
                                    <input type="number" step="0.01" name="add_on_price_display" id="add_on_price_display"
                                           value="{{ old('add_on_price_display', $package->extra_lease_price / 100) }}" required
                                           class="mt-1 block w-full rounded-lg border-gray-300">
                                </div>

                                <div>
                                    <label for="add_on_limit" class="block text-sm font-semibold text-slate-700">Max Add-on Limit</label>
                                    <input type="number" name="add_on_limit" id="add_on_limit" 
                                           value="{{ old('add_on_limit', $package->max_lease_limit) }}" required 
                                           class="mt-1 block w-full rounded-lg border-gray-300">
                                </div>
                            </div>
                        </div>

                        <div class="pt-6 border-t border-gray-100 flex justify-end gap-3">
                            <a href="{{ route('admin.packages.index') }}" class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</a>
                            <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-md">Update Package</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const commRadios = document.querySelectorAll('input[name="comm_type"]');
            const secPercentage = document.getElementById('section_percentage');
            const secFixed = document.getElementById('section_fixed');

            commRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'percentage') {
                        secPercentage.classList.remove('hidden');
                        secFixed.classList.add('hidden');
                    } else {
                        secFixed.classList.remove('hidden');
                        secPercentage.classList.add('hidden');
                    }
                });
            });
        });
    </script>
</x-app-layout>