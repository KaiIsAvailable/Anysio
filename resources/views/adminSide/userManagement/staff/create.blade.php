<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-6">
                <a href="{{ route('admin.staff.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    Back to List
                </a>
            </div>

            <!-- Header Section -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Add New Staff</h1>
            </div>

            <!-- Form Card -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100 p-6 sm:p-8">
                <x-form.form action="{{ route('admin.staff.store') }}" method="POST" class="space-y-6" loading="loading">

                    <!-- If Master Admin, let them select which management profile this staff belongs to -->
                    @if(auth()->user()->role === 'admin')
                        <div>
                            <x-form.input-label for="user_mgnt_id" value="Assign to Management Account" required />
                            <x-form.input-select 
                                id="user_mgnt_id" 
                                name="user_mgnt_id" 
                                class="mt-1"
                                placeholder="-- Select Management Account --"
                                :options="$managementList"
                                value-field="id"
                                label-field="user.name"
                                :value="old('user_mgnt_id')"
                                required 
                            />
                            <x-form.input-error :messages="$errors->get('user_mgnt_id')" class="mt-2" />
                        </div>
                    @endif

                    <!-- Staff Name -->
                    <div>
                        <x-form.input-label for="name" value="Full Name" required />
                        <x-form.text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus autocomplete="name" />
                        <x-form.input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <!-- Email Address -->
                    <div>
                        <x-form.input-label for="email" value="Email Address" required />
                        <x-form.text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required autocomplete="username" />
                        <x-form.input-error :messages="$errors->get('email')" class="mt-2" />

                        <!-- Checkbox to auto-verify email on creation -->
                        <div class="mt-3 flex items-center">
                            <input id="verify_email_now" name="verify_email_now" type="checkbox" value="1" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded" {{ old('verify_email_now') ? 'checked' : '' }}>
                            <label for="verify_email_now" class="ml-2 block text-sm text-gray-700">
                                Mark email as verified immediately
                            </label>
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <x-form.input-label for="password" value="Password" required />
                        <x-form.password-input id="password" name="password" class="mt-1 block w-full" required autocomplete="new-password" />
                        <x-form.input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <x-form.input-label for="password_confirmation" value="Confirm Password" required />
                        <x-form.password-input id="password_confirmation" name="password_confirmation" class="mt-1 block w-full" required autocomplete="new-password" />
                        <x-form.input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                    </div>

                    <!-- Staff Role (Select Component) -->
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
                            :value="old('role')"
                            required 
                        />
                        <x-form.input-error :messages="$errors->get('role')" class="mt-2" />
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-100">
                        <a href="{{ route('admin.staff.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                            Cancel
                        </a>
                        <x-form.primary-button loading="loading">
                            Create Staff
                        </x-form.primary-button>
                    </div>

                </x-form.form>
            </div>
        </div>
    </div>
</x-app-layout>