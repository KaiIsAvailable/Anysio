<x-app-layout>
    <div class="bg-gray-50 min-h-screen p-6">
        <div class="max-w-3xl mx-auto">

            <div class="mb-6">
                <a href="{{ route('admin.units.show', $unit->id) }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to List
                </a>
                <h1 class="text-2xl font-bold text-slate-900 mt-2">Add Room</h1>
            </div>

            <form method="POST" action="{{ route('admin.rooms.store') }}">
                @csrf

                {{-- Card wrapper (same as Owner create) --}}
                <div class="bg-white shadow-lg rounded-xl p-6 space-y-6">

                    {{-- Room Details --}}
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Room Details</h2>
                        <div class="mt-4 space-y-4">

                            {{-- Unit 字段 (已选定状态) --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Unit</label>
                                
                                @if(isset($unit))
                                    {{-- 显示已选定的 Unit 信息 --}}
                                    <div class="w-full px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg text-slate-700 font-medium flex items-center shadow-sm">
                                        <svg class="w-4 h-4 mr-2 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                        Unit {{ $unit->unit_no }} 
                                        <span class="ml-2 text-xs text-gray-400 font-normal">({{ $unit->property->name ?? 'N/A' }})</span>
                                    </div>
                                    
                                    {{-- 重要：必须通过 hidden input 把 unit_id 传给 store 方法 --}}
                                    <input type="hidden" name="unit_id" value="{{ $unit->id }}">
                                    
                                    {{-- 如果你的房间需要地址，可以从 Unit 自动带入 --}}
                                    <input type="hidden" name="address" value="{{ $unit->address ?? ($unit->property->address ?? '') }}">
                                @else
                                    {{-- 容错处理：如果没有带 unit_id 过来 (虽然你的逻辑里不应该发生) --}}
                                    <div class="p-3 bg-red-50 text-red-600 text-sm rounded-lg border border-red-200">
                                        Error: No unit selected. 
                                        <a href="{{ route('admin.properties.index') }}" class="underline font-bold">Go back</a>
                                    </div>
                                @endif
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {{-- 2. Room Number --}}
                                <div>
                                    <label class="block text-sm font-medium text-slate-900 mb-1">Room Number</label>
                                    <input name="room_no"
                                        value="{{ old('room_no') }}"
                                        placeholder="exp: Master Room / Room A"
                                        class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                        required>
                                    @error('room_no') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                                </div>

                                {{-- 3. Room Type --}}
                                <div>
                                    <label class="block text-sm font-medium text-slate-900 mb-1">Room Type</label>
                                    <input name="room_type"
                                        value="{{ old('room_type') }}"
                                        placeholder="exp: Single / Master / Balcony"
                                        class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                        required>
                                    @error('room_type') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                                </div>

                                {{-- 4. Status --}}
                                <div>
                                    <label class="block text-sm font-medium text-slate-900 mb-1">Status</label>
                                    <select name="status"
                                            class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                            required>
                                        @foreach(['Vacant','Occupied','Maintenance'] as $s)
                                            <option value="{{ $s }}" @selected(old('status') == $s)>{{ $s }}</option>
                                        @endforeach
                                    </select>
                                    @error('status') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                                </div>

                                {{-- 5. Address (增加自动填充逻辑) --}}
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-slate-900 mb-1">Address</label>
                                    
                                    {{-- 1. 视觉展示框：让用户看到 Property 的地址 --}}
                                    <div class="w-full px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg text-slate-600 text-sm flex items-start shadow-sm">
                                        <svg class="w-4 h-4 mr-2 mt-0.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span>{{ $unit->property->address . ', ' . $unit->property->city . ', ' . $unit->property->postcode . ' ' . $unit->property->state ?? 'No address set for this property' }}</span>
                                    </div>

                                    {{-- 2. 隐藏域：确保表单提交时，这个地址会被存入 Room 的数据里 --}}
                                    <input type="hidden" name="address" value="{{ $unit->property->address ?? '' }}">

                                    <div class="text-xs text-gray-400 mt-1 italic">
                                        * This room will be registered under the primary property address.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Divider --}}
                    <hr class="border-gray-100">

                    {{-- Asset Selection (Library) --}}
                    <div class="mt-6">
                        <label class="block text-sm font-medium text-slate-900 mb-2">Room Assets (Asset Library)</label>
                        
                        <div class="block w-full p-4 bg-white border border-gray-200 rounded-xl shadow-sm mb-4">
                            <div class="flex items-center justify-between mb-3 border-b pb-2">
                                <div class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                    <h3 class="font-bold text-slate-800 text-sm">Select Assets for this Room</h3>
                                </div>
                                <span class="text-[10px] text-gray-400 font-mono">ASSET-PICKER</span>
                            </div>
                            
                            {{-- Asset Grid --}}
                            <div class="custom-scrollbar mb-2 p-1 bg-gray-50 rounded-lg" style="height: 200px; overflow-y: auto !important; border: 1px solid #f1f5f9;">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-2">
                                    @forelse($assetLibrary as $lib)
                                        @if($lib->status === "Active")
                                            <div class="flex items-center justify-between py-1.5 px-2 bg-white border border-gray-100 rounded-md shadow-sm hover:border-indigo-200 transition-all">
                                                {{-- Checkbox logic: 如果 qty > 0 则选中 --}}
                                                <input type="hidden" 
                                                    name="assets[{{ $loop->index }}][id]" 
                                                    value="{{ $lib->id }}" 
                                                    class="hidden asset-checkbox" 
                                                    id="asset_{{ $lib->id }}">
                                                
                                                <span class="text-[12px] text-slate-600 font-semibold truncate flex-1 mr-2" title="{{ $lib->name }}">
                                                    {{ $lib->name }}
                                                </span>
                                                
                                                <div class="flex items-center bg-gray-50 rounded-md p-0.5 border border-gray-100">
                                                    <button type="button" onclick="adjustQty(this, -1)" 
                                                        class="w-5 h-5 flex items-center justify-center rounded bg-white text-gray-400 hover:text-red-500 hover:shadow-sm transition-all text-xs border border-gray-100">-</button>
                            
                                                    <input type="text" 
                                                        name="assets[{{ $loop->index }}][qty]" 
                                                        value="{{ old("assets.$loop->index.qty", 0) }}" 
                                                        readonly
                                                        class="qty-input w-10 text-center text-[12px] bg-transparent border-none focus:ring-0 p-0"
                                                        onchange="syncCheckbox(this)">

                                                    <button type="button" onclick="adjustQty(this, 1)" 
                                                        class="w-5 h-5 flex items-center justify-center rounded bg-white text-gray-400 hover:text-indigo-600 hover:shadow-sm transition-all text-xs border border-gray-100">+</button>
                                                </div>
                                            </div>
                                        @endif
                                    @empty
                                        <div class="col-span-3 flex flex-col items-center justify-center h-[160px]">
                                            <span class="text-[13px] text-slate-400 font-medium">No active assets found in library</span>
                                        </div>
                                    @endforelse
                                </div>
                            </div>                                                                                                                                                                                                                                                                                                                                                                                                                                                                            
                                                                                                                                                                 </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex justify-end gap-2 pt-2">
                        <a href="{{ route('admin.rooms.index') }}"
                           class="bg-white hover:bg-gray-50 text-slate-900 font-bold py-2 px-6 rounded-lg shadow border border-gray-200 transition">
                            Cancel
                        </a>

                        <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg shadow transition duration-150 ease-in-out">
                            Save
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>
    <script>
        function adjustQty(btn, amount) {
            const input = btn.parentElement.querySelector('.qty-input');
            let newVal = parseInt(input.value) + amount;
            if (newVal < 0) newVal = 0;
            input.value = newVal;
            
            // 自动勾选/取消隐藏的 checkbox
            const checkbox = btn.closest('.flex').parentElement.querySelector('.asset-checkbox');
            checkbox.checked = newVal > 0;
        }

        function syncCheckbox(input) {
            const checkbox = input.closest('.flex').parentElement.querySelector('.asset-checkbox');
            checkbox.checked = parseInt(input.value) > 0;
        }
    </script>
</x-app-layout>
