<x-app-layout>
    <div class="bg-gray-50 min-h-screen p-6">
        <div class="max-w-4xl mx-auto">

            {{-- 标题 --}}
            <div class="mb-6">
                <a href="{{ route('admin.properties.show', $targetProperty->id) }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to Units
                </a>
                <h1 class="text-2xl font-bold text-slate-900 mt-2">Edit Unit</h1>
            </div>

            <form method="POST" action="{{ route('admin.units.update', $unit->id) }}">
                @csrf
                @method('PUT')

                <div class="bg-white shadow-lg rounded-xl p-6 space-y-8">
                    
                    {{-- 第一部分：关联关系 --}}
                    <section>
                        <h2 class="text-lg font-semibold text-slate-900 mb-4 border-b pb-2">Associations</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Property</label>
                                
                                @if(isset($targetProperty))
                                    <div class="w-full px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg text-slate-700 font-medium flex items-center shadow-sm">
                                        <svg class="w-4 h-4 mr-2 text-indigo-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                                        </svg>
                                        {{ $targetProperty->name }}
                                    </div>
                                    <input type="hidden" name="property_id" value="{{ $targetProperty->id }}">
                                @else
                                    <select name="property_id" class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm" required>
                                        <option value="">-- Choose Property --</option>
                                        @foreach($properties as $property)
                                            <option value="{{ $property->id }}" @selected(old('property_id') == $property->id)>
                                                {{ $property->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Unit Owner</label>
                                <select name="owner_id" id="owner_selector" class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                                    @foreach($owners as $owner)
                                        <option value="{{ $owner->id }}" 
                                            @selected(old('owner_id', $unit->owner_id) == $owner->id)>
                                            {{ $owner->user->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </section>

                    {{-- 第二部分：单位基本信息 --}}
                    <section>
                        <h2 class="text-lg font-semibold text-slate-900 mb-4 border-b pb-2">Unit Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Unit No.</label>
                                <input type="text" name="unit_no" 
                                    value="{{ old('unit_no', $unit->unit_no) }}" 
                                    class="w-full rounded-lg @error('unit_no') border-red-500 @enderror border-gray-300 focus:ring-indigo-500 shadow-sm" required>
                                @error('unit_no')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Block / Tower</label>
                                <input type="text" name="block" 
                                    value="{{ old('block', $unit->block) }}" 
                                    class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm" required>
                            </div>
                            
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Floor</label>
                                <input type="number" name="floor" 
                                    value="{{ old('floor', $unit->floor) }}" 
                                    class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm" min="0" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Size (Sqft)</label>
                                <div class="relative">
                                    <input type="number" name="sqft" 
                                        value="{{ old('sqft', $unit->sqft) }}" 
                                        class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm" min="0" required>
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- 第三部分：公用事业账号 --}}
                    <section>
                        <h2 class="text-lg font-semibold text-slate-900 mb-4 border-b pb-2">Utilities & Status</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Electricity Acc No. (TNB)</label>
                                <input type="text" name="electricity_acc_no" value="{{ old('electricity_acc_no', $unit->electricity_acc_no) }}" 
                                    class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm" pattern="[0-9]{12}" maxlength="12" minlength="12" inputmode="numeric" required title="Must be exactly 12 digits">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Water Acc No. (Air Selangor/SAJ)</label>
                                <input type="text" name="water_acc_no" value="{{ old('water_acc_no', $unit->water_acc_no) }}" 
                                    class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm" pattern="[0-9]{16}" maxlength="16" minlength="16" inputmode="numeric" required title="Must be exactly 16 digits">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-slate-900 mb-1">Status</label>
                            <select name="status" class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                                @php $currentStatus = old('status', $unit->status); @endphp
                                <option value="Vacant" @selected($currentStatus == 'Vacant')>Vacant</option>
                                <option value="Occupied" @selected($currentStatus == 'Occupied')>Occupied</option>
                                <option value="Under Maintenance" @selected($currentStatus == 'Under Maintenance')>Under Maintenance</option>
                            </select>
                        </div>
                    </section>

                    {{-- Hidden field for has_rooms to maintain form submission --}}
                    <input type="hidden" name="has_rooms" value="{{ old('has_rooms', $hasRoomsCount) }}">
                    
                    {{-- 操作按钮 --}}
                    <div class="flex justify-end gap-2 pt-4 border-t border-gray-100">
                        <a href="{{ route('admin.properties.show', $targetProperty->id) }}" class="px-6 py-2 text-slate-700 font-bold border rounded-lg hover:bg-gray-50">Cancel</a>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-8 rounded-lg shadow-md transition">
                            Update Unit
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>