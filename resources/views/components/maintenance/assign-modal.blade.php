@props(['maintenance', 'staffOptions' => [], 'staffCreateData' => null])
<x-modal name="assign-maintenance" :shake-on-backdrop="true" :centered="true" :overflow-visible="true">
    <div x-data="{
        availableStaff: @js($staffOptions),
        selectedAssignedStaff: @js($maintenance->assigned_staff_id ? [$maintenance->assigned_staff_id] : []),
        addAssignedStaff(staff) {
            if (!staff?.id || staff.management_user_id !== @js($staffCreateData['managementUserId'] ?? $maintenance->management_user_id)) return;
            const option = { value: String(staff.id), label: staff.name + ' - ' + staff.email };
            this.availableStaff = [...this.availableStaff.filter(existing => String(existing.value) !== option.value), option];
            this.selectedAssignedStaff = [option.value];
        }
    }" x-on:staff-created.window="addAssignedStaff($event.detail)">
    <x-form.form data-maintenance-form="assign" data-reload="true" :async="true" method="POST"
        :action="route('admin.maintenance.work-orders.assign', $maintenance)" class="space-y-4 p-6">
        <input type="hidden" name="version" value="{{ $maintenance->version }}">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold">Assign Contractor</h2>
            <button type="button" aria-label="Close Assign Contractor" :disabled="loading"
                x-on:click="$dispatch('close-modal', 'assign-maintenance')" class="rounded-md p-1 text-gray-400 hover:text-gray-600 disabled:opacity-50">
                <svg class="h-6 w-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <p class="text-sm text-gray-600">Select a staff member under your management account. Add external contractors as staff first.</p>
        <div>
            <div class="flex items-center justify-between gap-2">
                <x-form.input-label for="maintenance-assigned-staff" value="Staff" :required="true" />
                @canany(['super-admin', 'staff.create'])
                    <button type="button" :disabled="loading" x-on:click="$dispatch('close-modal', 'assign-maintenance'); $dispatch('open-modal', 'create-staff')"
                        class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 hover:underline disabled:opacity-50">Add New Staff</button>
                @endcanany
            </div>
            <x-form.input-select id="maintenance-assigned-staff" name="assigned_staff_id" :options="$staffOptions"
                :value="$maintenance->assigned_staff_id" x-model="selectedAssignedStaff" x-effect="options = availableStaff" disabled-when="loading" placeholder="Select a staff member" required />
            <p x-show="availableStaff.length === 0" class="mt-2 text-sm text-gray-500">No active staff are available. Add a staff member first.</p>
        </div>
        <p data-maintenance-error role="alert" class="text-red-700"></p>
        <div class="flex justify-end gap-2">
            <x-form.secondary-button type="button" x-on:click="$dispatch('close-modal', 'assign-maintenance')">Cancel</x-form.secondary-button>
            <x-form.primary-button loading="loading" disabledWhen="availableStaff.length === 0">Confirm Assignment</x-form.primary-button>
        </div>
    </x-form.form>
    </div>
</x-modal>
@if($staffCreateData)
    <x-staff.create-modal :roles="$staffCreateData['roles']" :management-id="$staffCreateData['managementId']"
        :management-user-id="$staffCreateData['managementUserId']" return-to="assign-maintenance" />
@endif
