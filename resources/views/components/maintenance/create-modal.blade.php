<x-modal name="create-maintenance" :shake-on-backdrop="true">
    <form data-maintenance-form="create" class="space-y-4 p-6" method="POST" enctype="multipart/form-data">
        @csrf
        <h2 class="text-xl font-semibold">Create Maintenance</h2>
        <p class="text-sm text-gray-600">Create a separate work order for each verified repair or cleaning item. The original conversation is retained.</p>
        <p data-maintenance-location class="text-sm font-medium"></p>
        <div><x-form.input-label value="Item" /><x-form.text-input name="title" required maxlength="255" class="w-full" /></div>
        <div><x-form.input-label value="Problem description" /><textarea name="desc" required maxlength="20000" class="w-full rounded-lg border-gray-300" rows="3"></textarea></div>
        <div><x-form.input-label value="Location within this property / unit / room" /><x-form.text-input name="location_detail" required maxlength="255" class="w-full" /></div>
        <fieldset class="space-y-2"><legend class="text-sm font-medium">Use existing chat evidence (optional)</legend><div data-maintenance-source-files class="max-h-40 overflow-auto space-y-2"></div></fieldset>
        <p data-maintenance-existing class="text-xs text-gray-600"></p>
        <x-maintenance.files />
        <p data-maintenance-error role="alert" class="whitespace-pre-line text-sm text-red-700"></p>
        <div class="flex justify-end gap-2"><x-form.secondary-button type="button" x-on:click="$dispatch('close-modal', 'create-maintenance')">Close</x-form.secondary-button><x-form.primary-button>Create Maintenance</x-form.primary-button></div>
    </form>
</x-modal>
