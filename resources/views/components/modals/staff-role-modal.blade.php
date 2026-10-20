<template x-teleport="body">
    <div x-show="showRoleModal" x-cloak 
         class="fixed inset-0 z-[101] flex items-center justify-center p-4 sm:p-6"
         @click="
             isShaking = true; 
             setTimeout(() => isShaking = false, 400);
         ">

        {{-- Blur Overlay / Background --}}
        <div class="absolute inset-0 bg-gray-900 bg-opacity-50"></div>

        <!-- Modal Card -->
        <div @click.stop 
             class="relative bg-white rounded-[2rem] shadow-2xl max-w-xl w-full overflow-hidden transition-all duration-200 z-[102]"
             x-show="showRoleModal"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             :class="{ 'animate-shake border-2 border-indigo-500': isShaking }">
            
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="text-lg font-black text-slate-800">Create New Staff Role & Permissions</h3>
                <button type="button" @click="showRoleModal = false" class="text-gray-400 hover:text-rose-500 transition-colors text-2xl font-bold">&times;</button>
            </div>

            <!-- Custom Form Component -->
            <x-form.form action="{{ route('admin.roles.store') }}" method="POST" loading="loading"
                         @submit.prevent="
                            loading = true;
                            errorMessage = '';

                            let formData = new FormData($el);
                            let payload = {
                                name: newRoleName,
                                permissions: selectedPermissions,
                                user_id: formData.get('user_id')
                            };

                            fetch('{{ route('admin.roles.store') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify(payload)
                            })
                            .then(res => res.json().then(data => ({ status: res.status, body: data })))
                            .then(res => {
                                if (res.status === 200 || res.status === 201) {
                                    sessionStorage.setItem('role_success_message', 'Role created successfully!');
                                    window.location.reload();
                                } else {
                                    loading = false;
                                    errorMessage = res.body.message || 'Failed to create role.';
                                }
                            })
                            .catch(() => {
                                loading = false;
                                errorMessage = 'An error occurred. Please try again.';
                            })
                         ">
                <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                    {{-- Admin User Selection Field --}}
                    @if(auth()->user()->role === 'admin' || app(\Illuminate\Contracts\Auth\Access\Gate::class)->allows('super-admin'))
                        <div>
                            @php
                                $managementUsers = \App\Models\UserManagement::with('user')
                                    ->has('user')
                                    ->get()
                                    ->map(function($management) {
                                        $u = $management->user;
                                        return [
                                            'value' => (string) $u->id,            
                                            'label' => "{$u->name} ({$u->email})"    
                                        ];
                                    })
                                    ->toArray();
                            @endphp

                            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Assign Role To User / Management</label>
                            <x-form.input-select 
                                id="user_id" 
                                name="user_id" 
                                class="w-full"
                                :options="$managementUsers"
                                value-field="value"    
                                label-field="label"    
                                x-model="selectedTargetUserId"
                                :value="old('user_id')"
                            />
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase">Role Name</label>
                        <input type="text" x-model="newRoleName" class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <span x-text="errorMessage" class="text-xs text-red-600 mt-1 block" x-show="errorMessage"></span>
                    </div>

                    <!-- Permissions Checklist Section with Section-Level Master Toggle -->
                    <div class="space-y-4 pt-2 border-t border-gray-100">
                        <label class="block text-xs font-semibold text-gray-700 uppercase">Assign Initial Permissions</label>
                        
                        @foreach(config('permissions.modules', []) as $moduleName => $actions)
                            @php
                                // Flatten both regular strings and nested sub-actions into a single array list for JavaScript/Alpine
                                $flatPerms = [];
                                foreach ($actions as $actionKey => $actionVal) {
                                    if (is_array($actionVal)) {
                                        $flatPerms[] = "{$moduleName}.{$actionKey}";
                                        foreach ($actionVal as $subAction) {
                                            $flatPerms[] = "{$moduleName}.{$subAction}";
                                        }
                                    } else {
                                        $flatPerms[] = "{$moduleName}.{$actionVal}";
                                    }
                                }
                                $modulePermsJson = json_encode($flatPerms);
                            @endphp

                            <div class="p-3 rounded-lg border border-gray-100 bg-gray-50 space-y-3"
                                   x-data="{
                                       modulePerms: {{ $modulePermsJson }},
                                       get isAllChecked() {
                                           return this.modulePerms.every(p => selectedPermissions.includes(p));
                                       },
                                       toggleAll() {
                                           if (this.isAllChecked) {
                                               // Remove all permissions belonging to this module
                                               selectedPermissions = selectedPermissions.filter(p => !this.modulePerms.includes(p));
                                           } else {
                                               // Add all permissions belonging to this module without duplicates
                                               selectedPermissions = Array.from(new Set([...selectedPermissions, ...this.modulePerms]));
                                           }
                                       }
                                   }">
                            
                                <!-- Module Header with Master Toggle Switch -->
                                <div class="flex items-center justify-between pb-2 border-b border-gray-200/60">
                                    <h4 class="text-xs font-bold text-gray-600 uppercase tracking-wider flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                                        {{ ucfirst($moduleName) }} Module
                                    </h4>
                                    
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] font-semibold text-gray-500 uppercase">Select All</span>
                                        <label class="relative inline-flex items-center shrink-0 cursor-pointer">
                                            <input type="checkbox" 
                                                   @click="toggleAll()"
                                                   :checked="isAllChecked"
                                                   class="sr-only peer">
                                            <div class="w-8 h-4 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-indigo-600"></div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Individual Permission & Nested Toggles Grid -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach($actions as $actionKey => $actionVal)
                                        @if(is_array($actionVal))
                                            {{-- 1. Render Parent Action Toggle (e.g., Record Payment) --}}
                                            @php 
                                                $parentPermName = "{$moduleName}.{$actionKey}"; 
                                            @endphp
                                            <div class="flex items-center justify-between bg-white p-2.5 rounded-lg border border-gray-100 shadow-sm">
                                                <span class="text-xs font-medium text-gray-800 truncate capitalize mr-2">{{ $actionKey }}</span>
                                                <label class="relative inline-flex items-center shrink-0 cursor-pointer">
                                                    <input type="checkbox" 
                                                           value="{{ $parentPermName }}" 
                                                           x-model="selectedPermissions"
                                                           class="sr-only peer">
                                                    <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                                </label>
                                            </div>

                                            {{-- 2. Render Nested Sub-Action Toggles (e.g., Edit Penalty, Remove Penalty) --}}
                                            @foreach($actionVal as $subAction)
                                                @php 
                                                    $subPermName = "{$moduleName}.{$subAction}"; 
                                                @endphp
                                                <div class="flex items-center justify-between bg-indigo-50/40 p-2.5 rounded-lg border border-indigo-100/50 shadow-sm ml-2">
                                                    <span class="text-xs font-medium text-indigo-900 truncate capitalize mr-2">↳ {{ $subAction }}</span>
                                                    <label class="relative inline-flex items-center shrink-0 cursor-pointer">
                                                        <input type="checkbox" 
                                                               value="{{ $subPermName }}" 
                                                               x-model="selectedPermissions"
                                                               class="sr-only peer">
                                                        <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                                    </label>
                                                </div>
                                            @endforeach
                                        @else
                                            {{-- Standard Flat Action Toggle --}}
                                            @php 
                                                $permName = "{$moduleName}.{$actionVal}"; 
                                            @endphp
                                            <div class="flex items-center justify-between bg-white p-2.5 rounded-lg border border-gray-100 shadow-sm">
                                                <span class="text-xs font-medium text-gray-800 truncate capitalize mr-2">{{ $actionVal }}</span>
                                                <label class="relative inline-flex items-center shrink-0 cursor-pointer">
                                                    <input type="checkbox" 
                                                           value="{{ $permName }}" 
                                                           x-model="selectedPermissions"
                                                           class="sr-only peer">
                                                    <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                                </label>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="p-6 bg-gray-50/50 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" @click="showRoleModal = false" class="px-4 py-2 text-xs font-semibold text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                        Cancel
                    </button>
                    
                    <x-form.primary-button type="submit" loading="loading" class="px-4 py-2 text-xs font-semibold">
                        Save Role
                    </x-form.primary-button>
                </div>
            </x-form.form>
        </div>
    </div>
</template>