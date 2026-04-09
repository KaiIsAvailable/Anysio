<x-app-layout>
    <div class="bg-gray-50 min-h-screen p-6">
        <div class="max-w-3xl mx-auto">

            {{-- Breadcrumb / Back --}}
            <div class="mb-6">
                <a href="{{ route('admin.units.show', $room->unit_id) }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to Unit Details
                </a>
                <h1 class="text-2xl font-bold text-slate-900 mt-2">Edit Room: {{ $room->room_no }}</h1>
            </div>

            {{-- 注意：Action 改为 update，且必须有 @method('PUT') --}}
            <form method="POST" action="{{ route('admin.rooms.update', $room->id) }}">
                @csrf
                @method('PUT')

                <div class="bg-white shadow-lg rounded-xl p-6 space-y-6">

                    {{-- Room Details --}}
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 border-b pb-2">Room Details</h2>
                        <div class="mt-4 space-y-4">

                            {{-- Unit 字段 (只读展示) --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Unit</label>
                                <div class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-slate-500 font-medium flex items-center shadow-sm opacity-75">
                                    <svg class="w-4 h-4 mr-2 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    Unit {{ $room->unit->unit_no }} 
                                    <span class="ml-2 text-xs text-gray-400 font-normal">({{ $room->unit->property->name ?? 'N/A' }})</span>
                                </div>
                                <input type="hidden" name="unit_id" value="{{ $room->unit_id }}">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {{-- Room Number --}}
                                <div>
                                    <label class="block text-sm font-medium text-slate-900 mb-1">Room Number</label>
                                    <input name="room_no"
                                        value="{{ old('room_no', $room->room_no) }}"
                                        class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                        required>
                                    @error('room_no') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                                </div>

                                {{-- Room Type --}}
                                <div>
                                    <label class="block text-sm font-medium text-slate-900 mb-1">Room Type</label>
                                    <input name="room_type"
                                        value="{{ old('room_type', $room->room_type) }}"
                                        class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                        required>
                                    @error('room_type') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                                </div>

                                {{-- Status --}}
                                <div>
                                    <label class="block text-sm font-medium text-slate-900 mb-1">Status</label>
                                    <select name="status" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                                        @foreach(['Vacant','Occupied','Maintenance'] as $s)
                                            <option value="{{ $s }}" @selected(old('status', $room->status) == $s)>{{ $s }}</option>
                                        @endforeach
                                    </select>
                                    @error('status') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    {{-- Asset Selection (Library) --}}
                    <div class="mt-6">
                        <label class="block text-sm font-medium text-slate-900 mb-2">Room Assets (Update Quantities)</label>
                        
                        <div class="block w-full p-4 bg-white border border-gray-200 rounded-xl shadow-sm mb-4">
                            <div class="flex items-center justify-between mb-3 border-b pb-2">
                                <div class="flex items-center gap-3">
                                    <h3 class="font-bold text-slate-800 text-sm">Update Assets</h3>
                                </div>
                            </div>
                            
                            <div class="custom-scrollbar mb-2 p-1 bg-gray-50 rounded-lg" style="height: 200px; overflow-y: auto !important; border: 1px solid #f1f5f9;">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-2">
                                    @forelse($assetLibrary as $lib)
                                        @php
                                            // 从现有的多对多关联中获取该 Asset 的数量
                                            // 假设你在 Controller 里传了 $currentAssets = $room->assets->pluck('pivot.qty', 'id')->toArray();
                                            $existingQty = $currentAssets[$lib->id] ?? 0;
                                        @endphp
                                        <div class="flex items-center justify-between py-1.5 px-2 bg-white border {{ $existingQty > 0 ? 'border-indigo-200 ring-1 ring-indigo-50' : 'border-gray-100' }} rounded-md shadow-sm hover:border-indigo-200 transition-all asset-row">
                                            
                                            <input type="hidden" name="assets[{{ $loop->index }}][id]" value="{{ $lib->id }}">
                                            
                                            <span class="text-[12px] {{ $existingQty > 0 ? 'text-indigo-700 font-bold' : 'text-slate-600 font-semibold' }} truncate flex-1 mr-2">
                                                {{ $lib->name }}
                                            </span>
                                            
                                            <div class="flex items-center bg-gray-50 rounded-md p-0.5 border border-gray-100">
                                                <button type="button" onclick="adjustQty(this, -1)" 
                                                    class="w-5 h-5 flex items-center justify-center rounded bg-white text-gray-400 hover:text-red-500 border border-gray-100">-</button>
                                                
                                                <input type="text" 
                                                    name="assets[{{ $loop->index }}][qty]" 
                                                    value="{{ old("assets.$loop->index.qty", $existingQty) }}" 
                                                    readonly
                                                    class="qty-input w-10 text-center text-[12px] bg-transparent border-none focus:ring-0 p-0">

                                                <button type="button" onclick="adjustQty(this, 1)" 
                                                    class="w-5 h-5 flex items-center justify-center rounded bg-white text-gray-400 hover:text-indigo-600 border border-gray-100">+</button>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="col-span-3 flex items-center justify-center h-[160px]">
                                            <span class="text-[13px] text-slate-400 font-medium">No assets available</span>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex justify-end gap-2 pt-2">
                        <a href="{{ route('admin.units.show', $room->unit_id) }}"
                           class="bg-white hover:bg-gray-50 text-slate-900 font-bold py-2 px-6 rounded-lg shadow border border-gray-200 transition text-sm">
                            Cancel
                        </a>

                        <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg shadow transition duration-150 ease-in-out text-sm">
                            Update Room
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    {{-- 脚本保持不变，但在逻辑上可以优化样式切换 --}}
    <script>
        function adjustQty(btn, amount) {
            const input = btn.parentElement.querySelector('.qty-input');
            let newVal = parseInt(input.value) + amount;
            if (newVal < 0) newVal = 0;
            input.value = newVal;

            // 动态切换高亮样式
            const row = btn.closest('.asset-row');
            const label = row.querySelector('span');
            if (newVal > 0) {
                row.classList.add('border-indigo-200', 'ring-1', 'ring-indigo-50');
                label.classList.add('text-indigo-700', 'font-bold');
            } else {
                row.classList.remove('border-indigo-200', 'ring-1', 'ring-indigo-50');
                label.classList.remove('text-indigo-700', 'font-bold');
            }
        }
    </script>
</x-app-layout>