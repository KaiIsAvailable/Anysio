@props(['roles' => collect(), 'managementId' => null, 'managementUserId' => null, 'returnTo' => null, 'managementOptions' => [], 'show' => false])
<div x-data="{
    savingStaff: false,
    staffError: '',
    managementOptions: @js($managementOptions),
    selectedManagement: @js(old('user_mgnt_id') ? [old('user_mgnt_id')] : []),
    get selectedManagementAccount() {
        return this.managementOptions.find(account => account.value === this.selectedManagement[0]);
    },
    get selectedManagementUserId() { return this.selectedManagementAccount?.user_id || ''; },
    get selectedManagementRoles() { return this.selectedManagementAccount?.roles || []; },
    async submitStaff(form) {
        if (this.savingStaff) return;
        const body = new FormData(form);
        this.savingStaff = true;
        this.staffError = '';
        try {
            const response = await fetch(form.action, {
                method: 'POST', body, redirect: 'manual',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (response.type === 'opaqueredirect') throw new Error('Your session or permissions changed. Refresh the page and try again.');
            const data = await response.json();
            if (!response.ok || !data.staff?.id) throw new Error(Object.values(data.errors || {}).flat().join('\n') || data.message || 'Unable to create staff. Please try again.');
            this.$dispatch('staff-created', data.staff);
            this.$dispatch('session-status', { type: 'success', message: data.message });
            form.reset();
            this.$dispatch('close-modal', 'create-staff');
        } catch (error) {
            this.staffError = error.message || 'Unable to create staff. Please try again.';
        } finally {
            this.savingStaff = false;
        }
    }
}" x-on:pageshow.window="savingStaff = false">
    <x-modal name="create-staff" :show="$show" :centered="true" :overflow-visible="true" :shake-on-backdrop="true" :return-to="$returnTo">
        <x-form.form :action="route('admin.staff.store')" method="POST" :async="true" :autofocus="false"
            x-on:submit.prevent.stop="submitStaff($el)" class="space-y-6 p-6 sm:p-8">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold text-slate-900">Add New Staff</h2>
                <button type="button" aria-label="Close Add New Staff" :disabled="savingStaff"
                    x-on:click="$dispatch('close-modal', 'create-staff')" class="rounded-md p-1 text-gray-400 hover:text-gray-600 disabled:opacity-50">
                    <svg class="h-6 w-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            @if($managementId || $managementOptions)
                @if($managementOptions)
                    <div>
                        <x-form.input-label for="quick-staff-management" value="Assign to Management Account" required />
                        <x-form.input-select id="quick-staff-management" name="user_mgnt_id" :options="$managementOptions"
                            x-model="selectedManagement" disabled-when="savingStaff" placeholder="Select a management account" required />
                    </div>
                @else
                    <input type="hidden" name="user_mgnt_id" value="{{ $managementId }}">
                @endif
                <fieldset :disabled="savingStaff" class="space-y-6">
                    <x-staff.create-fields :roles="$roles" id-prefix="quick-staff-" :management-user-id="$managementUserId"
                        :dynamic-management="!empty($managementOptions)" />
                </fieldset>
            @else
                <p role="status" class="text-sm text-gray-600">A management profile is required before adding staff.</p>
            @endif
            <p x-show="staffError" x-text="staffError" role="alert" class="whitespace-pre-line text-sm text-red-700"></p>
            <div class="flex justify-end gap-2">
                <x-form.secondary-button type="button" ::disabled="savingStaff" x-on:click="$dispatch('close-modal', 'create-staff')">Cancel</x-form.secondary-button>
                <x-form.primary-button loading="savingStaff" :disabledWhen="$managementOptions ? '!selectedManagementUserId' : (!$managementId ? 'true' : null)">Create Staff</x-form.primary-button>
            </div>
        </x-form.form>
    </x-modal>
</div>
