<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Edit Manager</h1>
                <p class="mt-2 text-sm text-gray-500">Update account details and access permissions.</p>
            </div>

            <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                <x-form.form action="{{ route('admin.userManagement.update', $userMgnt->id) }}" method="POST" class="p-8">
                    @method('PUT')

                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <x-form.input-label value="Full Name" class="mb-2" />
                            <x-form.text-input name="name" value="{{ old('name', $userMgnt->user->name) }}" 
                                   class="w-full" />
                        </div>

                        <div>
                            <x-form.input-label value="Email Address" class="mb-2" />
                            <x-form.text-input type="email" name="email" value="{{ old('email', $userMgnt->user->email) }}" 
                                   class="w-full" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-form.input-label value="Base User Type" class="mb-1" />
                                <x-form.input-select name="role_type" id="role_type" :value="old('role_type', $userMgnt->user->role)" :options="['admin' => 'Admin', 'owner' => 'Owner', 'agent' => 'Agent']" placeholder="-- Select Type --" required class="w-full" />
                                <p class="mt-1 text-xs text-gray-400">Determines the role in the 'users' table.</p>
                            </div>

                            <div>
                                <x-form.input-label value="Management Role" class="mb-1" />
                                <x-form.input-select name="pms_role" id="pms_role" :value="old('pms_role', $userMgnt->role)" :options="['admin' => 'Admin', 'ownerAdmin' => 'Owner Admin', 'agentAdmin' => 'Agent Admin']" placeholder="-- Auto-selected --" required 
                                        class="mt-1 block w-full rounded-lg border-gray-100 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                                        style="pointer-events: none; touch-action: none;" 
                                        tabindex="-1" />
                                <p class="mt-1 text-xs text-gray-400">Determines access level in Management (Auto-synced).</p>
                            </div>
                        </div>

                        <div>
                            <x-form.input-label value="Subscription Status" class="mb-1" />
                            <x-form.input-select name="subscription_status" id="subscription_status" :value="old('subscription_status', $userMgnt->subscription_status)" :options="['active' => 'Active', 'inactive' => 'Inactive']" required 
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 {{ $userMgnt->subscription_status == 'inactive' ? 'bg-red-50 text-red-600' : '' }}" />
                            <p class="mt-1 text-xs text-gray-400">Set to 'Inactive' to suspend user access immediately.</p>
                        </div>
                    </div>

                    <div class="mt-10 flex items-center justify-end space-x-4 border-t border-gray-100 pt-6">
                        <a href="{{ route('admin.userManagement.index') }}" class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</a>
                        <x-form.primary-button type="submit" loading="loading" class="px-6 py-2.5">
                            Save Changes
                        </x-form.primary-button>
                    </div>
                </x-form.form>
            </div>
        </div>
    </div>
</x-app-layout>