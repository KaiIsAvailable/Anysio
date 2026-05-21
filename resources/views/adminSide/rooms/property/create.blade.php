<x-app-layout>
    <div class="bg-gray-50 min-h-screen p-6">
        <div class="max-w-3xl mx-auto">

            {{-- Navigation & Title --}}
            <div class="mb-6">
                <a href="{{ route('admin.properties.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to List
                </a>
                <h1 class="text-2xl font-bold text-slate-900 mt-2">Add Property</h1>
            </div>

            <x-form.form action="{{ route('admin.properties.store') }}">

                {{-- Card Wrapper --}}
                <div class="bg-white shadow-lg rounded-xl p-6 space-y-6">
                    
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Property Details</h2> <br>
                        
                        <div class="space-y-4">
                            {{-- 1. Has Owner？ --}}
                            @if($isOwnerAdmin)                                
                                <div class="flex items-center p-4 bg-gray-50 border border-gray-200 rounded-2xl">
                                    <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center mr-3 text-indigo-600">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-black text-slate-800">{{ $currentOwner->name }}</p>
                                        <p class="text-xs text-slate-500">Owner Admin is automatically assigned as owner.</p>
                                    </div>
                                </div>

                                <input type="hidden" name="owner_id" value="{{ $currentOwner->id }}">
                            @else
                                <div>
                                    <x-form.input-label value="Does this property has an owner?" class="mb-1" />
                                    <x-form.input-select 
                                        name="has_owner" 
                                        id="has_owner" 
                                        onchange="toggleOwnerInput()" 
                                        :options="['0' => 'No', '1' => 'Yes']"
                                        class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm"
                                    >
                                    </x-form.input-select>
                                </div>

                                {{-- 2. Owner 选择器 (增加了一个 id="owner_input_wrapper") --}}
                                <div id="owner_input_wrapper" style="{{ old('has_owner') == 1 ? '' : 'display:none;' }}">
                                    <x-form.input-label value="Owner" class="mb-1" />
                                    <x-form.input-select 
                                        name="owner_id" 
                                        id="owner_selector" 
                                        :options="$owners->pluck('name', 'id')->toArray()"
                                        placeholder="Select Owner (Optional)"
                                    />
                                    @if($owners->isEmpty())
                                        <p class="text-sm text-red-500 mt-2">No owner options available for your account.</p>
                                    @endif
                                </div>
                            @endif

                            {{-- Property Name --}}
                            <div>
                                <x-form.input-label value="Property Name" class="mb-1" />
                                <x-form.text-input 
                                    name="name" 
                                    value="{{ old('name') }}" 
                                    placeholder="e.g. Anysio HQ"
                                    class="w-full "
                                    required 
                                />
                                <x-form.input-error :messages="$errors->get('name')" class="mt-1" />
                            </div>

                            {{-- Type --}}
                            <div>
                                <x-form.input-label value="Property Type" class="mb-1" />
                                <x-form.input-select 
                                    name="type"
                                    class=" w-full"
                                    :options="['Condo' => 'Condo / Apartment', 'Landed' => 'Landed House', 'Commercial' => 'Commercial Building', 'Shop Lot' => 'Shop Lot']"
                                    placeholder="Select a Property Type">
                                </x-form.input-select>
                            </div>

                            {{-- Address --}}
                            <div>
                                <x-form.input-label value="Address" class="mb-1" />
                                <textarea name="address" rows="3" placeholder="Full street address..."
                                          class=" w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">{{ old('address') }}</textarea>
                                @error('address') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                            </div>

                            {{-- City & Postcode (Grid) --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-form.input-label value="Postcode" class="mb-1" />
                                    <x-form.text-input 
                                        type="text" 
                                        name="postcode"
                                        value="{{ old('postcode') }}"
                                        placeholder="e.g. 31900"
                                        class=" w-full"
                                        {{-- 这些属性会被合并进组件内部的 <input> 标签中 --}}
                                        pattern="\d{5}" 
                                        maxlength="5" 
                                        inputmode="numeric"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                    />
                                    <x-form.input-error :messages="$errors->get('postcode')" class="mt-1" />
                                </div>

                                <div>
                                    <x-form.input-label value="City" class="mb-1" />
                                    <x-form.text-input 
                                        name="city" 
                                        value="{{ old('city') }}" 
                                        placeholder="e.g. Kampar "
                                        class="w-full "
                                        required 
                                    />
                                    <x-form.input-error :messages="$errors->get('city')" class="mt-1" />
                                </div>
                            </div>

                            {{-- State --}}
                            <div>
                                <x-form.input-label value="State" class="mb-1" />
                                <x-form.input-select 
                                    name="state" 
                                    :options="[
                                        'Johor' => 'Johor',
                                        'Kedah' => 'Kedah',
                                        'Kelantan' => 'Kelantan',
                                        'Melaka' => 'Melaka',
                                        'Negeri Sembilan' => 'Negeri Sembilan',
                                        'Pahang' => 'Pahang',
                                        'Perak' => 'Perak',
                                        'Perlis' => 'Perlis',
                                        'Pulau Pinang' => 'Pulau Pinang',
                                        'Sabah' => 'Sabah',
                                        'Sarawak' => 'Sarawak',
                                        'Selangor' => 'Selangor',
                                        'Terengganu' => 'Terengganu',
                                        'W.P. Kuala Lumpur' => 'W.P. Kuala Lumpur',
                                        'W.P. Labuan' => 'W.P. Labuan',
                                        'W.P. Putrajaya' => 'W.P. Putrajaya'
                                    ]" 
                                    placeholder="Select a State"
                                />
                            </div>
                        </div>
                    </div>

                    {{-- Actions (Same as Room create) --}}
                    <div class="flex justify-end gap-2 pt-4 border-t border-gray-100">
                        <a href="{{ route('admin.properties.index') }}"
                           class="bg-white hover:bg-gray-50 text-slate-900 font-bold py-2 px-6 rounded-lg shadow border border-gray-200 transition">
                            Cancel
                        </a>

                        <x-form.primary-button loading="loading" class="py-2 px-6">
                            Save Property
                        </x-form.primary-button>
                    </div>

                </div>
            </x-form.form>
        </div>
    </div>
    <script>
        function toggleOwnerInput() {
            const select = document.getElementById('has_owner');
            const wrapper = document.getElementById('owner_input_wrapper');
            
            if (select.value === '1') {
                wrapper.style.setProperty('display', 'block', 'important');
            } else {
                wrapper.style.setProperty('display', 'none', 'important');
            }
        }
    </script>
</x-app-layout>