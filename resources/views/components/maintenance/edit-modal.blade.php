@props(['maintenance'])
<x-modal name="edit-maintenance" :centered="true" :overflow-visible="true" :shake-on-backdrop="true">
    <x-form.form method="PUT" :async="true" :autofocus="false" data-maintenance-form="edit" data-reload="true"
        :action="route('admin.maintenance.work-orders.update', $maintenance)" enctype="multipart/form-data" class="max-h-[calc(100dvh-3rem)] space-y-6 overflow-y-auto p-6 sm:p-8">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Edit Maintenance</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $maintenance->maintenance_number }}</p>
            </div>
            <button type="button" aria-label="Close Edit Maintenance" :disabled="loading"
                x-on:click="$dispatch('close-modal', 'edit-maintenance')" class="rounded-md p-1 text-gray-400 hover:text-gray-600 disabled:opacity-50">
                <svg class="h-6 w-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <input type="hidden" name="version" value="{{ $maintenance->version }}">
        <div>
            <x-form.input-label for="maintenance-edit-lease" :value="$maintenance->lease ? 'Lease' : 'Property / Unit / Room'" />
            <x-form.text-input id="maintenance-edit-lease" readonly class="bg-gray-50 text-gray-600"
                :value="$maintenance->lease ? ($maintenance->lease->tenant?->user?->name ?? 'Tenant').' · '.$maintenance->lease->leasable_name.' - '.$maintenance->lease->leasable_type_label.' · '.($maintenance->lease->start_date?->format('d/m/Y') ?? 'Not recorded').' to '.($maintenance->lease->end_date?->format('d/m/Y') ?? 'Not recorded') : $maintenance->leasable_name.' - '.$maintenance->leasable_type_label" />
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <x-form.input-label for="maintenance-edit-item" value="Item" />
                <x-form.text-input id="maintenance-edit-item" :value="$maintenance->title" readonly class="bg-gray-50 text-gray-600" />
            </div>
            <div>
                <x-form.input-label for="maintenance-edit-qty" value="Quantity" :required="true" />
                <x-form.text-input id="maintenance-edit-qty" type="number" name="qty" :value="$maintenance->qty ?? 1" min="1" max="4294967295" step="1" required />
            </div>
        </div>
        <div><x-form.input-label for="maintenance-edit-desc" value="Problem description" :required="true" /><x-form.textarea id="maintenance-edit-desc" name="desc" required maxlength="20000" rows="4">{{ $maintenance->desc }}</x-form.textarea></div>
        <div><x-form.input-label for="maintenance-edit-location" value="Location details" :required="$maintenance->source_type !== 'refund'" /><x-form.text-input id="maintenance-edit-location" name="location_detail" :value="$maintenance->location_detail" :required="$maintenance->source_type !== 'refund'" maxlength="255" /></div>
        <x-maintenance.files :existing-files="$maintenance->attachments->where('purpose', 'before')" />
        <p data-maintenance-error role="alert" class="whitespace-pre-line text-sm text-red-700"></p>
        <div class="flex justify-end gap-2">
            <x-form.secondary-button type="button" ::disabled="loading" x-on:click="$dispatch('close-modal', 'edit-maintenance')">Cancel</x-form.secondary-button>
            <x-form.primary-button loading="loading">Update Maintenance</x-form.primary-button>
        </div>
    </x-form.form>
</x-modal>
