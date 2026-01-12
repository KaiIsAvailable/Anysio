<script src="{{ asset('js/filament/userManagement.js') }}"></script>
<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Edit Manager</h1>
                <p class="mt-2 text-sm text-gray-500">Update account details and access permissions.</p>
            </div>

            <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                <form action="{{ route('admin.userManagement.update', $userMgnt->id) }}" method="POST" class="p-8">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                            <input type="text" name="name" value="{{ old('name', $userMgnt->user->name) }}" 
                                   class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                            <input type="email" name="email" value="{{ old('email', $userMgnt->user->email) }}" 
                                   class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Base User Type</label>
                                <select name="role_type" id="role_type" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">-- Select Type --</option>
                                    {{-- 这里使用 $userMgnt->user->role --}}
                                    <option value="admin" {{ $userMgnt->user->role == 'admin' ? 'selected' : '' }}>Admin</option>
                                    <option value="owner" {{ $userMgnt->user->role == 'owner' ? 'selected' : '' }}>Owner</option>
                                    <option value="agent" {{ $userMgnt->user->role == 'agent' ? 'selected' : '' }}>Agent</option>
                                </select>
                                <p class="mt-1 text-xs text-gray-400">Determines the role in the 'users' table.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Management Role</label>
                                <select name="pms_role" id="pms_role" required 
                                        class="mt-1 block w-full rounded-lg border-gray-100 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                                        style="pointer-events: none; touch-action: none;" 
                                        tabindex="-1">
                                    <option value="">-- Auto-selected --</option>
                                    {{-- 这里使用 $userMgnt->role --}}
                                    <option value="admin" {{ $userMgnt->role == 'admin' ? 'selected' : '' }}>Admin</option>
                                    <option value="ownerAdmin" {{ $userMgnt->role == 'ownerAdmin' ? 'selected' : '' }}>Owner Admin</option>
                                    <option value="agentAdmin" {{ $userMgnt->role == 'agentAdmin' ? 'selected' : '' }}>Agent Admin</option>
                                </select>
                                <p class="mt-1 text-xs text-gray-400">Determines access level in Management (Auto-synced).</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Subscription Status</label>
                            <select name="subscription_status" id="subscription_status" required 
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 {{ $userMgnt->subscription_status == 'inactive' ? 'bg-red-50 text-red-600' : '' }}">
                                
                                <option value="active" {{ $userMgnt->subscription_status == 'active' ? 'selected' : '' }}>
                                    Active
                                </option>
                                
                                <option value="inactive" {{ $userMgnt->subscription_status == 'inactive' ? 'selected' : '' }}>
                                    Inactive
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-gray-400">Set to 'Inactive' to suspend user access immediately.</p>
                        </div>
                    </div>

                    <div class="mt-10 flex items-center justify-end space-x-4 border-t border-gray-100 pt-6">
                        <a href="{{ route('admin.userManagement.index') }}" class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</a>
                        <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-md transition-all">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>