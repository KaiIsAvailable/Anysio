@props([
    'id' => 'modalOpen',
    'title' => 'Are you sure?',
    'maxWidth' => 'sm:max-w-md',
    'zIndex' => 'z-[120]'
])

@php
    $jsVar = Str::camel($id);
@endphp

<template x-teleport="body">
    <div x-data="{ {{ $jsVar }}: false, shake: false }" 
         @open-{{ $id }}.window="{{ $jsVar }} = true"
         @close-{{ $id }}.window="{{ $jsVar }} = false"
         x-show="{{ $jsVar }}" 
         x-cloak
         class="fixed inset-0 {{ $zIndex }} flex items-center justify-center p-4 sm:p-6">
        
        {{-- Blur Overlay Component with Click-to-Shake --}}
        <div class="absolute inset-0">
             <x-ui.blur-overlay 
                :show="$jsVar" 
                onClose="shake = true; setTimeout(() => shake = false, 400)" 
             />
        </div>

        {{-- Modal Box --}}
        <div class="relative bg-white rounded-[2rem] shadow-2xl w-full {{ $maxWidth }} overflow-hidden transition-all duration-200 z-10"
             x-show="{{ $jsVar }}"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             :class="{ 'animate-shake border-2 border-indigo-500': shake }"
             @click.stop>
             
            {{-- Header --}}
            <div class="px-6 pt-6 pb-4 bg-gray-50/50 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-black text-slate-800">{{ $title }}</h3>
                <button type="button" @click="{{ $jsVar }} = false" class="text-gray-400 hover:text-rose-500 transition-colors text-2xl font-light">&times;</button>
            </div>

            {{-- Dynamic Body Slot --}}
            <div class="bg-white">
                {{ $slot }}
            </div>
        </div>
    </div>
</template>