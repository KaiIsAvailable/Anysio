@props(['manual' => false, 'leasableOptions' => [], 'itemOptions' => []])
<x-modal name="create-maintenance" :shake-on-backdrop="true">
    <x-form.form data-maintenance-form="create" :async="true" data-reload="{{ $manual ? 'true' : 'false' }}"
        :action="$manual ? route('admin.maintenance.work-orders.store-from-list') : ''" class="max-h-[calc(100dvh-3rem)] space-y-4 overflow-y-auto p-6" method="POST" enctype="multipart/form-data">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold">Create Maintenance</h2>
            <button type="button" aria-label="Close" :disabled="loading" x-on:click="$dispatch('close-modal', 'create-maintenance')" class="rounded-md p-1 text-gray-400 hover:text-gray-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500 disabled:opacity-50">
                <svg class="h-6 w-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <p class="text-sm text-gray-600">{{ $manual ? 'Select a property, unit or room to record maintenance. A lease is not required.' : 'Create one work order covering this conversation. Include all verified repair or cleaning details in its description.' }}</p>
        @if($manual)
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2" x-data="{
                maintenanceLocations: @js($leasableOptions),
                locationType: @js(old('leasable_type') ? [old('leasable_type')] : []),
                locationId: @js(old('leasable_id') ? [old('leasable_id')] : [])
            }" x-init="$watch('locationType', () => locationId = [])"
                x-on:reset.window="if ($event.target === $el.closest('form')) { locationType = []; locationId = []; }">
                <div>
                    <x-form.input-label for="maintenance-location-type" value="Select Property Type" :required="true" />
                    <x-form.input-select id="maintenance-location-type" name="leasable_type"
                        :options="['property' => 'Property', 'unit' => 'Unit', 'room' => 'Room']"
                        x-model="locationType" disabled-when="loading" :value="old('leasable_type')" placeholder="Select property type" required />
                </div>
                <div>
                    <x-form.input-label for="maintenance-leasable" value="Select Property" :required="true" />
                    <x-form.input-select id="maintenance-leasable" name="leasable_id" :options="[]"
                        x-effect="options = maintenanceLocations.filter(location => location.type === locationType[0])"
                        x-model="locationId" disabled-when="loading || !locationType.length" :value="old('leasable_id')"
                        placeholder="Select property" required />
                </div>
                @if(empty($leasableOptions))<p class="text-sm text-gray-500 sm:col-span-2">No properties, units or rooms are available under your management account.</p>@endif
            </div>
        @endif
        @unless($manual)<p data-maintenance-location class="text-sm font-medium"></p>@endunless
        <x-maintenance.item-fields :item-options="$itemOptions" />
        <div><x-form.input-label for="maintenance-description" value="Problem description" :required="true" /><x-form.textarea id="maintenance-description" name="desc" required maxlength="20000" rows="3">{{ old('desc') }}</x-form.textarea></div>
        <div><x-form.input-label for="maintenance-location" value="Location within this property / unit / room" :required="true" /><x-form.text-input id="maintenance-location" name="location_detail" :value="old('location_detail')" required maxlength="255" /></div>
        @unless($manual)
            <fieldset class="space-y-2"><legend class="text-sm font-medium">Use existing chat evidence (optional)</legend><div data-maintenance-source-files class="max-h-40 overflow-auto space-y-2"></div></fieldset>
            <p data-maintenance-existing class="text-xs text-gray-600"></p>
        @endunless
        <x-maintenance.files />
        <p data-maintenance-error role="alert" class="whitespace-pre-line text-sm text-red-700"></p>
        <div class="flex justify-end gap-2"><x-form.secondary-button type="button" x-on:click="$dispatch('close-modal', 'create-maintenance')">Cancel</x-form.secondary-button><x-form.primary-button loading="loading" :disabledWhen="$manual && empty($leasableOptions) ? 'true' : null">Create Maintenance</x-form.primary-button></div>
    </x-form.form>
</x-modal>
<x-modals.add-fee-type-modal />
