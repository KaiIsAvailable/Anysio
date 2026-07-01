@props(['name' => 'search', 'placeholder' => 'Search...', 'value' => null])

<div x-data="{ query: '{{ request($name, $value) }}' }" class="flex flex-wrap items-stretch">
    <div class="relative flex-1">
        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </div>

        <input type="text"
            id="table-search-input"
            name="{{ $name }}"
            x-model="query"
            placeholder="{{ $placeholder }}"
            class="block w-64 sm:w-72 h-10 pl-11 pr-10 bg-gray-50 border border-gray-300 text-slate-900 text-sm rounded-l-lg focus:ring-indigo-500 focus:border-indigo-500 transition-colors placeholder-gray-400 outline-none">

        <button type="button"
                x-show="query.length > 0"
                @click="query = ''; $nextTick(() => $el.closest('form').submit())"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-red-500 transition-colors"
                style="display: none;">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <button type="submit"
            id="table-search-button"
            class="inline-flex items-center px-4 h-10 text-sm font-medium rounded-r-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors border border-indigo-600 -ml-px">
        Search
    </button>
</div>