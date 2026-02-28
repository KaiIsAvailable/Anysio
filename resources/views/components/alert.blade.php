@props(['type' => 'success', 'message' => '', 'details' => null])

@php
    $colors = [
        'success' => 'bg-emerald-50 border-emerald-400 text-emerald-800',
        'error'   => 'bg-red-50 border-red-400 text-red-800',
        'info'    => 'bg-blue-50 border-blue-400 text-blue-800',
        'warning' => 'bg-amber-50 border-amber-400 text-amber-800',
    ][ $type ] ?? 'bg-gray-50 border-gray-400 text-gray-800';

    $icons = [
        'success' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'error'   => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
        'info'    => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    ][ $type ] ?? '';
@endphp

<div x-data="{ show: true }" 
     x-show="show" 
     x-init="setTimeout(() => show = false, 5000)"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 transform translate-y-[-20px]"
     x-transition:enter-end="opacity-100 transform translate-y-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="{{ $colors }} border-l-4 p-4 rounded-r-lg shadow-md mb-6 flex items-start space-x-3">
    
    @if($icons)
        <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icons }}"></path>
        </svg>
    @endif

    <div class="flex-1">
        <p class="font-bold text-sm leading-tight">{{ $message }}</p>
        @if($details)
            <div class="mt-2 text-xs opacity-90 font-medium">
                {{ $details }}
            </div>
        @endif
    </div>

    <button @click="show = false" class="text-current opacity-50 hover:opacity-100 transition-opacity">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
    </button>
</div>