@props([
    'value' => null,
    'required' => false,
    'info' => null,
])

<label {{ $attributes->merge(['class' => 'block uppercase font-medium text-sm text-gray-700 flex items-center gap-1.5']) }}>
    {{ $value ?? $slot }}

    @if($required)
        <span class="text-red-500 ml-0.5">*</span>
    @endif

    @if($info)
        <span x-data="{ open: false }" x-cloak class="relative inline-flex items-center text-gray-400 hover:text-gray-600 cursor-help">
            <!-- Info Icon SVG -->
            <svg @mouseenter="open = true" @mouseleave="open = false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
            </svg>

            <!-- Tooltip Popup -->
            <div x-show="open" 
                class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-56 p-2 bg-gray-900 text-white text-xs rounded-md shadow-xl z-50 pointer-events-none normal-case font-normal text-left [&>b]:font-bold">
                {!! $info !!}
            </div>
        </span>
    @endif
</label>