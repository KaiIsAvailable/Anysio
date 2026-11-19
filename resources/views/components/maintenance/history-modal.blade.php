@props(['leasable' => null])
<x-modal name="history-maintenance" max-width="7xl" :centered="true" :shake-on-backdrop="true">
    <div class="flex max-h-[calc(100dvh-3rem)] flex-col">
        <div class="flex shrink-0 items-start justify-between gap-4 border-b border-gray-100 p-6">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Maintenance History</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $leasable ? class_basename($leasable).': '.($leasable->name ?? $leasable->unit_no ?? $leasable->room_no) : 'All properties, units and rooms' }}</p>
            </div>
            <button type="button" aria-label="Close Maintenance History" x-on:click="$dispatch('close-modal', 'history-maintenance')" class="rounded-md p-1 text-gray-400 hover:text-gray-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500">
                <svg class="h-6 w-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <div data-maintenance-history-host class="min-h-0 overflow-y-auto p-6" aria-busy="false"></div>
        <template data-maintenance-history-loading>
            <x-loading-skeleton variant="statement" label="Loading maintenance history..." data-maintenance-history-skeleton />
            <div data-maintenance-history-error hidden role="alert" class="py-6 text-sm text-red-700">
                <p data-maintenance-history-error-message></p>
                <x-form.secondary-button type="button" data-maintenance-history class="mt-4">Retry</x-form.secondary-button>
            </div>
        </template>
        <div class="flex shrink-0 justify-end border-t border-gray-100 p-4">
            <x-form.secondary-button type="button" x-on:click="$dispatch('close-modal', 'history-maintenance')">Close</x-form.secondary-button>
        </div>
    </div>
</x-modal>
