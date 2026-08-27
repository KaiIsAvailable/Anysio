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
                <h1 class="text-2xl font-bold text-slate-900 mt-2">Edit Owner</h1>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <x-form.form method="POST" action="{{ route('admin.owners.update', $owner->id) }}" class="p-8" loading="loading">
                    @method('PUT')
                    @csrf

                    <div class="space-y-6">
                        
                        {{-- Owner Name (Editable) --}}
                        <div>
                            <x-form.input-label value="Owner Name" class="mb-1" />
                            <x-form.text-input 
                                type="text" 
                                name="name" 
                                id="name"
                                value="{{ old('name', $owner->user->name) }}" 
                                class="w-full" 
                                required
                            />
                            <x-form.input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>

                        {{-- Email (Editable) --}}
                        <div>
                            <x-form.input-label value="Email Address" class="mb-1" />
                            <x-form.text-input 
                                type="email" 
                                name="email" 
                                id="email"
                                value="{{ old('email', $owner->user->email) }}" 
                                class="w-full" 
                                required
                            />
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
                                <p class="mt-2 text-xs text-gray-500 italic">Example: 0109xxxxxxxx</p>
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
                                <p class="mt-2 text-xs text-gray-500 italic">Example: 01xxxxxxxxx</p>
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
                                    class="w-full" 
                                    required 
                                />
                                <x-form.input-error :messages="$errors->get('gender')" class="mt-1" />
                            </div>
                        </div>

                        {{-- Address Information Section --}}
                        <div class="pt-4 border-t border-gray-100">
                            <div class="space-y-4">
                                {{-- Street Address (1 Full Row) --}}
                                <div>
                                    <x-form.input-label value="Address" class="mb-1" />
                                    <textarea name="address" rows="3"
                                            class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">{{ old('address', $owner->address) }}</textarea>
                                    @error('address') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                                </div>

                                {{-- Postcode, City & State (1 Row with 3 Columns) --}}
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    {{-- Postcode --}}
                                    <div>
                                        <x-form.input-label value="Postcode" class="mb-1" />
                                        <x-form.text-input 
                                            type="text" 
                                            name="postcode" 
                                            id="postcode" 
                                            value="{{ old('postcode', $owner->postcode) }}" 
                                            class="w-full"
                                            pattern="\d{5}" 
                                            maxlength="5" 
                                            inputmode="numeric"
                                            oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                        />
                                        <x-form.input-error :messages="$errors->get('postcode')" class="mt-1" />
                                    </div>

                                    {{-- City --}}
                                    <div>
                                        <x-form.input-label value="City" class="mb-1" />
                                        <x-form.text-input 
                                            name="city" 
                                            id="city" 
                                            value="{{ old('city', $owner->city) }}" 
                                            class="w-full" 
                                        />
                                        <x-form.input-error :messages="$errors->get('city')" class="mt-1" />
                                    </div>

                                    {{-- State --}}
                                    <div>
                                        <x-form.input-label value="State" class="mb-1" />
                                        <x-form.input-select 
                                            name="state" 
                                            id="state"
                                            :options="$worldCountries"
                                            :value="old('state', $owner->state)"
                                            class="w-full"
                                        />
                                        <x-form.input-error :messages="$errors->get('state')" class="mt-1" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                            <a href="{{ route('admin.owners.index') }}" 
                               class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancel
                            </a>
                            <x-form.primary-button type="submit" class="px-6 py-2.5" loading="loading">
                                Update Owner
                            </x-form.primary-button>
                        </div>

                    </div>
                </x-form.form>
            </div>
        </div>
    </div>
</x-app-layout>