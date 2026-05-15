@props(['status'])

{{-- 逻辑：优先使用传入的 status，如果没有，则尝试读取 session('success') --}}
@php
    $message = $status ?? session('success');
    $isError = session()->has('error') || $errors->any();
    if (!$message && $errors->any()) {
        $message = $errors->first();
        $isError = true;
    }
@endphp

@if ($message)
    <div x-data="{ show: true }" 
        x-show="show" 
        x-init="setTimeout(() => show = false, 5000)"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-x-10"
        x-transition:enter-end="opacity-100 transform translate-x-0"
        x-transition:leave="transition ease-in duration-500"
        x-transition:leave-start="opacity-100 transform translate-x-0"
        x-transition:leave-end="opacity-0 transform translate-x-10"
        {{ $attributes->merge(['class' => 'fixed top-24 right-5 z-[103] flex items-center w-full max-w-xs p-4 bg-white border-l-4 rounded-lg shadow-2xl ' . ($isError ? 'border-red-500' : 'border-green-500')]) }}
        role="alert">
        
        <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-lg {{ $isError ? 'text-red-500 bg-red-100' : 'text-green-500 bg-green-100' }}">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                @if($isError)
                    {{-- X Icon for Error --}}
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                @else
                    {{-- Check Icon for Success --}}
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                @endif
            </svg>
        </div>

        <div class="ml-3 text-sm font-bold text-gray-800">
            {{ $message }}
        </div>

        <button @click="show = false" class="ml-auto text-gray-400 hover:text-gray-900 rounded-lg p-1.5 inline-flex items-center justify-center h-8 w-8 hover:bg-gray-100 transition-colors">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
@endif