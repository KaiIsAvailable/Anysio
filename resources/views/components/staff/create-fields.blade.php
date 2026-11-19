@props(['roles' => collect(), 'idPrefix' => '', 'managementUserId' => null, 'dynamicManagement' => false])
<!-- Staff Name -->
<div>
    <x-form.input-label :for="$idPrefix.'name'" value="Full Name" required />
    <x-form.text-input :id="$idPrefix.'name'" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autocomplete="name" />
    <x-form.input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<!-- Email Address -->
<div>
    <x-form.input-label :for="$idPrefix.'email'" value="Email Address" required />
    <x-form.text-input :id="$idPrefix.'email'" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required autocomplete="username" />
    <x-form.input-error :messages="$errors->get('email')" class="mt-2" />

    <!-- Checkbox to auto-verify email on creation -->
    <div class="mt-3 flex items-center">
        <input id="{{ $idPrefix }}verify_email_now" name="verify_email_now" type="checkbox" value="1" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded" {{ old('verify_email_now') ? 'checked' : '' }}>
        <label for="{{ $idPrefix }}verify_email_now" class="ml-2 block text-sm text-gray-700">
            Mark email as verified immediately
        </label>
    </div>
</div>

<div>
    <x-form.input-label :for="$idPrefix.'phone'" value="Phone Number" />
    <x-form.text-input :id="$idPrefix.'phone'" name="phone" type="tel" :value="old('phone')" autocomplete="tel" inputmode="numeric" pattern="[0-9]*" maxlength="20" oninput="this.value = this.value.replace(/[^0-9]/g, '')" class="mt-1 block w-full" />
    <x-form.input-error :messages="$errors->get('phone')" class="mt-2" />
</div>

<!-- Password -->
<div>
    <x-form.input-label :for="$idPrefix.'password'" value="Password" required />
    <x-form.password-input :id="$idPrefix.'password'" name="password" class="mt-1 block w-full" required autocomplete="new-password" />
    <x-form.input-error :messages="$errors->get('password')" class="mt-2" />
</div>

<!-- Confirm Password -->
<div>
    <x-form.input-label :for="$idPrefix.'password_confirmation'" value="Confirm Password" required />
    <x-form.password-input :id="$idPrefix.'password_confirmation'" name="password_confirmation" class="mt-1 block w-full" required autocomplete="new-password" />
    <x-form.input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
</div>

<!-- Staff Role Section with Quick-Add Modal -->
<div x-data="{ 
    showRoleModal: false, 
    newRoleName: '', 
    roles: @js(collect($roles)->map(fn ($role) => is_string($role) ? ['name' => $role] : ['name' => $role->name])->values()),
    selectedRole: @js(old('role') ? [old('role')] : []),
    selectedPermissions: [],
    isLoading: false,
    errorMessage: '',
    isShaking: false
}"
@if($dynamicManagement)
x-effect="roles = selectedManagementRoles; selectedRole = []"
@endif
x-on:reset.window="if ($event.target === $el.closest('form')) selectedRole = []"
@staff-role-created.window="
    const role = $event.detail.role;
    @if($dynamicManagement)
    if (role.team_id !== selectedManagementUserId) return;
    @endif
    if (!roles.some(existing => existing.name === role.name)) roles.push({ name: role.name });
    $nextTick(() => { selectedRole = [role.name]; });
">
    <div class="flex items-center justify-between">
        <x-form.input-label :for="$idPrefix.'role'" value="Staff Position / Role" required />
        
        <!-- Quick + Button to trigger modal -->
        @canany(['super-admin', 'settings.create new role'])
        <button type="button" @click="showRoleModal = true" @if($dynamicManagement) :disabled="!selectedManagementUserId" @endif class="inline-flex items-center text-xs font-semibold text-indigo-600 hover:text-indigo-800 gap-1 disabled:opacity-50">
            <svg class="w-4 h-4 bg-indigo-50 rounded-full p-0.5 border border-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Add New Role
        </button>
        @endcanany
    </div>

    <!-- Select Dropdown using your custom x-form.input-select component -->
    <div class="mt-1">
        <x-form.input-select 
            :id="$idPrefix.'role'"
            name="role" 
            class="w-full"
            :options="$roles"
            value-field="name"
            label-field="name"
            x-model="selectedRole"
            x-effect="options = roles.map(role => ({ value: role.name, label: role.name }))"
            :value="old('role')"
            required 
        />
    </div>
    <x-form.input-error :messages="$errors->get('role')" class="mt-2" />

    <!-- EMPTY STATE NOTICE -->
    <p x-show="roles.length === 0" class="text-xs text-amber-600 mt-1">
        No custom roles found. Click "Add New Role" above to create one.
    </p>

    @canany(['super-admin', 'settings.create new role'])
    <x-modals.staff-role-modal :reload-after-create="false" :management-user-id="$managementUserId" :dynamic-management="$dynamicManagement" />
    @endcanany
</div>

