<template x-teleport="body">
    <div x-show="openPassword" x-cloak 
         x-effect="if (openPassword) { 
             $nextTick(() => { 
                 const first = $el.querySelector('input[type=password]');
                 if (first) first.focus();
             }); 
         }"
         {{-- 外层容器：确保覆盖全屏且层级足够高 --}}
         class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6">
        
        {{-- Blur Overlay --}}
        {{-- 这里直接使用你的组件，并在调用时覆盖层级 --}}
        <x-ui.blur-overlay 
            show="openPassword" 
            onClose="shakePassword = true; setTimeout(() => shakePassword = false, 400)"
            zIndex="z-[90]"
        />

        {{-- Modal 主体 --}}
        {{-- 增加 z-[100] 确保主体永远在 Blur 之上 --}}
        <div class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-xs overflow-hidden transition-all duration-200 z-[100]"
             x-show="openPassword"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             :class="{ 'animate-shake border-2 border-indigo-500': shakePassword }"
             @click.stop>
            
            {{-- 头部 --}}
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="text-lg font-black text-slate-800">Authentication</h3>
                <button type="button" @click="openPassword = false" 
                        class="text-gray-400 hover:text-rose-500 transition-colors text-2xl">
                    &times;
                </button>
            </div>

            {{-- 表单区域：确保没有多余的层遮挡它 --}}
            <form 
                action="#"
                @submit.prevent="verifyPassword(passwordInput)" 
                class="relative z-[101]"
            >
                <div class="p-6 space-y-5">
                    <p class="text-xs text-gray-500 font-medium">Please confirm your password to access sensitive data.</p>
                    
                    <div>
                        <x-form.input-label value="Administrator Password" />
                        <x-form.password-input 
                            x-model="passwordInput"
                            required
                            autocomplete="current-password"
                            placeholder="Enter password..."
                            class="w-full mt-3"
                        />
                    </div>

                    <p x-show="passwordError" x-text="passwordError" 
                       class="text-[11px] text-rose-500 font-bold px-2" x-cloak>
                    </p>
                </div>

                {{-- 底部按钮 --}}
                <div class="p-6 bg-gray-50/50 border-t border-gray-100 flex gap-3">
                    <button type="button" @click="openPassword = false" 
                            class="flex-1 px-4 py-4 text-gray-400 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-gray-100 transition-colors">
                        Cancel
                    </button>
                    <x-form.primary-button type="submit" loading="loading"
                                           class="px-4 py-4 uppercase font-black text-xs">
                        Verify Access
                    </x-form.primary-button>
                </div>
            </form> 
        </div>
    </div>
</template>