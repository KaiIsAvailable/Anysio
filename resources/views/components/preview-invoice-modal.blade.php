{{-- Preview Agreement Modal Component --}}
<div x-data="{ 
        openPreview: false, 
        shakeModal: false,
        closeModal() {
            this.openPreview = false;
            document.body.style.overflow = ''; // Restores background scrolling
        },
        printDocument() {
            // Get the fully parsed HTML content currently displayed in the modal
            let contentHtml = document.getElementById('invoice-modal-content').innerHTML;
            let titleText = document.getElementById('invoice-modal-title') ? document.getElementById('invoice-modal-title').innerText : 'Document Preview';

            let printWindow = window.open('', '_blank', 'height=600,width=800');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                    <head>
                        <title>${titleText}</title>
                        <style>
                            body { font-family: sans-serif; padding: 20px; color: #0f172a; margin: 0; }
                            table { width: 100%; border-collapse: collapse; }
                            th, td { padding: 12px 15px; }
                        </style>
                    </head>
                    <body>
                        ${contentHtml}
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 500);
        }
     }"

        @open-invoice-preview.window="
    openPreview = true;
    $nextTick(() => {
        document.getElementById('invoice-modal-content').innerHTML = $event.detail.content;

        const titleEl = document.getElementById('invoice-modal-title');

        if (titleEl) {
            titleEl.innerText = $event.detail.title;
        }

        document.body.style.overflow = 'hidden';
    });
"

    @open-lease-preview.window="
    openPreview = true;
    $nextTick(() => {
        document.getElementById('invoice-modal-content').innerHTML = $event.detail.content;

        const titleEl = document.getElementById('invoice-modal-title');

        if (titleEl) {
            titleEl.innerText = $event.detail.title;
        }

        document.body.style.overflow = 'hidden';
    });
"
    x-cloak>
    <template x-teleport="body">
        <!-- 🌟 核心修复：直接使用内联 style 强制 z-index: 9999，无视 Tailwind 编译问题 -->
        <div x-show="openPreview"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            class="fixed inset-0 overflow-y-auto"
            style="z-index: 9999;">

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
                            <h3 id="invoice-modal-title" class="text-2xl font-black text-slate-800 tracking-tight">Invoice Preview</h3>
                            <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-bold">
                                Please review the document carefully
                            </p>
                        </div>
                        <button @click="closeModal()" class="p-2 rounded-full hover:bg-gray-200 text-gray-400 transition-colors">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- 内容区 --}}
                    <div class="px-10 py-8 max-h-[65vh] overflow-y-auto bg-white prose prose-slate max-w-none" id="invoice-modal-content">
                        <!-- JS 注入的内容会出现在这里 -->
                        <div class="flex flex-col items-center justify-center py-12 text-slate-300">
                            <svg class="w-16 h-16 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1.104 1.104 0 01.707.293l5.414 5.414a1.104 1.104 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="mt-4 font-medium">Generating preview...</p>
                        </div>
                    </div>

                    {{-- Footer with Print Button --}}
                    <div class="px-8 py-6 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                        <span class="text-xs text-slate-400 font-bold uppercase tracking-widest">End of Document</span>
                        <div class="flex items-center gap-3">
                            {{-- Print Button --}}
                            <button type="button"
                                @click="printDocument()"
                                class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 shadow-lg shadow-indigo-100 transition-all active:scale-[0.98]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                Print
                            </button>

                            {{-- Close Button --}}
                            <button type="button"
                                @click="closeModal()"
                                class="px-8 py-3 bg-slate-900 text-white rounded-xl font-bold text-sm hover:bg-slate-800 shadow-lg shadow-slate-200 transition-all active:scale-[0.98]">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>