@props([
    'show' => 'false',    // 绑定的显示变量
    'onClose' => '',      // 点击遮罩时的操作
    'zIndex' => 'z-[10]' // 层级，默认 100
])

<div x-show="{{ $show }}" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 {{ $zIndex }} bg-slate-900/60 backdrop-blur-sm"
     @if($onClose) @click="{{ $onClose }}" @endif
     x-cloak>
</div>