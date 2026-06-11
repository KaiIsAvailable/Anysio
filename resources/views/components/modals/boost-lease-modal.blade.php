{{-- resources/views/components/modals/boost-lease-modal.blade.php --}}
<template x-teleport="body">
    <div x-show="openBoost" x-cloak 
         x-data="{ shake: false, loading: false }" 
         {{-- 确保容器覆盖全屏并强制 flex 居中 --}}
         class="fixed inset-0 z-[101] flex items-center justify-center p-4 sm:p-6">
        
        {{-- Blur Overlay --}}
        <div class="absolute inset-0">
             <x-ui.blur-overlay 
                show="openBoost" 
                onClose="shake = true; setTimeout(() => shake = false, 400)" 
             />
        </div>

        {{-- Modal 主体 --}}
        <div class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-xs overflow-hidden transition-all duration-200 z-[102]"
             x-show="openBoost"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             :class="{ 'animate-shake border-2 border-indigo-500': shake }"
             @click.stop>
            
            {{-- 头部 --}}
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <div>
                    <h3 class="text-lg font-black text-slate-800">Boost Capacity</h3>
                </div>
                <button type="button" @click="openBoost = false" class="text-gray-400 hover:text-rose-500 transition-colors text-2xl">
                    &times;
                </button>
            </div>

            <x-form.form action="{{ route('admin.boost.lease') }}" method="POST">
                @csrf
                <div class="p-6 space-y-5">
                    {{-- 套餐信息 --}}
                    <div class="bg-indigo-50 p-4 rounded-2xl">
                        <p class="text-[10px] text-indigo-600 font-bold uppercase tracking-widest">Current Plan</p>
                        <p class="text-sm font-bold text-slate-700" x-text="boostData.packageName"></p>
                        <div class="flex justify-between text-[11px] text-slate-500 mt-2 font-bold">
                            <span>Limit: <span x-text="boostData.maxLimit"></span> </span>
                            <span>RM <span x-text="boostData.extraPrice / 100"></span> / unit</span>
                        </div>
                        <span class="text-slate-500 text-[11px] font-bold">Balance Limit: <span x-text="boostData.balanceLimit"></span> </span>
                    </div>

                    {{-- 数量 --}}
                    <div>
                        <x-form.input-label value="Quantity" />
                        <x-form.text-input 
                            type="number" 
                            name="lease_count" 
                            min="1"
                            value="1" 
                            required 
                            class="w-full mt-3"
                        /> 
                    </div>
                </div>

                <div class="p-6 bg-gray-50/50 border-t border-gray-100 flex gap-3">
                    <button type="button" @click="openBoost = false" class="flex-1 px-4 py-4 text-gray-400 rounded-2xl font-black text-xs uppercase tracking-widest">Cancel</button>
                    <x-form.primary-button type="submit" loading="loading"
                            class="px-4 py-4 uppercase">
                        Confirm Boost
                    </x-form.primary-button>
                </div>
            </x-form.form>
        </div>
    </div>
</template>