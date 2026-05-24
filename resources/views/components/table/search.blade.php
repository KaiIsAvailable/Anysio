@props(['name' => 'search', 'placeholder' => 'Search...', 'value' => null])

{{-- 1. 改为 div，确保没有 form 嵌套 --}}
<div x-data="{ 
    query: @js(request($name, $value)) 
}" 
class="flex flex-wrap items-center">

    {{-- 2. 真正的提交逻辑放在一个独立的 form 里，或者如果这是唯一表单，确保闭合正确 --}}
    <x-form.form action="{{ url()->current() }}" method="GET" 
        x-data="{ query: @js(request('search')) }" 
        class="flex items-stretch"> {{-- 1. 使用 items-stretch 强制拉伸对齐 --}}
        
        <div class="relative flex-1">
            <!-- 搜索图标 -->
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>

            <!-- 2. 使用标准 h-10 -->
            <input type="text" 
                name="search" 
                x-model="query"
                placeholder="{{ $placeholder }}"
                class="block w-64 sm:w-72 h-10 pl-11 pr-10 bg-gray-50 border border-gray-300 text-slate-900 text-sm rounded-l-lg focus:ring-indigo-500 focus:border-indigo-500 transition-colors placeholder-gray-400 outline-none">

            <!-- X 清除按钮 -->
            <button type="button" 
                    x-show="query && query.length > 0"
                    @click="query = ''; window.location.href = '{{ url()->current() }}';"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-red-500 transition-colors">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- 3. 这里同样使用 h-10 并用 -ml-px 盖住边框，实现完美合体 -->
        <x-form.primary-button type="submit" loading="loading"
                class="inline-flex items-center px-4 h-10 text-sm font-medium rounded-r-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors border border-indigo-600 -ml-px">
            Search
        </x-form.primary-button>
    </x-form.form>
</div>