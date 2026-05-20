@props(['name' => 'search', 'placeholder' => 'Search...', 'value' => null])

{{-- 使用 flex-wrap 在小屏幕下自动换行，保持布局稳定 --}}
<form action="{{ url()->current() }}" method="GET" class="flex flex-wrap items-center gap-2">
    <div class="relative flex-grow sm:flex-grow-0">
        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </div>
        
        <x-form.text-input 
            name="{{ $name }}" 
            value="{{ request($name, $value) }}"
            placeholder="{{ $placeholder }}"
            {{-- w-full 移动端填满，sm:w-80 在电脑端固定宽度 --}}
            class="block w-full sm:w-80 py-2.5 pl-11 pr-4" 
        />
    </div>

    <x-form.primary-button type="submit" loading="loading" class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium">
        Search
    </x-form.primary-button>
</form>