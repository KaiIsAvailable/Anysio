@props(['maintenance'])
<x-modal name="complete-maintenance" :centered="true" :shake-on-backdrop="true">
    <x-form.form data-maintenance-form="complete" data-reload="true" :async="true" method="POST"
        :action="route('admin.maintenance.work-orders.complete', $maintenance)" class="space-y-4 p-6">
        <input type="hidden" name="version" value="{{ $maintenance->version }}">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold">Confirm completion</h2>
            <button type="button" aria-label="Close Confirm Completion" :disabled="loading"
                x-on:click="$dispatch('close-modal', 'complete-maintenance')" class="rounded-md p-1 text-gray-400 hover:text-gray-600 disabled:opacity-50">
                <svg class="h-6 w-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <p class="text-sm text-gray-600">Evidence is optional. @if($maintenance->ticket_id)Saved completion evidence will be visible to the tenant in the original conversation.@else Evidence is saved with this maintenance record.@endif Completion cannot be reopened or edited.</p>
        <x-maintenance.files />
        <p data-maintenance-error role="alert" class="whitespace-pre-line text-red-700"></p>
        <div class="flex justify-end gap-2">
            <x-form.secondary-button type="button" ::disabled="loading" x-on:click="$dispatch('close-modal', 'complete-maintenance')">Cancel</x-form.secondary-button>
            <x-form.primary-button loading="loading">Confirm completed</x-form.primary-button>
        </div>
    </x-form.form>
</x-modal>
