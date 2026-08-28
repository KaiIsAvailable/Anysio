@props([
    'name' => 'confirmation-modal',
    'title' => 'Are you sure?',
    'maxWidth' => 'sm:max-w-md'
])

<template x-teleport="body">
    <div x-data="{ show: false, detail: null, shake: false }" 
         @open-{{ $name }}.window="show = true; detail = $event.detail" 
         @close-{{ $name }}.window="show = false"
         x-show="show" 
         x-cloak
         class="fixed inset-0 z-[120] overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center">
        
        {{-- Backdrop Layer --}}
        <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm" 
             x-show="show"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
        </div>

        {{-- Modal Box: Clicking outside this box triggers shake via wrapper/click handling --}}
        <div class="relative bg-white rounded-2xl shadow-2xl overflow-hidden transition-all duration-200 z-10 {{ $maxWidth }} sm:w-full"
             x-show="show"
             @click.outside="shake = true; setTimeout(() => shake = false, 400)"
             :class="{ 'animate-shake border-2 border-indigo-500': shake }"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
            
            {{-- Header --}}
            <div class="px-6 pt-6 pb-4 bg-white border-b border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">{{ $title }}</h3>
                    </div>
                    <button type="button" @click="show = false" class="text-gray-400 hover:text-gray-600 text-2xl font-light">&times;</button>
                </div>
            </div>

            {{-- Body Slot --}}
            <div class="px-6 py-5 bg-white text-sm text-slate-600">
                {{ $slot }}
            </div>
        </div>
    </div>
</template>