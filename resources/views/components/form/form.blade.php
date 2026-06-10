@props(['action', 'method' => 'POST'])

@php
    $realMethod = in_array(strtoupper($method), ['GET', 'POST']) ? $method : 'POST';
    $spoofMethod = !in_array(strtoupper($method), ['GET', 'POST']) ? strtoupper($method) : null;
@endphp

<form 
    {{ $attributes->merge([
        'action' => $action,
        'method' => $realMethod
    ]) }}
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
                const nextEl = inputs[index + 1];
                nextEl.focus();

                // 核心逻辑：如果是输入框，定位到末尾
                if (nextEl.tagName === 'INPUT' || nextEl.tagName === 'TEXTAREA') {
                    const len = nextEl.value.length;
                    // 使用 setTimeout 确保在 focus 之后执行，兼容性更好
                    setTimeout(() => {
                        nextEl.setSelectionRange(len, len);
                    }, 0);
                }
            }
        }
    }" 
    @submit="loading = true"
    
    {{-- 💡 页面加载时：只聚焦，不乱弹 --}}
    x-init="$nextTick(() => {
        const first = Array.from($el.querySelectorAll('input, select, textarea'))
            .find(el => 
                el.type !== 'hidden' && 
                !el.disabled && 
                el.offsetWidth > 0 && 
                el.offsetHeight > 0
            );

        if (first) {
            first.focus();
            // 确保光标在开头
            if (typeof first.setSelectionRange === 'function') {
                const len = first.value.length; // 获取当前输入框内文本的长度
                first.setSelectionRange(len, len); // 定位到最后
            }
        }
    })"
    
    @keydown.enter="handleEnter($event)"
>
    @csrf
    @if($spoofMethod) @method($spoofMethod) @endif
    {{ $slot }}
</form>