@props(['loading' => false])

@php
    // 判断传入的是否是 Alpine.js 的变量名字符串（比如 'loading' 或 'openUpload'）
    // 如果是纯布尔值或普通字符串，交由 Blade 处理
    $isAlpine = is_string($loading) && !in_array($loading, ['true', 'false', '1', '0']);
@endphp

<button {{ $attributes->merge([
    'type' => 'submit', 
    'class' => 'inline-flex items-center justify-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed'
]) }} 
{{-- 兼容 Alpine 和原生 Blade 的 disabled 绑定 --}}
@if($isAlpine)
    :disabled="{{ $loading }}"
@else
    {{ $loading ? 'disabled' : '' }}
@endif
>
    
    {{-- Loading 状态的 SVG 旋转动画 --}}
    @if($isAlpine)
        <svg x-show="{{ $loading }}" 
             class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" 
             fill="none" 
             viewBox="0 0 24 24"
             x-cloak> {{-- 推荐用 x-cloak 配合 CSS 防止 Alpine 加载前闪烁 --}}
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    @else
        @if($loading)
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @endif
    @endif

    {{-- 按钮原本的文字/内容 --}}
    <span>{{ $slot }}</span>
</button>