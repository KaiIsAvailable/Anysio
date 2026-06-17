<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="mb-6">
                <a href="{{ route('admin.owners.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to List
                </a>
                <h1 class="text-2xl font-bold text-slate-900 mt-2">Create New Owner</h1>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <x-form.form action="{{ route('admin.owners.store') }}" class="p-8">
                    
                    <div class="space-y-6">
                        {{-- Full Name --}}
                        <div>
                            <x-form.input-label value="Full Name" class="mb-1" />
                            <x-form.text-input 
                                name="name" 
                                id="name" 
                                value="{{ old('name') }}" 
                                placeholder="Enter owner's full name" 
                                class="w-full"
                                required 
                            />
                            <x-form.input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>

                        {{-- Email --}}
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <div class="flex items-center justify-between mb-2">
                                <x-form.input-label value="Email Address" class="mb-0" />
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="random_email" id="random_email" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    <span class="ml-2 text-xs font-medium text-indigo-600 uppercase tracking-wider">Generate Random</span>
                                </label>
                            </div>
                            <x-form.text-input 
                                type="email" 
                                name="email" 
                                id="email_input" 
                                value="{{ old('email') }}" 
                                placeholder="example@mail.com"
                                class="w-full transition-all" 
                            />
                            <p id="helper_text" class="mt-2 text-xs text-gray-500 italic">If random is selected, a temporary password will also be generated.</p>
                            <x-form.input-error :messages="$errors->get('email')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Company Name --}}
                            <div>
                                <x-form.input-label value="Company Name" class="mb-1" />
                                <x-form.text-input 
                                    name="company_name" 
                                    id="company_name" 
                                    value="{{ old('company_name') }}" 
                                    placeholder="Optional"
                                    class="w-full" 
                                />
                                <x-form.input-error :messages="$errors->get('company_name')" class="mt-1" />
                            </div>

                            {{-- IC Number --}}
                            <div>
                                <x-form.input-label value="IC Number" class="mb-1" />
                                <x-form.text-input 
                                    name="ic_number" 
                                    id="ic_number" 
                                    value="{{ old('ic_number') }}" 
                                    placeholder="" 
                                    class="w-full"
                                    required 
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')" 
                                    maxlength="12" 
                                    inputmode="numeric"
                                />
                                <p id="helper_text" class="mt-2 text-xs text-gray-500 italic">Example: 0109xxxxxxxx</p>
                                <x-form.input-error :messages="$errors->get('ic_number')" class="mt-1" />
                            </div>

                            {{-- Phone Number --}}
                            <div>
                                <x-form.input-label value="Phone Number" class="mb-1" />
                                <x-form.text-input 
                                    name="phone" 
                                    id="phone" 
                                    value="{{ old('phone') }}" 
                                    placeholder="" 
                                    class="w-full"
                                    required 
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')" 
                                    maxlength="20" 
                                    inputmode="numeric"
                                />
                                <p id="helper_text" class="mt-2 text-xs text-gray-500 italic">Example: 01xxxxxxxxx</p>
                                <x-form.input-error :messages="$errors->get('phone')" class="mt-1" />
                            </div>

                            {{-- Gender --}}
                            <div>
                                <x-form.input-label value="Gender" class="mb-1" />
                                <x-form.input-select 
                                    name="gender" 
                                    id="gender" 
                                    :options="['Male' => 'Male', 'Female' => 'Female']"
                                    placeholder="-- Select Gender --"
                                    class="w-full"
                                    required 
                                />
                                <x-form.input-error :messages="$errors->get('gender')" class="mt-1" />
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="pt-4 border-t border-gray-100 flex justify-end gap-3" x-data="{ loading: false }">
                            <a href="{{ route('admin.owners.index') }}" class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancel
                            </a>
                            
                            <x-form.primary-button type="submit" loading="loading" class="px-6 py-2.5">
                                Save Owner
                            </x-form.primary-button>
                        </div>
                    </div>
                </x-form.form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const randomCheckbox = document.getElementById('random_email');
            const emailInput = document.getElementById('email_input');

            randomCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    emailInput.value = '';
                    emailInput.placeholder = 'System will auto-generate email...';
                    emailInput.readOnly = true;
                    emailInput.classList.add('bg-gray-100', 'cursor-not-allowed');
                } else {
                    emailInput.placeholder = 'example@mail.com';
                    emailInput.readOnly = false;
                    emailInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
                }
            });
        });
    </script>
</x-app-layout>