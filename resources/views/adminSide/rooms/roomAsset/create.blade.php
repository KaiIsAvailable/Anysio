<x-app-layout>
    <div class="bg-gray-50 min-h-screen p-6">
        <div class="max-w-3xl mx-auto">
            {{-- Header --}}
            <div class="mb-6">
                <a href="{{ route('admin.roomAsset.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to List
                </a>
                <h1 class="text-2xl font-bold text-slate-900 mt-2">Manage User Asset Library</h1>
                <p class="text-gray-500 text-sm">Pre-define common assets for specific owners or agents.</p>
            </div>

            <div class="space-y-6">
                {{-- Step 1: Owner Selection (Card Style) --}}
                <section class="bg-white shadow-lg rounded-xl p-6 border border-gray-100">
                    <div class="flex items-center mb-4">
                        <h2 class="text-lg font-semibold text-slate-900">Select Owner / Agent</h2>
                    </div>
                    
                    <select name="target_user_id" 
                            onchange="if(this.value) window.location.href = '{{ route('admin.roomAsset.create') }}?user_id=' + this.value"
                            class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition">
                        <option value="">-- Choose who this asset belongs to --</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }} ({{ ucfirst($user->role) }})
                            </option>
                        @endforeach
                    </select>
                </section>

                {{-- Step 2: Assets List --}}
                @if(request('user_id'))
                    <form action="{{ route('admin.roomAsset.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="target_user_id" value="{{ request('user_id') }}">

                        <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100">
                            <div class="px-6 py-5 border-b border-gray-50 bg-white flex items-center justify-between">
                                <div class="flex items-center">
                                    <h2 class="text-lg font-semibold text-slate-900">Assets to Add</h2>
                                </div>
                                <div class="flex space-x-2">
                                    <button type="button" 
                                            onclick="openAssetModal()" 
                                            class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg shadow-md transition transform active:scale-95 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1">
                                        {{-- SVG 图标 --}}
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        <span>Batch Select</span>
                                    </button>
                                    <button type="button" id="addAssetBtn" class="bg-gray-100 hover:bg-gray-200 text-slate-700 font-bold py-2 px-4 rounded-lg transition text-sm border border-gray-200">
                                        + Custom
                                    </button>
                                </div>
                            </div>

                            <div id="assetList" class="p-6 space-y-4">
                                @if(old('assets'))
                                    @foreach(old('assets') as $i => $asset)
                                        {{-- 外层 DIV 保持原样，去掉错误背景色判断 --}}
                                        <div class="asset-row rounded-xl border border-gray-200 bg-gray-50 p-4 relative group animate-fadeIn transition-all hover:border-indigo-300">
                                            <div class="flex items-center justify-between mb-3">
                                                <div class="text-xs font-bold text-indigo-600 uppercase tracking-wider">
                                                    Asset Entry #{{ $i + 1 }}
                                                </div>
                                                {{-- 确保这里的 class 有 remove-asset，方便 JS 绑定删除事件 --}}
                                                <button type="button" class="remove-asset text-xs text-red-500 hover:text-red-700 font-bold transition">Remove</button>
                                            </div>

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-slate-700 mb-1">Asset Name</label>
                                                    {{-- 只有这里的 Border 会变红 --}}
                                                    <input type="text" name="assets[{{ $i }}][name]" 
                                                        value="{{ $asset['name'] ?? '' }}"
                                                        class="w-full rounded-lg {{ $errors->has("assets.$i.name") ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-indigo-500' }} shadow-sm" 
                                                        placeholder="e.g. Fridge" required>
                                                    
                                                    {{-- 错误文字提示 --}}
                                                    @error("assets.$i.name")
                                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                                                    <select name="assets[{{ $i }}][category]" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                                        @foreach(['General', 'Furniture', 'Appliances', 'Electronics'] as $cat)
                                                            <option value="{{ $cat }}" {{ ($asset['category'] ?? '') == $cat ? 'selected' : '' }}>
                                                                {{ $cat }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>

                            <div class="px-6 py-5 bg-gray-50 border-t border-gray-100 flex justify-end">
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-10 rounded-lg shadow-lg transition transform active:scale-95">
                                    Save to User Library
                                </button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Asset Row Template - Matches Room Create Style --}}
    <template id="assetRowTpl">
        <div class="asset-row rounded-xl border border-gray-200 bg-gray-50 p-4 relative group animate-fadeIn transition-all hover:border-indigo-300">
            <div class="flex items-center justify-between mb-3">
                <div class="text-xs font-bold text-indigo-600 uppercase tracking-wider">New Asset Entry</div>
                <button type="button" class="remove-asset text-xs text-red-500 hover:text-red-700 font-bold transition">Remove</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Asset Name</label>
                    <input type="text" name="assets[__i__][name]" 
                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" 
                           placeholder="e.g. Fridge" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                    <select name="assets[__i__][category]" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                        <option value="General">General</option>
                        <option value="Furniture">Furniture</option>
                        <option value="Appliances">Appliances</option>
                        <option value="Electronics">Electronics</option>
                    </select>
                </div>
            </div>
        </div>
    </template>

    {{-- Asset Modal --}}
    <div id="assetModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-slate-900 bg-opacity-60 backdrop-blur-sm transition-opacity" onclick="closeAssetModal()"></div>
            <div class="bg-white rounded-xl overflow-hidden shadow-2xl transform transition-all sm:max-w-lg sm:w-full border border-gray-100">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                    <h3 class="text-lg font-bold text-slate-900">Standard Asset Library</h3>
                    <button type="button" onclick="closeAssetModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                <div class="p-6 max-h-[400px] overflow-y-auto bg-white">
                    <div class="grid grid-cols-2 gap-3">
                        @foreach($assetLibrary as $lib)
                            <label class="flex items-center p-3 border border-gray-100 rounded-lg cursor-pointer hover:bg-indigo-50 hover:border-indigo-200 transition group">
                                <input type="checkbox" class="asset-checkbox h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" value="{{ $lib->name }}" data-category="{{ $lib->category }}">
                                <span class="ml-3 text-sm font-medium text-slate-700 group-hover:text-indigo-700">{{ $lib->name }} ({{ $lib->category }})</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end space-x-3">
                    <button type="button" onclick="closeAssetModal()" class="px-4 py-2 text-sm font-medium text-gray-600 transition hover:text-gray-800">Cancel</button>
                    <button type="button" onclick="confirmBatchAdd()" class="px-6 py-2 bg-indigo-600 text-white text-sm font-bold rounded-lg shadow-md hover:bg-indigo-700 transition">Add Selected Items</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .animate-fadeIn { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</x-app-layout>