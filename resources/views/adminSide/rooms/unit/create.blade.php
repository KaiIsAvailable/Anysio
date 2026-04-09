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
                <h1 class="text-2xl font-bold text-slate-900 mt-2">Add New Unit</h1>
            </div>

            <form method="POST" action="{{ route('admin.units.store') }}">
                @csrf

                <div class="bg-white shadow-lg rounded-xl p-6 space-y-8">
                    
                    {{-- 第一部分：关联关系 --}}
                    <section>
                        <h2 class="text-lg font-semibold text-slate-900 mb-4 border-b pb-2">Associations</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Property</label>
                                
                                @if(isset($targetProperty))
                                    <div class="w-full px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg text-slate-700 font-medium flex items-center shadow-sm">
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
                                @if(isset($targetOwner))
                                    <div class="w-full px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg text-slate-700 font-medium flex items-center shadow-sm">
                                        {{ $targetOwner->user->name }}
                                    </div>
                                    <input type="hidden" name="owner_id" value="{{ $targetOwner->id }}">
                                @else
                                    <select name="owner_id" id="owner_selector" onchange="filterAssetsByOwner()" class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                                        <option value="">-- Select Owner (Optional) --</option>
                                        @foreach($owners as $owner)
                                            {{-- 统一使用 $owner->id --}}
                                            <option value="{{ $owner->id }}" 
                                                data-user-id="{{ $owner->user_id }}" 
                                                @selected(old('owner_id') == $owner->id)>
                                                {{ $owner->user->name }} {{ $owner->company_name ? "({$owner->company_name})" : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                        </div>
                    </section>

                    {{-- 第二部分：单位基本信息 --}}
                    <section>
                        <h2 class="text-lg font-semibold text-slate-900 mb-4 border-b pb-2">Unit Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Unit No.</label>
                                <input type="text" name="unit_no" value="{{ old('unit_no') }}" 
                                    class="w-full rounded-lg @error('unit_no') border-red-500 @enderror border-gray-300 focus:ring-indigo-500 shadow-sm" required>
                                @error('unit_no')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Block / Tower</label>
                                <input type="text" name="block" value="{{ old('block') }}" placeholder="e.g. Block A" 
                                       class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Floor</label>
                                <input type="text" name="floor" value="{{ old('floor') }}" placeholder="e.g. 10" 
                                       class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Size (Sqft)</label>
                                <div class="relative">
                                    <input type="number" name="sqft" value="{{ old('sqft') }}" 
                                           class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
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
                                <input type="text" name="electricity_acc_no" value="{{ old('electricity_acc_no') }}" 
                                       class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-900 mb-1">Water Acc No. (Air Selangor/SAJ)</label>
                                <input type="text" name="water_acc_no" value="{{ old('water_acc_no') }}" 
                                       class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-slate-900 mb-1">Status</label>
                            <select name="status" class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                                <option value="Vacant" @selected(old('status') == 'Vacant')>Vacant</option>
                                <option value="Occupied" @selected(old('status') == 'Occupied')>Occupied</option>
                                <option value="Under Maintenance" @selected(old('status') == 'Under Maintenance')>Under Maintenance</option>
                            </select>
                        </div>
                    </section>

                    <section>
                        {{-- 在 Status 字段下面或者适当位置插入 --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-4 bg-indigo-50 rounded-xl border border-indigo-100">
                            {{-- Has Rooms 开关 --}}
                            <div>
                                <label class="block text-sm font-bold text-indigo-900 mb-1">Any Rooms inside Unit?</label>
                                <select name="has_rooms" id="has_rooms" onchange="toggleRoomInput()" 
                                        class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                                    <option value="0" @selected(old('has_rooms') == 0)>No</option>
                                    <option value="1" @selected(old('has_rooms') == 1)>Yes</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-indigo-900 mb-1">Add Assets for this Unit?</label>
                                <select name="has_unit_assets" id="has_unit_assets" onchange="toggleUnitAssetInput()" 
                                        class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                                    <option value="0" @selected(old('has_unit_assets') == 0)>No</option>
                                    <option value="1" @selected(old('has_unit_assets') == 1)>Yes</option>
                                </select>
                                <p id="helper_text" class="mt-1 text-xs text-gray-500 italic">Common area assets like Sofa, Fridge etc.</p>
                            </div>

                            {{-- Total Rooms 数量 --}}
                            <div id="total_rooms_container" style="{{ old('has_rooms') == 1 ? '' : 'display:none;' }}">
                                <label class="block text-sm font-bold text-indigo-900 mb-1">Total Number of Rooms</label>
                                <input type="number" 
                                    id="total_rooms_input"
                                    name="total_rooms" 
                                    value="{{ old('total_rooms', 0) }}" 
                                    min="0" 
                                    max="10" 
                                    oninput="if(value>10)value=10; if(value<0)value=0; generateRoomAssetFields()"
                                    class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 shadow-sm">
                                    <p id="helper_text" class="mt-1 text-xs text-gray-500 italic">Max room in a Unit:s 10. For more rooms, you may add inside room.</p>
                            </div>
                        </div>

                        {{-- 新增：Unit 资产配置容器 (默认隐藏) --}}
                        <div id="unit_assets_configuration" class="mt-4 mb-6 pr-2" style="display: none;">
                            <div class="block w-full p-4 bg-white border border-gray-200 rounded-xl shadow-sm mb-4 room-card" data-room-index="unit">
                                <div class="flex items-center justify-between mb-3 border-b pb-2">
                                    <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-7h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                        Unit Common Area Assets
                                    </h3>
                                </div>
                                
                                <div class="custom-scrollbar mb-2 p-1 bg-gray-50 rounded-lg" style="height: 180px; overflow-y: auto !important; border: 1px solid #f1f5f9;">
                                    <div class="grid grid-cols-3 gap-x-4 gap-y-2">
                                        @foreach($assetLibrary as $lib)
                                        @if($lib->status === "Active")
                                            <div class="flex items-center justify-between py-1.5 px-2 bg-white border border-gray-100 rounded-md shadow-sm hover:border-indigo-200 transition-all" data-asset-owner-id="{{ $lib->user_id }}">
                                                <input type="checkbox" name="unit_assets[{{ $loop->index }}][id]" value="{{ $lib->id }}" class="hidden asset-checkbox">
                                                <span class="text-[12px] text-slate-600 font-semibold truncate flex-1 mr-2">{{ $lib->name }}</span>
                                                <div class="flex items-center bg-gray-50 rounded-md p-0.5 border border-gray-100">
                                                    <button type="button" onclick="adjustQty(this, -1)" class="w-5 h-5 flex items-center justify-center rounded bg-white text-gray-400 hover:text-red-500 border border-gray-100">-</button>
                                                    <input type="text" name="unit_assets[{{ $loop->index }}][qty]" value="0" readonly class="qty-input w-10 text-center text-[12px]">
                                                    <button type="button" onclick="adjustQty(this, 1)" class="w-5 h-5 flex items-center justify-center rounded bg-white text-gray-400 hover:text-indigo-600 border border-gray-100">+</button>
                                                </div>
                                        </div>
                                        @endif
                                        @endforeach
                                        {{-- 这里的提示框逻辑复用 filterAssetsByOwner --}}
                                        <div class="no-asset-message hidden col-span-3 flex flex-col items-center justify-center h-[160px]">
                                            <span class="text-[13px] text-slate-400 font-medium">No assets found for this owner</span>
                                        </div>
                                        <div class="select-owner-message hidden col-span-3 flex flex-col items-center justify-center h-[160px]">
                                            <span class="text-[13px] text-slate-400 font-medium">Please Select Owner First</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 关键：在这里添加这个容器 --}}
                        <br>
                        <div id="rooms_configuration" class="space-y-4 mb-6 pr-2"></div>

                        {{-- 操作按钮 --}}
                        <div class="flex justify-end gap-2 pt-4 border-t border-gray-100"></div>


                        <script>
                            function toggleRoomInput() {
                                const hasRooms = document.getElementById('has_rooms').value;
                                const container = document.getElementById('total_rooms_container');
                                const configContainer = document.getElementById('rooms_configuration');
                                
                                if (hasRooms == "1") {
                                    container.style.display = 'block';
                                    generateRoomAssetFields();
                                } else {
                                    container.style.display = 'none';
                                    configContainer.innerHTML = '';
                                }
                            }

                            document.addEventListener('DOMContentLoaded', function() {
                                // 1. 获取旧数据 (利用 Laravel Blade 语法直接转成 JS 对象)
                                const oldRooms = @json(old('rooms'));
                                const oldUnitAssets = @json(old('unit_assets'));
                                const hasRoomsValue = document.getElementById('has_rooms').value;

                                // 2. 恢复 Unit 级别的资产 (Common Area)
                                if (oldUnitAssets) {
                                    Object.keys(oldUnitAssets).forEach(index => {
                                        const asset = oldUnitAssets[index];
                                        if (asset && asset.qty > 0) {
                                            // 寻找单元资产中对应的 input 并赋值
                                            const qtyInput = document.querySelector(`input[name="unit_assets[${index}][qty]"]`);
                                            if (qtyInput) {
                                                qtyInput.value = asset.qty;
                                                // 触发你之前的勾选逻辑（如背景变色或隐藏 checkbox 勾选）
                                                syncCheckbox(qtyInput); 
                                            }
                                        }
                                    });
                                }

                                // 3. 处理 Room 逻辑
                                if (hasRoomsValue == "1") {
                                    // 如果有旧的房间数据，我们根据旧数据的数量来生成
                                    if (oldRooms && oldRooms.length > 0) {
                                        const container = document.getElementById('rooms_configuration');
                                        container.innerHTML = ''; // 清空初始化时的默认占位

                                        oldRooms.forEach((roomData, i) => {
                                            // 调用你生成 HTML 的函数，并传入 roomData 进行填充
                                            addRoomCardWithData(i, roomData);
                                        });
                                        
                                        // 记得更新全局的 roomIndex 计数器，防止点击 "Add Room" 按钮时索引冲突
                                        if (typeof roomIndex !== 'undefined') {
                                            roomIndex = oldRooms.length;
                                        }
                                    } else {
                                        // 如果没有旧数据（第一次进入页面），则按原逻辑生成
                                        generateRoomAssetFields();
                                    }
                                }

                                // 4. 最后运行一次过滤（根据当前选中的 Owner 过滤资产显示）
                                filterAssetsByOwner();
                            });


                            // 控制 Unit 资产容器的显示隐藏
                            function toggleUnitAssetInput() {
                                const hasAssets = document.getElementById('has_unit_assets').value;
                                const container = document.getElementById('unit_assets_configuration');
                                
                                if (hasAssets == "1") {
                                    container.style.display = 'block';
                                    // 每次打开时重新过滤一遍资产（基于当前选中的 Owner）
                                    filterAssetsByOwner();
                                } else {
                                    container.style.display = 'none';
                                    // 如果选了 No，清空 Unit 资产的数量（可选）
                                    container.querySelectorAll('.qty-input').forEach(input => {
                                        input.value = 0;
                                        syncCheckbox(input);
                                    });
                                }
                            }

                            // 修改现有的 DOMContentLoaded 确保页面加载时能恢复状态
                            document.addEventListener('DOMContentLoaded', function() {
                                // ... 原有逻辑 ...
                                toggleUnitAssetInput(); // 初始化 Unit 资产显示状态
                            });

                            function filterAssetsByOwner() {
                                const selector = document.getElementById('owner_selector');
                                if (!selector) return;

                                const selectedOption = selector.options[selector.selectedIndex];
                                const selectedUserId = selectedOption.getAttribute('data-user-id') || "";
                                
                                // 1. 拿到所有的 Room Card
                                const allRoomCards = document.querySelectorAll('.room-card');

                                allRoomCards.forEach(roomCard => {
                                    let visibleCount = 0;
                                    
                                    // 2. 在当前 Room 卡片内查找提示框和资产
                                    // 注意：这里必须用 querySelector('.class') 因为 ID 在多个房间里是重复的
                                    const noAssetMsg = roomCard.querySelector('.no-asset-message');
                                    const selectOwnerMsg = roomCard.querySelector('.select-owner-message');
                                    const assetItems = roomCard.querySelectorAll('[data-asset-owner-id]');

                                    assetItems.forEach(item => {
                                        const itemOwnerId = item.getAttribute('data-asset-owner-id');

                                        // 逻辑：只有当【选了业主】且【ID匹配】时才显示
                                        if (selectedUserId !== "" && itemOwnerId == selectedUserId) {
                                            item.style.setProperty('display', 'flex', 'important');
                                            visibleCount++; 
                                        } else {
                                            item.style.setProperty('display', 'none', 'important');
                                            
                                            // 自动归零逻辑
                                            const qtyInput = item.querySelector('.qty-input');
                                            if (qtyInput && parseInt(qtyInput.value) > 0) {
                                                qtyInput.value = 0;
                                                if (typeof syncCheckbox === 'function') syncCheckbox(qtyInput); 
                                            }
                                        }
                                    });

                                    // 3. 提示框显隐逻辑
                                    if (selectedUserId === "") {
                                        // 情况 A：没选业主 -> 显示 "Please Select Owner"，隐藏 "Please Add Asset"
                                        if (selectOwnerMsg) selectOwnerMsg.classList.remove('hidden');
                                        if (noAssetMsg) noAssetMsg.classList.add('hidden');
                                    } else {
                                        // 情况 B：选了业主 -> 隐藏 "Please Select Owner"
                                        if (selectOwnerMsg) selectOwnerMsg.classList.add('hidden');

                                        // 根据当前房间是否有显示的资产，决定是否显示 "Please Add Asset"
                                        if (visibleCount === 0) {
                                            if (noAssetMsg) noAssetMsg.classList.remove('hidden');
                                        } else {
                                            if (noAssetMsg) noAssetMsg.classList.add('hidden');
                                        }
                                    }
                                });
                            }

                            function generateRoomAssetFields() {
                                const count = document.getElementById('total_rooms_input').value;
                                const container = document.getElementById('rooms_configuration');
                                
                                if (count > 10) return;
                                if (count < 0) { container.innerHTML = ''; return; }
                                if (!count || count <= 0) { 
                                    container.innerHTML = ''; 
                                    return; 
                                }

                                let html = '';
                                for (let i = 1; i <= count; i++) {
                                    const isFirstRoom = (i === 1);
                                    
                                    // --- 核心修改：在 JS 循环里直接引用 PHP 的 old() ---
                                    // 我们利用模板字符串嵌入 PHP 表达式
                                    const oldRoomNo = @json(old('rooms')) ? (@json(old('rooms'))[i]?.room_no || '') : '';
                                    const oldRoomType = @json(old('rooms')) ? (@json(old('rooms'))[i]?.room_type || '') : '';

                                    html += `
                                    <div class="block w-full p-4 bg-white border border-gray-200 rounded-xl shadow-sm mb-4 room-card" data-room-index="${i}">
                                        <div class="flex items-center justify-between mb-3 border-b pb-2">
                                            <div class="flex items-center gap-3">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                                </svg>
                                                <h3 class="font-bold text-slate-800 text-sm">Room ${i}</h3>
                                                ${isFirstRoom ? `
                                                    <button type="button" onclick="syncAllRooms()" class="text-[10px] bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full border border-indigo-100 hover:bg-indigo-600 hover:text-white transition-all flex items-center gap-1">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                                        Apply to all rooms
                                                    </button>
                                                ` : ''}
                                            </div>
                                            <span class="text-[10px] text-gray-400 font-mono">#RM-0${i}</span>
                                        </div>
                                        
                                        <div class="custom-scrollbar mb-2 p-1 bg-gray-50 rounded-lg" style="height: 180px; overflow-y: auto !important; border: 1px solid #f1f5f9;">
                                            <div class="grid grid-cols-3 gap-x-4 gap-y-2">
                                                @foreach($assetLibrary as $lib)
                                                @if($lib->status === "Active")
                                                    <div class="flex items-center justify-between py-1.5 px-2 bg-white border border-gray-100 rounded-md shadow-sm hover:border-indigo-200 transition-all" data-asset-owner-id="{{ $lib->user_id }}">
                                                        <input type="checkbox" name="rooms[${i}][assets][{{ $loop->index }}][id]" value="{{ $lib->id }}" class="hidden asset-checkbox">
                                                        
                                                        <span class="text-[12px] text-slate-600 font-semibold truncate flex-1 mr-2" title="{{ $lib->name }}">
                                                            {{ $lib->name }}
                                                        </span>
                                                        
                                                        <div class="flex items-center bg-gray-50 rounded-md p-0.5 border border-gray-100">
                                                            <button type="button" onclick="adjustQty(this, -1)" 
                                                                class="w-5 h-5 flex items-center justify-center rounded bg-white text-gray-400 hover:text-red-500 hover:shadow-sm transition-all text-xs border border-gray-100">-</button>
                                    
                                                            <input type="text" 
                                                                name="rooms[${i}][assets][{{ $loop->index }}][qty]" 
                                                                data-asset-index="{{ $loop->index }}" 
                                                                value="${@json(old('rooms')) ? (@json(old('rooms'))[i]?.assets[{{ $loop->index }}]?.qty || 0) : 0}" 
                                                                readonly
                                                                class="qty-input w-10 text-center text-[12px]"
                                                                onchange="syncCheckbox(this)">

                                                            <button type="button" onclick="adjustQty(this, 1)" 
                                                                class="w-5 h-5 flex items-center justify-center rounded bg-white text-gray-400 hover:text-indigo-600 hover:shadow-sm transition-all text-xs border border-gray-100">+</button>
                                                        </div>
                                                    </div>
                                                @endif
                                                @endforeach
                                                <div class="no-asset-message hidden col-span-3 flex flex-col items-center justify-center h-[160px] bg-gray-50/50">
                                                    <span class="text-[13px] text-slate-400 font-medium">Please Add Asset</span>
                                                </div>
                                                <div class="select-owner-message hidden col-span-3 flex flex-col items-center justify-center h-[160px] bg-gray-50/50">
                                                    <span class="text-[13px] text-slate-400 font-medium">Please Select Owner</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-bold text-slate-800 mb-1.5 ml-1">Room Number</label>
                                                <input type="text" 
                                                    name="rooms[${i}][room_no]" 
                                                    value="${oldRoomNo}" 
                                                    placeholder="e.g. A-01-02"
                                                    required
                                                    class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all"
                                                >
                                            </div>
                                            <div>
                                                <label class="block text-sm font-bold text-slate-800 mb-1.5 ml-1">Room Type</label>
                                                <input type="text" 
                                                    name="rooms[${i}][room_type]" 
                                                    value="${oldRoomType}" 
                                                    placeholder="e.g. Master Bedroom"
                                                    required
                                                    class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all"
                                                >
                                            </div>
                                        </div>
                                    </div>`;
                                }
                                container.innerHTML = html;

                                // --- 恢复视觉状态 ---
                                // 渲染完后，需要让那些 qty > 0 的资产框变色（触发你原有的 syncCheckbox）
                                container.querySelectorAll('.qty-input').forEach(input => {
                                    if (parseInt(input.value) > 0) {
                                        syncCheckbox(input);
                                    }
                                });

                                filterAssetsByOwner();
                            }

                            function adjustQty(btn, change) {
                                const input = btn.parentElement.querySelector('input[type="text"]');
                                let newVal = parseInt(input.value) + change;
                                if (newVal < 0) newVal = 0;
                                input.value = newVal;
                                
                                syncCheckbox(input);
                            }

                            function syncCheckbox(input) {
                                // 确保能找到同级的 checkbox
                                const row = input.closest('.flex.items-center.justify-between');
                                if (!row) return;

                                const checkbox = row.querySelector('.asset-checkbox');
                                if (checkbox) {
                                    checkbox.checked = parseInt(input.value) > 0;
                                }
                            }

                            function syncAllRooms() {
                                const totalRooms = document.getElementById('total_rooms_input').value;
                                if (totalRooms <= 1) return;
                                
                                if (!confirm('This will copy Room 1 assets to all other rooms. Continue?')) return;

                                // 1. 只获取 Room 1 容器内的 qty 输入框 (通过类名或属性精准定位)
                                const firstRoomCard = document.querySelector('.room-card[data-room-index="1"]');
                                if (!firstRoomCard) {
                                    console.error("Room 1 not found");
                                    return;
                                }
                                
                                const firstRoomInputs = firstRoomCard.querySelectorAll('input[data-asset-index]');

                                firstRoomInputs.forEach((input) => {
                                    const assetIndex = input.getAttribute('data-asset-index');
                                    const qty = input.value;

                                    // 2. 遍历其他房间
                                    for (let i = 2; i <= totalRooms; i++) {
                                        // 使用属性选择器精准定位：第 i 个房间中，asset-index 相同的那个 input
                                        const targetInput = document.querySelector(`input[name="rooms[${i}][assets][${assetIndex}][qty]"]`);
                                        
                                        if (targetInput) {
                                            targetInput.value = qty;
                                            // 3. 必须手动调用同步 checkbox 状态
                                            syncCheckbox(targetInput);
                                        }
                                    }
                                });
                            }
                        </script>
                    </section>

                    {{-- 操作按钮 --}}
                    <div class="flex justify-end gap-2 pt-4 border-t border-gray-100">
                        <a href="{{ route('admin.properties.show', $targetProperty->id ) }}" class="px-6 py-2 text-slate-700 font-bold border rounded-lg hover:bg-gray-50">Cancel</a>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-8 rounded-lg shadow-md transition">
                            Create Unit
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>