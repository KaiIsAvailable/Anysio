<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="mb-8">
                <nav class="flex mb-2" aria-label="Breadcrumb">
                    <a href="{{ route('admin.staff.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 flex items-center transition-colors">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Back to Staff List
                    </a>
                </nav>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Edit Staff Member</h1>
                <p class="mt-2 text-sm text-gray-500">Update account information and access permissions for this staff.</p>
            </div>

            <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                <form action="{{ route('admin.staff.update', $staff->id) }}" method="POST" class="p-8">
                    @csrf
                    @method('PUT')

                    <div class="space-y-8">
                        <div class="space-y-6">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">Profile Information</h3>
                            
                            <div class="grid grid-cols-1 gap-6 pl-4">
                                <div>
                                    <label for="name" class="block text-sm font-bold text-slate-700 mb-1.5">Full Name</label>
                                    <input type="text" name="name" id="name" 
                                           value="{{ old('name', $staff->user->name) }}"
                                           class="block w-full px-4 py-3 bg-gray-50 border border-gray-300 text-slate-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 transition-all placeholder-gray-400"
                                           placeholder="John Doe" required>
                                    @error('name') <p class="mt-1.5 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="email" class="block text-sm font-bold text-slate-700 mb-1.5">Email Address</label>
                                    <input type="email" name="email" id="email" 
                                           value="{{ old('email', $staff->user->email) }}"
                                           class="block w-full px-4 py-3 bg-gray-50 border border-gray-300 text-slate-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 transition-all placeholder-gray-400"
                                           placeholder="john@example.com" required>
                                    @error('email') <p class="mt-1.5 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6 pt-6 border-t border-gray-100">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">Access Control</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pl-4">
                                <div>
                                    <label for="role" class="block text-sm font-bold text-slate-700 mb-1.5">Position / Role</label>
                                    <select name="role" id="role" 
                                            class="block w-full px-4 py-3 bg-gray-50 border border-gray-300 text-slate-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                                        <option value="Staff" {{ old('role', $staff->role) == 'Staff' ? 'selected' : '' }}>Staff</option>
                                        <option value="Supervisor" {{ old('role', $staff->role) == 'Supervisor' ? 'selected' : '' }}>Supervisor</option>
                                        <option value="Manager" {{ old('role', $staff->role) == 'Manager' ? 'selected' : '' }}>Manager</option>
                                    </select>
                                    @error('role') <p class="mt-1.5 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="is_active" class="block text-sm font-bold text-slate-700 mb-1.5">Account Status</label>
                                    <select name="is_active" id="is_active" 
                                            class="block w-full px-4 py-3 bg-gray-50 border border-gray-300 text-slate-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                                        <option value="active" {{ old('is_active', $staff->is_active) == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ old('is_active', $staff->is_active) == 'inactive' ? 'selected' : '' }}>Inactive / Suspended</option>
                                    </select>
                                    @error('is_active') <p class="mt-1.5 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-10 pt-8 border-t border-gray-100 flex justify-end gap-3">
                        <a href="{{ route('admin.staff.index') }}" 
                           class="px-6 py-2.5 text-sm font-medium text-slate-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all shadow-sm">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="px-10 py-2.5 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-md hover:shadow-lg transition-all transform active:scale-95">
                            Update Staff Details
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-6 bg-amber-50 rounded-xl p-5 border border-amber-200">
                <div class="flex">
                    <svg class="h-5 w-5 text-amber-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <h4 class="text-sm font-bold text-amber-800">Note on Sensitivity</h4>
                        <p class="text-xs text-amber-700 mt-1 leading-relaxed">Changing a staff member's email will require them to log in with the new credentials. Inactive staff accounts will be immediately restricted from accessing the management portal.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>