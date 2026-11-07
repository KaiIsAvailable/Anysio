{{-- Preview Agreement Modal Component --}}
<script>
    function renderAgreementPaper(frame) {
        frame.srcdoc = window.documentLayout.documentHtml(document.getElementById('modal-content').innerHTML);
    }
    function printAgreementPaper() {
        return window.documentLayout.printDocument(
            document.getElementById('modal-content').innerHTML,
            document.getElementById('modal-title')?.innerText || 'Document Preview'
        );
    }
    function resizeAgreementPaper(frame) {
        window.documentLayout.resizeDocumentPaper(frame);
    }
</script>
<div x-data="{ 
        ...agreementBreakdown('modal-content'),
        openPreview: false, 
        previewLoading: false,
        previewRendering: false,
        previewError: '',
        previewRequestId: null,
        shakeModal: false,
        refreshPaper() {
            this.previewRendering = true;
            this.$nextTick(() => {
                if (this.openPreview) renderAgreementPaper(this.$refs.agreementPaper);
            });
        },
        closeModal() {
            this.resetBreakdown();
            this.openPreview = false;
            this.previewRequestId = null;
            this.previewLoading = false;
            this.previewRendering = false;
            this.previewError = '';
            document.body.style.overflow = ''; // Restores background scrolling
        },
        printDocument() { return printAgreementPaper(); }
     }" 
     @open-preview-modal.window="resetBreakdown(); previewRequestId = null; previewError = ''; previewLoading = false; openPreview = true; refreshPaper()"
     @open-lease-preview-loading.window="
        resetBreakdown();
        previewRequestId = $event.detail.requestId;
        previewLoading = true;
        previewRendering = false;
        previewError = '';
        openPreview = true;
        document.getElementById('modal-content').innerHTML = '';
        document.getElementById('modal-title').innerText = $event.detail.title || 'Document Preview';
        document.body.style.overflow = 'hidden';
     "
     @lease-preview-error.window="
        if ($event.detail.requestId !== previewRequestId) return;
        previewLoading = false;
        previewRendering = false;
        previewError = $event.detail.message;
     "
     @open-lease-preview.window="
        if ($event.detail.requestId && $event.detail.requestId !== previewRequestId) return;
        resetBreakdown($event.detail);
        previewError = '';
        openPreview = true;
        $nextTick(() => {
            if (!openPreview || ($event.detail.requestId && $event.detail.requestId !== previewRequestId)) return;
            document.getElementById('modal-content').innerHTML = $event.detail.content;
            document.getElementById('modal-title').innerText = $event.detail.title || 'Document Preview';
            document.body.style.overflow = 'hidden';
            refreshPaper();
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
                            <h3 id="modal-title" class="text-2xl font-black text-slate-800 tracking-tight">Agreement Preview</h3>
                            <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-bold">
                                Please review the document carefully
                            </p>
                        </div>
                        <button type="button" aria-label="Close document preview" @click="closeModal()" class="p-2 rounded-full hover:bg-gray-200 text-gray-400 transition-colors">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- 内容区 --}}
                    <div :aria-busy="previewLoading" style="max-height: 65vh; overflow: auto; padding: 24px; background: #e5e7eb;">
                        <x-loading-skeleton variant="document" label="Loading document preview..." x-show="previewLoading" class="max-w-3xl mx-auto bg-white p-6 sm:p-12 shadow-sm" />
                        <div x-show="previewError" role="alert" class="max-w-3xl mx-auto rounded-lg bg-white p-6 text-red-700" x-text="previewError"></div>
                        <iframe x-ref="agreementPaper" x-show="!previewLoading && !previewError" title="A4 document preview"
                            sandbox="allow-same-origin"
                            @load="resizeAgreementPaper($el); if (previewRendering && openPreview) { previewLoading = false; previewRendering = false; }"
                            style="display: block; width: 210mm; min-width: 210mm; height: 297mm; min-height: 297mm; margin: 0 auto; border: 0; background: white; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.18);"></iframe>
                    </div>
                    <div id="modal-content" hidden>
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
                            <x-form.secondary-button x-show="breakdownAllowed && !previewLoading && !previewError" @click="editBreakdown()">Set Rental Breakdown</x-form.secondary-button>
                            {{-- Print Button --}}
                            @canany(['owner-admin', 'document template.print'])
                            <button type="button" 
                                    @click="printDocument()" 
                                    :disabled="previewLoading || !!previewError"
                                    class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 shadow-lg shadow-indigo-100 transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                Print
                            </button>
                            @endcanany

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
    <x-modals.confirmation-modal id="rental-breakdown" title="Set Rental Breakdown" zIndex="z-[10000]">
        <x-form.form :async="true" :autofocus="false" @submit.prevent="applyBreakdown()" class="p-6 space-y-4" novalidate>
            <div>
                <x-form.input-label for="rental-total" style="text-transform: none" x-text="rentalFrequency ? 'Original ' + rentalFrequency + ' rental (RM)' : 'Original rental (RM)'" />
                <x-form.text-input id="rental-total" readonly x-bind:value="rentalTotal === null ? 'Not available' : formatRentalCents(rentalTotal)" />
                <p class="text-sm text-red-600" x-show="rentalBasisError" x-text="rentalBasisError"></p>
                <p class="text-sm text-amber-700" x-show="rentalTotal !== null && rentalTotal % 100 !== 0">The original rental includes non-zero decimals. Its exact amount will be retained; no rounding is applied.</p>
            </div>
            <div>
                <x-form.input-label for="basic-lease" value="Basic Lease (RM)" />
                <x-form.text-input id="basic-lease" inputmode="decimal" x-model="basicLease" />
            </div>
            <div>
                <x-form.input-label for="service-charge" value="Service Charge (RM)" />
                <x-form.text-input id="service-charge" inputmode="decimal" x-model="serviceCharge" />
            </div>
            <p class="text-sm text-gray-500">Excludes deposits and Agreement Fee. Applies only to this preview.</p>
            <p role="alert" class="text-sm text-red-600" x-text="breakdownError"></p>
            <div class="flex flex-wrap justify-end gap-2">
                <x-form.secondary-button x-show="appliedBreakdown" @click="removeBreakdown()">Remove Breakdown</x-form.secondary-button>
                <x-form.secondary-button @click="$dispatch('close-rental-breakdown')">Cancel</x-form.secondary-button>
                <x-form.primary-button type="submit">Apply</x-form.primary-button>
            </div>
        </x-form.form>
    </x-modals.confirmation-modal>
</div>
