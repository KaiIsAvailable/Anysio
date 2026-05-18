@props(['action', 'method' => 'POST'])

@php
    $realMethod = in_array(strtoupper($method), ['GET', 'POST']) ? $method : 'POST';
    $spoofMethod = !in_array(strtoupper($method), ['GET', 'POST']) ? strtoupper($method) : null;
@endphp

<form 
    action="{{ $action }}" 
    method="{{ $realMethod }}" 
    x-data="{ 
        loading: false,
        
        getFocusableElements: function() {
            const all = Array.from($el.querySelectorAll('input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled])'));
            return all.filter(el => el.offsetWidth > 0 || el.offsetHeight > 0);
        },
        
        handleEnter: function(e) {
            const el = e.target;
            const inputs = this.getFocusableElements();
            const index = inputs.indexOf(el);

            // 1. 处理 SELECT 的原生弹窗行为
            if (el.tagName === 'SELECT') {
                if (!el.dataset.isOpened) {
                    el.dataset.isOpened = 'true';
                    return; // 允许浏览器打开下拉框
                } else {
                    delete el.dataset.isOpened; // 下拉框已选择，继续后续逻辑
                }
            }

            // 2. 核心修复：如果是最后一个元素，直接手动触发提交
            if (index === inputs.length - 1) {
                this.loading = true;
                // 确保阻止默认的回车行为（防止某些浏览器乱跳），然后主动提交表单
                e.preventDefault();
                $el.submit(); 
                return;
            }

            // 3. 不是最后一个，执行跳转
            if (index > -1) {
                e.preventDefault();
                inputs[index + 1].focus();
            }
        }
    }" 
    @submit="loading = true"
    
    {{-- 💡 页面加载时：只聚焦，不乱弹 --}}
    x-init="$nextTick(() => {
        const first = $el.querySelector('input:not([type=hidden]):not([disabled]):not([type=submit]), select:not([disabled]), textarea:not([disabled])');
        if (first) first.focus();
    })"
    
    @keydown.enter="handleEnter($event)"
    {{ $attributes }}
>
    @csrf
    @if($spoofMethod) @method($spoofMethod) @endif
    {{ $slot }}
</form>