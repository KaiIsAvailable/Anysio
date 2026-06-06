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
                        <h2 class="text-lg font-semibold text-slate-900">Select Owner</h2>
                    </div>

                    <x-form.input-select
                        name="target_user_id"
                        :options="$userOptions"
                        :value="$selectedUserId"
                        placeholder="-- Choose who this asset belongs to --"
                        onchange="window.location.href = this.value ? '{{ route('admin.roomAsset.create') }}?user_id=' + this.value : '{{ route('admin.roomAsset.create') }}'"
                        :class="'w-full transition ' . (auth()->user()->role === 'ownerAdmin' ? 'bg-gray-100 cursor-not-allowed opacity-75' : '')"
                        :disabled="auth()->user()->role === 'ownerAdmin'" />
                </section>

                {{-- Step 2: Assets List --}}
                @if($selectedUserId)
                <x-form.form action="{{ route('admin.roomAsset.store') }}">
                    <input type="hidden" name="target_user_id" value="{{ $selectedUserId }}">

                    <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100">
                        <div class="px-6 py-5 border-b border-gray-50 bg-white flex items-center justify-between">
                            <div class="flex items-center">
                                <h2 class="text-lg font-semibold text-slate-900">Assets to Add</h2>
                            </div>
                            <div class="flex space-x-2">
                                <x-form.primary-button type="button"
                                    onclick="openAssetModal()"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg shadow-md transition transform active:scale-95 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1">
                                    {{-- SVG 图标 --}}
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    <span>Batch Select</span>
                                </x-form.primary-button>
                                <button type="button" id="addAssetBtn" class="bg-gray-100 hover:bg-gray-200 text-slate-700 font-bold py-2 px-4 rounded-lg transition text-sm border border-gray-200">
                                    + Custom
                                </button>
                            </div>
                        </div>

                        <div id="assetList" class="p-6 space-y-4">
                            @if(old('assets'))
                            @foreach(old('assets') as $i => $asset)
                            <div class="asset-row rounded-xl border border-gray-200 bg-gray-50 p-4 relative group animate-fadeIn transition-all hover:border-indigo-300">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="text-xs font-bold text-indigo-600 uppercase tracking-wider">
                                        Asset Entry #{{ $i + 1 }}
                                    </div>
                                    <button type="button" class="remove-asset text-xs text-red-500 hover:text-red-700 font-bold transition">Remove</button>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <x-form.input-label value="Asset Name" class="mb-1" />
                                        <x-form.text-input
                                            name="assets[{{ $i }}][name]"
                                            value="{{ $asset['name'] ?? '' }}"
                                            class="w-full {{ $errors->has('assets.'.$i.'.name') ? 'border-red-500 focus:ring-red-500' : '' }}"
                                            placeholder="e.g. Fridge"
                                            required />

                                        <x-form.input-error :messages="$errors->get('assets.'.$i.'.name')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-form.input-label value="Category" class="mb-1" />
                                        <x-form.input-select
                                            name="assets[{{ $i }}][category]"
                                            :options="['General' => 'General', 'Furniture' => 'Furniture', 'Appliances' => 'Appliances', 'Electronics' => 'Electronics']"
                                            :value="$asset['category'] ?? ''"
                                            class="w-full" />
                                    </div>
                                </div>
                            </div>
                            @endforeach
                            @endif
                        </div>

                        <div class="px-6 py-5 bg-gray-50 border-t border-gray-100 flex justify-end">
                            <x-form.primary-button loading="loading" class="py-3 px-10">
                                Save to User Library
                            </x-form.primary-button>
                        </div>
                    </div>
                </x-form.form>
                @endif
            </div>
        </div>
    </div>

    {{-- Asset Row Template --}}
    <template id="assetRowTpl">
        <div class="asset-row rounded-xl border border-gray-200 bg-gray-50 p-4 relative group animate-fadeIn transition-all hover:border-indigo-300">
            <div class="flex items-center justify-between mb-3">
                <div class="text-xs font-bold text-indigo-600 uppercase tracking-wider">New Asset Entry</div>
                <button type="button" class="remove-asset text-xs text-red-500 hover:text-red-700 font-bold transition">Remove</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-form.input-label value="Asset Name" class="mb-1" />
                    <x-form.text-input name="assets[__i__][name]" class="w-full" placeholder="e.g. Fridge" required />
                </div>
                <div>
                    <x-form.input-label value="Category" class="mb-1" />
                    <x-form.input-select
                        name="assets[__i__][category]"
                        :options="['General' => 'General', 'Furniture' => 'Furniture', 'Appliances' => 'Appliances', 'Electronics' => 'Electronics']"
                        class="w-full" />
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
                        @php
                        // 判断资产是否已被该用户拥有
                        $isOwned = in_array($lib->name, $existingAssetNames ?? []);
                        @endphp

                        {{-- 根据是否拥有，动态切换背景和光标样式 --}}
                        <label class="flex items-center p-3 border border-gray-100 rounded-lg transition group {{ $isOwned ? 'bg-gray-100 cursor-not-allowed opacity-75' : 'cursor-pointer hover:bg-indigo-50 hover:border-indigo-200' }}">

                            {{-- 若已拥有，加入 disabled 禁止勾选 --}}
                            <input type="checkbox"
                                class="asset-checkbox h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                value="{{ $lib->name }}"
                                data-category="{{ $lib->category }}"
                                {{ $isOwned ? 'disabled' : '' }}>

                            {{-- 若已拥有，文字变灰并显示 Already Exists --}}
                            <span class="ml-3 text-sm font-medium {{ $isOwned ? 'text-gray-400' : 'text-slate-700 group-hover:text-indigo-700' }}">
                                {{ $lib->name }} ({{ $lib->category }})
                                @if($isOwned)
                                <span class="ml-1 text-[10px] bg-gray-200 text-gray-500 px-1.5 py-0.5 rounded font-normal">Already Exists</span>
                                @endif
                            </span>
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
        .animate-fadeIn {
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</x-app-layout>