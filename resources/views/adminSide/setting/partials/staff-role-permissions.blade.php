@php
    $initialRole = $roles->firstWhere('name', request('role_tab'))?->name ?? $roles->first()?->name ?? '';
    $roleOptions = $roles->map(fn ($role) => ['value' => $role->name, 'label' => $role->name])->all();
@endphp
<div x-show="activeTab === 'permissions'" x-cloak
     x-data="{ roleTab: @js($initialRole), editingPermissions: false, savingPermissions: false, showRoleModal: false, newRoleName: '', selectedPermissions: [], isLoading: false, errorMessage: '', isShaking: false }">
    <section class="bg-white shadow-sm rounded-lg border border-gray-200 p-4 sm:p-5 space-y-4">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-medium text-gray-900">{{ __('Staff Role Permissions') }}</h3>
                <p class="text-sm text-gray-500">{{ __('Choose a role to control its staff access. Staff role permissions are independent of your account permissions.') }}</p>
            </div>
            @canany(['super-admin', 'settings.create new role'])
                <button type="button" @click="showRoleModal = true" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">{{ __('Create New Role') }}</button>
            @endcanany
        </header>

        <x-modals.staff-role-modal />

        @if($roles->isEmpty())
            <p class="py-6 text-center text-sm text-gray-500">{{ __('No roles found. Create a staff role to get started.') }}</p>
        @else
            <div class="w-full sm:max-w-lg">
                <label for="staff-permission-role" class="block text-xs font-medium text-gray-600 mb-1">{{ __('Staff Role') }}</label>
                <x-form.input-select id="staff-permission-role" name="staff_role" :options="$roleOptions" :value="$initialRole" placeholder="{{ __('Search roles...') }}"
                    @change="if ($event.detail?.value) roleTab = $event.detail.value" />
            </div>

            <form action="{{ route('admin.settings.user-role-permissions.update') }}" method="POST" @submit="savingPermissions = true; $dispatch('form-loading', true)" @pageshow.window="savingPermissions = false" class="space-y-3">
                @csrf
                @method('PUT')
                <input type="hidden" name="active_tab" value="permissions">
                <input type="hidden" name="role_tab" :value="roleTab">

                @foreach($roles as $role)
                    @php $grants = $role->permissions->where('guard_name', 'web')->pluck('name')->values()->all(); @endphp
                    <div x-show="roleTab === @js($role->name)" x-cloak class="space-y-3"
                         x-data="{ selected: @js($grants), original: @js($grants), search: '' }"
                         @reset-staff-permissions.window="selected = [...original]">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $role->name }}</p>
                                <p class="text-xs text-gray-500"><span x-text="selected.length"></span> {{ __('permissions selected') }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <input type="search" x-model="search" aria-label="{{ __('Find module') }}" placeholder="{{ __('Find module...') }}" class="w-36 sm:w-48 rounded-md border-gray-300 text-sm py-1.5 focus:ring-indigo-500 focus:border-indigo-500">
                                @canany(['super-admin', 'settings.edit user role'])
                                    <button type="button" x-show="!editingPermissions" @click="editingPermissions = true" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">{{ __('Edit Permissions') }}</button>
                                @endcanany
                                @canany(['super-admin', 'settings.delete user role'])
                                    <button type="button" :disabled="savingPermissions"
                                            @click="if (confirm(@js(__('Delete this staff role?')))) document.getElementById('delete-role-form-{{ $role->id }}').submit()"
                                            class="text-sm font-medium text-red-600 hover:text-red-800 disabled:opacity-50">{{ __('Delete Role') }}</button>
                                @endcanany
                            </div>
                        </div>

                        <x-permissions.module-grid :modules="$modules" :role-id="$role->id" editing-state="editingPermissions" />
                    </div>
                @endforeach

                @canany(['super-admin', 'settings.edit user role'])
                    <div x-show="editingPermissions" x-cloak class="sticky bottom-0 bg-white border-t border-gray-200 py-3 flex justify-end items-center gap-4">
                        <button type="button" @click="$dispatch('reset-staff-permissions'); editingPermissions = false" :disabled="savingPermissions" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</button>
                        <x-form.primary-button loading="loading">{{ __('Save Permissions') }}</x-form.primary-button>
                    </div>
                @endcanany
            </form>

            @foreach($roles as $role)
                <form id="delete-role-form-{{ $role->id }}" action="{{ route('admin.roles.destroy', $role->id) }}" method="POST" class="hidden">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="active_tab" value="permissions">
                </form>
            @endforeach
        @endif
    </section>
</div>
