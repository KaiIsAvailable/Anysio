<x-app-layout>
    <div class="bg-gray-50 min-h-screen p-6">
        <div class="max-w-3xl mx-auto">

            {{-- Header (same style as Owner create) --}}
            <div class="flex items-center justify-between mb-6">
                <div class="flex-1 flex justify-start">
                    <a href="{{ route('admin.rooms.index') }}"
                       class="inline-flex items-center text-gray-500 hover:text-indigo-600 transition-colors">
                        <svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back
                    </a>
                </div>

                <div class="text-center">
                    <h1 class="text-2xl font-bold text-slate-900 font-sans whitespace-nowrap">Add Room</h1>
                    <p class="mt-1 text-sm text-gray-500">Create a room and add multiple assets in one form.</p>
                </div>

                <div class="flex-1"></div>
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

            <form method="POST" action="{{ route('admin.rooms.store') }}">
                @csrf

                {{-- Card wrapper (same as Owner create) --}}
                <div class="bg-white shadow-lg rounded-xl p-6 space-y-6">

                    {{-- Room Details --}}
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Room Details</h2>
                        <div class="mt-4 space-y-4">

                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Select Owner</label>
                                
                                @can('agent-admin')
                                    {{-- agentAdmin以上的role可以自由选择 --}}
                                    <select name="owner_id" id="owner_select"
                                            class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                            required>
                                        <option value="">-- Select Owner --</option>
                                        @foreach($owners as $o)
                                            <option value="{{ $o->id }}" data-user-id="{{ $o->user_id }}" @selected(old('owner_id') == $o->id)>
                                                {{ $o->user->name ?? '—' }} ({{ $o->user->email ?? '—' }})
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    {{-- Owner Admin: 只能看到自己，且不可更改 --}}
                                    @php
                                        // 获取当前登录用户对应的 Owner 模型记录
                                        $currentOwner = $owners->where('user_id', auth()->id())->first();
                                    @endphp

                                    <div class="relative">
                                        {{-- 显示给用户看的“假”框（Disabled 状态） --}}
                                        <select class="w-full rounded-lg border-gray-100 bg-gray-50 text-gray-500 cursor-not-allowed shadow-sm" disabled>
                                            <option selected>
                                                {{ auth()->user()->name }} ({{ auth()->user()->email }})
                                            </option>
                                        </select>
                                        
                                        {{-- 实际提交给后端的“真”数据（Hidden Input） --}}
                                        {{-- 注意：Disabled 的 select 不会提交数据，所以必须用 hidden 传值 --}}
                                        <input type="hidden" name="owner_id" value="{{ $currentOwner->id ?? '' }}">
                                    </div>
                                @endcan

                                @error('owner_id') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-900 mb-1">Room Number</label>
                                    <input name="room_no"
                                           value="{{ old('room_no') }}"
                                           placeholder="exp: 1-1-1A"
                                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                           required>
                                    @error('room_no') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-slate-900 mb-1">Room Type</label>
                                    <input name="room_type"
                                           value="{{ old('room_type') }}"
                                           placeholder="exp: Single room"
                                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                           required>
                                    @error('room_type') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                                </div>

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

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-slate-900 mb-1">Address</label>
                                    <input name="address"
                                           value="{{ old('address') }}"
                                           placeholder="exp: no100, jalan jaya jalan, taman ampang, 51900, kuala lumpur"
                                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                           required>
                                    @error('address') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- Divider --}}
                    <hr class="border-gray-100">

                    {{-- Assets --}}
                    @php $today = now()->toDateString(); @endphp

                    <div>
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-slate-900">Room Assets</h2>

                            <button type="button" id="addAssetBtn"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow transition duration-150 ease-in-out">
                                + Add Asset
                            </button>
                        </div>

                        <p class="mt-2 text-sm text-gray-500">You can add more than one asset before saving.</p>

                        <div class="mt-4 space-y-4" id="assetList"></div>

                        <template id="assetRowTpl">
                            <div class="asset-row rounded-xl border border-gray-200 bg-gray-50 p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="text-sm font-semibold text-slate-900">Asset</div>
                                    <button type="button"
                                            class="remove-asset text-sm text-red-600 hover:text-red-800">
                                        Remove
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-900 mb-1">Name</label>
                                        <div class="relative group">
                                            {{-- 底层的输入框 --}}
                                            <input type="text" 
                                                name="assets[__i__][name]" 
                                                placeholder="Search or type new..."
                                                class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm pr-10"
                                                required>

                                            {{-- 顶层的 Select：全覆盖 inset-0，确保弹出框对齐 --}}
                                            {{-- 关键：使用 onmousedown 逻辑判断点击位置 --}}
                                            <select class="asset-selector absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                                    onmousedown="if(event.offsetX < this.offsetWidth - 40) { this.previousElementSibling.focus(); return false; }"
                                                    onchange="this.previousElementSibling.value = this.value; this.previousElementSibling.focus();">
                                                <option value="">-- Select or Type --</option>
                                                @foreach($assetLibrary as $lib)
                                                    <option value="{{ $lib->name }}">{{ $lib->name }}</option>
                                                @endforeach
                                            </select>

                                            {{-- 右侧箭头：视觉装饰 --}}
                                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                                <svg class="h-4 w-4 text-gray-400 group-hover:text-indigo-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-slate-900 mb-1">Condition</label>
                                        <select name="assets[__i__][condition]"
                                                class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                            <option value="Good">Good</option>
                                            <option value="Broken">Broken</option>
                                            <option value="Maintaining">Maintaining</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-slate-900 mb-1">Last Maintenance</label>
                                        <input type="date"
                                               name="assets[__i__][last_maintenance]"
                                               max="{{ $today }}"
                                               class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                        <div class="text-xs text-gray-400 mt-1">Cannot be a future date. Leave empty for NULL.</div>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-slate-900 mb-1">Remark</label>
                                        <input name="assets[__i__][remark]"
                                               placeholder="(optional)"
                                               class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                        <div class="text-xs text-gray-400 mt-1">Leave empty for NULL.</div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="mt-4 text-sm text-gray-600 space-y-1">
                            <div>• Condition options: Good / Broken / Maintaining.</div>
                            <div>• Last Maintenance / Remark can be empty (NULL).</div>
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

            <script>
                (function () {
                    const list = document.getElementById('assetList');
                    const tpl = document.getElementById('assetRowTpl');
                    const addBtn = document.getElementById('addAssetBtn');
                    const ownerSelect = document.getElementById('owner_select');
                    const fullLibrary = @json($assetLibrary);
                    let idx = 0;

                    function getFilteredOptionsHtml() {
                        const selectedOption = ownerSelect.options[ownerSelect.selectedIndex];
                        const userId = selectedOption ? selectedOption.getAttribute('data-user-id') : null;

                        let html = '<option value="">-- Select or Type --</option>';
                        if (userId) {
                            const filtered = fullLibrary.filter(a => String(a.user_id) === String(userId));
                            filtered.forEach(asset => {
                                html += `<option value="${asset.name}">${asset.name}</option>`;
                            });
                        }
                        return html;
                    }

                    // 修正点 1：在这里添加参数 isInitial
                    function addRow(isInitial = false) {
                        const templateHtml = tpl.innerHTML.replaceAll('__i__', String(idx));
                        const wrap = document.createElement('div');
                        wrap.innerHTML = templateHtml.trim();
                        const row = wrap.firstElementChild;

                        const selector = row.querySelector('.asset-selector');
                        if (selector) selector.innerHTML = getFilteredOptionsHtml();

                        // 修正点 2：根据 isInitial 判断是否移除删除按钮
                        if (isInitial) {
                            const removeBtn = row.querySelector('.remove-asset');
                            if (removeBtn) removeBtn.remove(); 
                            
                            // 确保你的 HTML template 里有这个 class 为 uppercase 的 span
                            const titleSpan = row.querySelector('.uppercase');
                            if (titleSpan) titleSpan.textContent = "Primary Asset (Required)";
                        } else {
                            const removeBtn = row.querySelector('.remove-asset');
                            if (removeBtn) {
                                removeBtn.addEventListener('click', () => row.remove());
                            }
                        }

                        list.appendChild(row);
                        idx++;
                    }

                    ownerSelect.addEventListener('change', function() {
                        const newOptions = getFilteredOptionsHtml();
                        document.querySelectorAll('.asset-selector').forEach(s => {
                            s.innerHTML = newOptions;
                        });
                    });

                    // 普通点击：传入 false
                    addBtn.addEventListener('click', () => addRow(false));
                    
                    // 修正点 3：初始化页面时传入 true
                    addRow(true);
                })();
            </script>

        </div>
    </div>
    
</x-app-layout>
