<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- Navigation & Title (Aligned with Create) --}}
            <div class="mb-6">
                <a href="{{ route('admin.owners.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to List
                </a>
                <h1 class="text-2xl font-bold text-slate-900 mt-2">Edit Owner</h1>
            </div>

            {{-- Form Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <x-form.form action="{{ route('admin.owners.update', $owner->id) }}" class="p-8">
                    @method('PUT')

                    <div class="space-y-6">
                        
                        {{-- Owner Name (Disabled) --}}
                        <div>
                            <x-form.input-label value="Owner Name" class="mb-1" />
                            <x-form.text-input 
                                type="text" 
                                value="{{ $owner->user->name }}" 
                                class="w-full bg-gray-50 text-gray-500 cursor-not-allowed border-gray-200" 
                                disabled 
                            />
                            <input type="hidden" name="user_id" value="{{ $owner->user_id }}">
                            <p class="mt-1 text-xs text-gray-400">Account name is managed via User Settings.</p>
                        </div>

                        {{-- Email (Disabled) --}}
                        <div>
                            <x-form.input-label value="Email Address" class="mb-1" />
                            <x-form.text-input 
                                type="email" 
                                value="{{ $owner->user->email }}" 
                                class="w-full bg-gray-50 text-gray-500 cursor-not-allowed border-gray-200" 
                                disabled 
                            />
                            <input type="hidden" name="email" value="{{ $owner->user->email }}">
                            <p class="mt-1 text-xs text-gray-400">Account email is managed via User Settings.</p>
                            <x-form.input-error :messages="$errors->get('email')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Company Name --}}
                            <div>
                                <x-form.input-label value="Company Name" class="mb-1" />
                                <x-form.text-input 
                                    type="text" 
                                    name="company_name" 
                                    id="company_name" 
                                    value="{{ old('company_name', $owner->company_name) }}" 
                                    placeholder="Individual"
                                    class="w-full" 
                                />
                                <x-form.input-error :messages="$errors->get('company_name')" class="mt-1" />
                            </div>

                            {{-- IC / Passport Number --}}
                            <div>
                                <x-form.input-label value="IC / Passport Number" class="mb-1" />
                                <x-form.text-input 
                                    type="text" 
                                    name="ic_number" 
                                    id="ic_number" 
                                    value="{{ old('ic_number', $owner->ic_number) }}" 
                                    class="w-full" 
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')" 
                                    maxlength="12" 
                                    inputmode="numeric"
                                    required
                                />
                                <p id="helper_text" class="mt-2 text-xs text-gray-500 italic">Example: 0109xxxxxxxx</p>
                                <x-form.input-error :messages="$errors->get('ic_number')" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Phone Number --}}
                            <div>
                                <x-form.input-label value="Phone Number" class="mb-1" />
                                <x-form.text-input 
                                    type="text" 
                                    name="phone" 
                                    id="phone" 
                                    value="{{ old('phone', $owner->phone) }}" 
                                    class="w-full"
                                    required 
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')" 
                                    maxlength="12" 
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
                                    :value="old('gender', $owner->gender)"
                                    :options="['Male' => 'Male', 'Female' => 'Female']"
                                    placeholder="-- Select Gender --"
                                    class="w-full" 
                                    required 
                                />
                                <x-form.input-error :messages="$errors->get('gender')" class="mt-1" />
                            </div>
                        </div>

                        {{-- Actions (Aligned with Create) --}}
                        <div class="pt-4 border-t border-gray-100 flex justify-end gap-3" x-data="{ loading: false }">
                            <a href="{{ route('admin.owners.index') }}" 
                               class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancel
                            </a>
                            <x-form.primary-button type="submit" loading="loading" class="px-6 py-2.5">
                                Update Owner
                            </x-form.primary-button>
                        </div>

                    </div>
                </x-form.form>
            </div>
        </div>
    </div>
</x-app-layout>