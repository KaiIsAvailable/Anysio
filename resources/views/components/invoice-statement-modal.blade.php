@props(['endpoint'])

<x-modal name="invoice-statement" maxWidth="7xl" :shake-on-backdrop="true">
    <div x-data="invoiceStatement(@js($endpoint))" x-init="$watch('show', value => value ? openStatement() : closeStatement())"
        role="dialog" aria-modal="true" aria-labelledby="statement-modal-title">
        <div class="flex items-center justify-between gap-3 px-4 sm:px-8 py-5 border-b">
            <h2 id="statement-modal-title" x-ref="title" tabindex="-1" class="text-lg font-bold text-slate-900">Invoice Statement</h2>
            <button type="button" x-ref="closeButton" x-on:click="$dispatch('close')"
                class="text-gray-400 hover:text-gray-600 transition-colors" aria-label="Close Invoice Statement">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="min-w-0 overflow-x-hidden overflow-y-auto overscroll-contain px-4 sm:px-8 py-4" style="max-height: calc(100dvh - 10rem)" :aria-busy="statementLoading">
            {{-- Search controls are temporarily hidden until the existing project components are reused.
            <template x-if="filtersReady && show">
                <x-invoice-statement-filters :endpoint="$endpoint" />
            </template>
            --}}
            <div x-show="error" x-cloak role="alert" class="p-4 text-red-700">
                <p x-text="error"></p>
                <button type="button" @click="load(lastUrl)" class="mt-2 underline">Retry</button>
            </div>
            <div x-ref="content" @click="navigate($event)"></div>
        </div>
    </div>
</x-modal>
