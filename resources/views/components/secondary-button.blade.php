@props(['loading' => false])

@php
    // 判断传入的是否是 Alpine.js 的变量名字符串（比如 'isLoading' 或 'openUpload'）
    // 如果是纯布尔值或普通字符串，交由 Blade 处理
    $isAlpine = is_string($loading) && !in_array($loading, ['true', 'false', '1', '0']);
@endphp

<button {{ $attributes->merge([
    'type' => 'button', 
    'class' => 'inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed'
]) }} 
{{-- 兼容 Alpine 和原生 Blade 的 disabled 绑定 --}}
@if($isAlpine)
    :disabled="{{ $loading }}"
    :class="{ 'opacity-50 cursor-not-allowed': {{ $loading }} }"
@else
    {{ $loading ? 'disabled' : '' }}
@endif
>
    
    {{-- Loading 状态的 SVG 旋转动画 --}}
    @if($isAlpine)
        <svg x-show="{{ $loading }}" 
             class="animate-spin -ml-1 mr-2 h-3 w-3 text-current" 
             fill="none" 
             viewBox="0 0 24 24"
             x-cloak>
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    @else
        @if($loading)
            <svg class="animate-spin -ml-1 mr-2 h-3 w-3 text-current" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @endif
    @endif

    {{-- 按钮原本的文字/内容 --}}
    <span>{{ $slot }}</span>
</button>