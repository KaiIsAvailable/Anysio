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
                <p class="mt-2 text-sm text-gray-500">Update account information, verification status, and access permissions for this staff.</p>
            </div>

            <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                <x-form.form action="{{ route('admin.staff.update', $staff->id) }}" method="POST" class="p-8" loading="loading">
                    @csrf
                    @method('PUT')

                    <div class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 pl-4">
                            <!-- Name -->
                            <div>
                                <x-form.input-label for="name" value="Full Name" required />
                                <x-form.text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $staff->user->name)" required autocomplete="name" />
                                <x-form.input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <!-- Email -->
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <x-form.input-label for="email" value="Email Address" required />
                                    
                                    {{-- Email Verification Status Badge --}}
                                    @if($staff->user->email_verified_at)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                            Verified ({{ $staff->user->email_verified_at->format('d/m/Y H:i') }})
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                            Unverified
                                        </span>
                                    @endif
                                </div>

                                <x-form.text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $staff->user->email)" required autocomplete="username" />
                                <x-form.input-error :messages="$errors->get('email')" class="mt-2" />

                                <!-- Verify Email Immediately Checkbox -->
                                <div class="mt-3 flex items-center">
                                    <input id="verify_email" name="verify_email" type="checkbox" value="1" 
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded" 
                                            {{ old('verify_email', $staff->user->email_verified_at ? true : false) ? 'checked' : '' }}>
                                    <label for="verify_email" class="ml-2 block text-sm text-gray-700 font-medium">
                                        Mark email address as verified
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pl-4">
                            <!-- Role Selection -->
                            <div>
                                <x-form.input-label for="role" value="Staff Position / Role" required />
                                <x-form.input-select 
                                    id="role" 
                                    name="role" 
                                    class="mt-1"
                                    placeholder="-- Select Staff Role --"
                                    :options="[
                                        'Front Desk' => 'Front Desk',
                                        'Backend Staff' => 'Backend Staff',
                                        'Maintenance' => 'Maintenance',
                                        'Others' => 'Others'
                                    ]"
                                    :value="old('role', $staff->role)"
                                    required 
                                />
                                <x-form.input-error :messages="$errors->get('role')" class="mt-2" />
                            </div>

                            <!-- Account Status -->
                            <div>
                                <x-form.input-label for="is_active" value="Account Status" required />
                                <x-form.input-select 
                                    id="is_active" 
                                    name="is_active" 
                                    class="mt-1"
                                    :options="[
                                        '1' => 'Active',
                                        '0' => 'Inactive'
                                    ]"
                                    :value="old('is_active', $staff->is_active ? '1' : '0')"
                                    required 
                                />
                                <x-form.input-error :messages="$errors->get('is_active')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="mt-2 pt-4 border-t border-gray-100 flex justify-end gap-3">
                        <a href="{{ route('admin.staff.index') }}" 
                           class="px-6 py-2.5 text-sm font-medium text-slate-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all shadow-sm">
                            Cancel
                        </a>
                        <x-form.primary-button type="submit" loading="loading"
                                class="px-10 py-2.5 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-md hover:shadow-lg transition-all transform active:scale-95">
                            Save Changes
                        </x-form.primary-button>
                    </div>
                </x-form.form>
            </div>
        </div>
    </div>
</x-app-layout>