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
                <h1 class="text-2xl font-bold text-slate-900 mt-2">Edit Property</h1>
                <p class="text-xs text-gray-500 mt-1">ID: {{ $property->id }}</p>
            </div>

            {{-- Validation Errors --}}
            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4">
                    <div class="text-sm font-semibold text-red-800 mb-2">Please fix the following:</div>
                    <ul class="list-disc ml-5 text-sm text-red-700 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- 注意这里的 Action 和 Method --}}
            <form method="POST" action="{{ route('admin.properties.update', $property) }}">
                @csrf
                @method('PUT') {{-- 必须加这个，否则 Laravel 不认 --}}

                <div class="bg-white shadow-lg rounded-xl p-6 space-y-6">
                    
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Property Details</h2> <br>
                        
                        <div class="space-y-4">
                            {{-- Property Name --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Property Name</label>
                                <input type="text" name="name" value="{{ old('name', $property->name) }}" 
                                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm @error('name') border-red-500 @enderror" 
                                       required>
                                @error('name') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                            </div>

                            {{-- Type --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Property Type</label>
                                <select name="type" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                    @foreach(['Condo' => 'Condo / Apartment', 'Landed' => 'Landed House', 'Commercial' => 'Commercial Building'] as $val => $label)
                                        <option value="{{ $val }}" {{ old('type', $property->type) == $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Address --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Address</label>
                                <textarea name="address" rows="3" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">{{ old('address', $property->address) }}</textarea>
                                @error('address') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                            </div>

                            {{-- City & Postcode --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-900 mb-1">City</label>
                                    <input type="text" name="city" value="{{ old('city', $property->city) }}" 
                                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-slate-900 mb-1">Postcode</label>
                                     <input type="text" name="postcode" value="{{ old('postcode', $property->postcode) }}"
                                         class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" pattern="\d{5}" maxlength="5" minlength="5" inputmode="numeric" title="請輸入5位數字">
                                </div>
                            </div>

                            {{-- State (这里建议用我之前给你的马来西亚州属列表) --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">State</label>
                                <select name="state" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                    @foreach(['Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Perak', 'Perlis', 'Pulau Pinang', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu', 'W.P. Kuala Lumpur', 'W.P. Labuan', 'W.P. Putrajaya'] as $s)
                                        <option value="{{ $s }}" {{ old('state', $property->state) == $s ? 'selected' : '' }}>{{ $s }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex justify-end gap-2 pt-4 border-t border-gray-100">
                        <a href="{{ route('admin.properties.index') }}"
                           class="bg-white hover:bg-gray-50 text-slate-900 font-bold py-2 px-6 rounded-lg shadow border border-gray-200 transition">
                            Cancel
                        </a>

                        <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg shadow transition duration-150 ease-in-out">
                            Update Property
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>
</x-app-layout>