@props(['endpoint'])

<x-modal name="invoice-statement" maxWidth="7xl">
    <div x-data="invoiceStatement(@js($endpoint))" x-init="$watch('show', value => value ? openStatement() : closeStatement())"
        role="dialog" aria-modal="true" aria-labelledby="statement-modal-title">
        <div class="flex items-center justify-between gap-3 p-4 border-b">
            <h2 id="statement-modal-title" x-ref="title" tabindex="-1" class="text-lg font-bold text-slate-900">Invoice Statement</h2>
            <x-form.secondary-button x-ref="closeButton" x-on:click="$dispatch('close')" aria-label="Close Invoice Statement">
                {{ __('Close') }}
            </x-form.secondary-button>
        </div>
        <div class="overflow-y-auto overscroll-contain" style="max-height: calc(100dvh - 10rem)" :aria-busy="statementLoading">
            <template x-if="filtersReady && show">
                <x-invoice-statement-filters :endpoint="$endpoint" />
            </template>
            <p x-show="statementLoading" role="status" class="p-6 text-slate-600">Loading statement…</p>
            <div x-show="error" x-cloak role="alert" class="p-4 text-red-700">
                <p x-text="error"></p>
                <button type="button" @click="load(lastUrl)" class="mt-2 underline">Retry</button>
            </div>
            <div x-ref="content" @click="navigate($event)"></div>
        </div>
    </div>
</x-modal>
