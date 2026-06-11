{{-- resources/views/components/modals/package-modal.blade.php --}}
<template x-teleport="body">
    <div x-show="openPackage" x-cloak 
         x-data="{ shake: false }" 
         class="fixed inset-0 z-[101] flex items-center justify-center p-4">
        
        <div class="absolute inset-0">
             <x-ui.blur-overlay show="openPackage" onClose="shake = true; setTimeout(() => shake = false, 400)" />
        </div>

        <div class="relative bg-white rounded-[2rem] shadow-2xl w-fit min-w-[320px] overflow-hidden z-[102]"
             x-show="openPackage"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             :class="{ 'animate-shake border-2 border-indigo-500': shake }"
             @click.stop>
            
            {{-- 头部 --}}
            <div class="px-6 pt-6 pb-2 flex justify-between items-center">
                <h3 class="text-xl font-black text-slate-800">Package Details</h3>
                <button type="button" @click="openPackage = false" class="text-gray-400 hover:text-rose-500 transition-colors text-2xl">&times;</button>
            </div>

            <div class="px-6 pb-6 space-y-4">
                {{-- 主要卡片 --}}
                <div class="bg-slate-50 border border-slate-100 p-5 rounded-2xl space-y-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[10px] text-indigo-500 font-black uppercase tracking-wider">Plan Name</p>
                            <p class="text-lg font-bold text-slate-800" x-text="boostData.packageName"></p>
                        </div>
                        <div class="bg-emerald-100 text-emerald-700 px-2 py-1 rounded-lg text-[10px] font-bold uppercase" x-text="boostData.status"></div>
                    </div>

                    {{-- 详细数据列表 --}}
                    <div class="grid grid-cols-2 gap-x-6 gap-y-3 pt-3 border-t border-slate-200">
                        <div>
                            <p class="text-[10px] text-slate-400 font-bold uppercase">Total Lease</p>
                            <p class="text-sm font-black text-slate-700" x-text="boostData.TotalAvailableLease"></p>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-bold uppercase">Max Limit</p>
                            <p class="text-sm font-black text-slate-700" x-text="boostData.maxLimit"></p>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-bold uppercase">Price</p>
                            <p class="text-sm font-black text-slate-700" x-text="'RM ' + boostData.price"></p>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-bold uppercase">Mode</p>
                            <p class="text-sm font-black text-slate-700 capitalize" x-text="boostData.paymentMode"></p>
                        </div>
                    </div>
                </div>

                {{-- 额外费用提示 (单独一行) --}}
                <div class="bg-indigo-50 p-4 rounded-2xl flex justify-between items-center text-indigo-900 border border-indigo-100">
                    <span class="text-xs font-bold uppercase">Extra Lease Cost</span>
                    <span class="text-sm font-black" x-text="'RM ' + (boostData.extraPrice / 100).toFixed(2) + ' / unit'"></span>
                </div>
            </div>
        </div>
    </div>
</template>