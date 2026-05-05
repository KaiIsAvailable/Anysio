{{-- Preview Agreement Modal Component --}}
<div x-data="{ openPreview: false,
    openPreview: false, 
    shakeModal: false }" 

     @open-preview-modal.window="openPreview = true" 
     @open-lease-preview.window="
        openPreview = true;
        {{-- 这里的 $event.detail 会接收到按钮传过来的 content 和 title --}}
        $nextTick(() => {
            document.getElementById('modal-content').innerHTML = $event.detail.content;
            const titleEl = document.getElementById('modal-title');
            if (titleEl) titleEl.innerText = $event.detail.title;
        });
     "
     x-cloak>
    <template x-teleport="body">
        <div x-show="openPreview" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             class="fixed inset-0 z-[100] overflow-y-auto">
            
            <div class="flex items-center justify-center min-h-screen px-4 py-6">
                
                {{-- 背景遮罩 (毛玻璃效果) --}}
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" 
                     @click="shakeModal = true; setTimeout(() => shakeModal = false, 400)">
                </div>

                {{-- Modal 主体 --}}
                <div class="relative bg-white rounded-[2rem] shadow-2xl max-w-4xl w-full overflow-hidden transition-all duration-300 border border-white/20"
                     x-show="openPreview"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                     x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                     :class="{ 'animate-shake': shakeModal }"
                     @click.stop>
                    
                    {{-- Header --}}
                    <div class="px-8 py-6 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                        <div>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight">Agreement Preview</h3>
                            <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-bold">
                                Please review the document carefully
                            </p>
                        </div>
                        <button @click="openPreview = false" class="p-2 rounded-full hover:bg-gray-200 text-gray-400 transition-colors">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- 内容区 --}}
                    <div class="px-10 py-8 max-h-[65vh] overflow-y-auto bg-white prose prose-slate max-w-none" id="modal-content">
                        <!-- JS 注入的内容会出现在这里 -->
                        <div class="flex flex-col items-center justify-center py-12 text-slate-300">
                            <svg class="w-16 h-16 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1.104 1.104 0 01.707.293l5.414 5.414a1.104 1.104 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="mt-4 font-medium">Generating preview...</p>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="px-8 py-6 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                        <span class="text-xs text-slate-400 font-bold uppercase tracking-widest">End of Document</span>
                        <button type="button" 
                                @click="openPreview = false" 
                                class="px-8 py-3 bg-slate-900 text-white rounded-xl font-bold text-sm hover:bg-slate-800 shadow-lg shadow-slate-200 transition-all active:scale-[0.98]">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<style>
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-8px); }
        50% { transform: translateX(8px); }
        75% { transform: translateX(-8px); }
    }
    .animate-shake { animation: shake 0.4s ease-in-out; }
    [x-cloak] { display: none !important; }

    /* 自定义滚动条样式，让它更现代 */
    #modal-content::-webkit-scrollbar { width: 6px; }
    #modal-content::-webkit-scrollbar-track { background: #f1f1f1; }
    #modal-content::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    #modal-content::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>