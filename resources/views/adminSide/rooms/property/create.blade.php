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

            <form method="POST" action="{{ route('admin.properties.store') }}">
                @csrf

                {{-- Card Wrapper --}}
                <div class="bg-white shadow-lg rounded-xl p-6 space-y-6">
                    
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Property Details</h2> <br>
                        
                        <div class="space-y-4">
                            {{-- 1. Has Owner？ --}}
                            <div>
                                <label class="block text-sm font-bold text-slate-900 mb-1">Does this property has an owner?</label>
                                <select name="has_owner" id="has_owner" onchange="toggleOwnerInput()" 
                                        class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                                    <option value="0" @selected(old('has_owner') == 0)>No</option>
                                    <option value="1" @selected(old('has_owner') == 1)>Yes</option>
                                </select>
                            </div>

                            {{-- 2. Owner 选择器 (增加了一个 id="owner_input_wrapper") --}}
                            <div id="owner_input_wrapper" style="{{ old('has_owner') == 1 ? '' : 'display:none;' }}">
                                <label class="block text-sm font-medium text-slate-900 mb-1">Owner</label>
                                <select name="owner_id" id="owner_selector" onchange="filterAssetsByOwner()" 
                                        class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                                    <option value="">-- Select Owner (Optional) --</option>
                                    @foreach($owners as $owner)
                                        <option value="{{ $owner->id }}" 
                                            @selected(old('owner_id') == $owner->id)>
                                            {{ $owner->user->name }} {{ $owner->company_name ? "({$owner->company_name})" : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Property Name --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Property Name</label>
                                <input type="text" name="name" value="{{ old('name') }}" 
                                       placeholder="e.g. Sky Residence, Garden Villa"
                                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm @error('name') border-red-500 @enderror" 
                                       required>
                                @error('name') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                            </div>

                            {{-- Type --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Property Type</label>
                                <select name="type" 
                                        class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                    <option value="Condo" {{ old('type') == 'Condo' ? 'selected' : '' }}>Condo / Apartment</option>
                                    <option value="Landed" {{ old('type') == 'Landed' ? 'selected' : '' }}>Landed House</option>
                                    <option value="Commercial" {{ old('type') == 'Commercial' ? 'selected' : '' }}>Commercial Building</option>
                                </select>
                            </div>

                            {{-- Address --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Address</label>
                                <textarea name="address" rows="3" placeholder="Full street address..."
                                          class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">{{ old('address') }}</textarea>
                                @error('address') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                            </div>

                            {{-- City & Postcode (Grid) --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-900 mb-1">City</label>
                                    <input type="text" name="city" value="{{ old('city') }}" placeholder="e.g. Ampang"
                                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                    @error('city') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-slate-900 mb-1">Postcode</label>
                                    <input type="text" name="postcode" value="{{ old('postcode') }}" placeholder="e.g. 51900"
                                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                    @error('postcode') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            {{-- State --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">State</label>
                                <select name="state" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                    @foreach(['', 'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Perak', 'Perlis', 'Pulau Pinang', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu', 'W.P. Kuala Lumpur', 'W.P. Labuan', 'W.P. Putrajaya'] as $state)
                                        <option value="{{ $state }}" @selected(old('state') == $state)>{{ $state }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Actions (Same as Room create) --}}
                    <div class="flex justify-end gap-2 pt-4 border-t border-gray-100">
                        <a href="{{ route('admin.properties.index') }}"
                           class="bg-white hover:bg-gray-50 text-slate-900 font-bold py-2 px-6 rounded-lg shadow border border-gray-200 transition">
                            Cancel
                        </a>

                        <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg shadow transition duration-150 ease-in-out">
                            Save Property
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>
    <script>
        function toggleOwnerInput() {
            const hasOwner = document.getElementById('has_owner').value;
            const wrapper = document.getElementById('owner_input_wrapper');
            const selector = document.getElementById('owner_selector');

            if (hasOwner === "1") {
                wrapper.style.display = 'block';
            } else {
                wrapper.style.display = 'none';
                // 关键：如果选了 "No"，把已经选中的 owner 清空，避免传回后端
                selector.value = ""; 
                
                // 如果你有连带的 asset 过滤逻辑，也在这里触发重置
                if (typeof filterAssetsByOwner === "function") {
                    filterAssetsByOwner();
                }
            }
        }
    </script>
</x-app-layout>