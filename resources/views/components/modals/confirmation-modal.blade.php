@props([
    'id' => 'modalOpen',
    'title' => 'Are you sure?',
    'action' => '#',
    'method' => 'POST',
    'message' => '',
    'label' => '',
    'inputName' => 'reason',
    'inputType' => 'textarea',
    'placeholder' => '',
    'required' => true,
    'maxWidth' => 'sm:max-w-md'
])

<template x-teleport="body">
    <div x-data="{ inputValue: '', shake: false }" 
         @close-{{ $id }}.window="{{ $id }} = false"
         x-show="{{ $id }}" 
         x-cloak
         class="fixed inset-0 z-[120] overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center">
        
        {{-- Backdrop Layer --}}
        <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm" 
             x-show="{{ $id }}"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
        </div>

        {{-- Modal Box --}}
        <div class="relative bg-white rounded-2xl shadow-2xl overflow-hidden transition-all duration-200 z-10 {{ $maxWidth }} sm:w-full"
             x-show="{{ $id }}"
             @click.outside="shake = true; setTimeout(() => shake = false, 400)"
             :class="{ 'animate-shake border-2 border-indigo-500': shake }"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
             
            {{-- Header --}}
            <div class="px-6 pt-6 pb-4 bg-white border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-900">{{ $title }}</h3>
                <button type="button" @click="{{ $id }} = false" class="text-gray-400 hover:text-gray-600 text-2xl font-light">&times;</button>
            </div>

            {{-- Form & Body --}}
            <form :action="actionUrl || '{{ $action }}'" 
                  method="POST" 
                  @submit="if({{ $required ? 'true' : 'false' }} && inputType === 'textarea' && !inputValue.trim()) { $event.preventDefault(); alert('Please provide a value.'); }">
                @csrf
                @if(strtoupper($method) !== 'POST')
                    @method($method)
                @endif

                <div class="px-6 py-5 bg-white text-sm text-slate-600 space-y-4">
                    
                    {{-- Dynamic Message (supports dynamic data or static text) --}}
                    @if($message)
                        <p class="text-sm text-slate-600 leading-relaxed">
                            {!! $message !!}
                        </p>
                    @endif

                    {{-- Dynamic Field (Textarea / Input / Custom Slot) --}}
                    @if($label)
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                {{ $label }} @if($required)<span class="text-rose-500">*</span>@endif
                            </label>

                            @if($inputType === 'textarea')
                                <textarea name="{{ $inputName }}" 
                                          x-model="inputValue" 
                                          rows="3" 
                                          {{ $required ? 'required' : '' }}
                                          placeholder="{{ $placeholder }}"
                                          class="w-full text-sm rounded-lg border-gray-300 focus:border-rose-500 focus:ring-rose-500 shadow-sm"></textarea>
                            @elseif($inputType === 'input')
                                <input type="text" 
                                       name="{{ $inputName }}" 
                                       x-model="inputValue" 
                                       {{ $required ? 'required' : '' }}
                                       placeholder="{{ $placeholder }}"
                                       class="w-full text-sm rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            @endif
                        </div>
                    @endif

                    {{-- Additional custom slot injection if needed --}}
                    {{ $slot ?? '' }}
                </div>

                {{-- Footer Buttons --}}
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" 
                            @click="{{ $id }} = false" 
                            class="px-4 py-2 text-xs font-semibold text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-lg transition-all">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 rounded-lg transition-all shadow-sm">
                        Confirm Action
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>