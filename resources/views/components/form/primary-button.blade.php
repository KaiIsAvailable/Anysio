@props(['loading' => false, 'size' => 'md', 'disabledWhen' => null, 'variant' => 'primary'])

@php
    // 判断传入的是否是 Alpine.js 的变量名字符串（比如 'loading' 或 'openUpload'）
    // 如果是纯布尔值或普通字符串，交由 Blade 处理
    $isAlpine = is_string($loading) && !in_array($loading, ['true', 'false', '1', '0']);

    $sizeClasses = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-xs',
    ][$size] ?? 'px-4 py-2 text-xs';
    $colorClasses = $variant === 'danger'
        ? 'bg-red-600 hover:bg-red-700 focus:bg-red-700 active:bg-red-800 focus:ring-red-500'
        : 'bg-indigo-600 hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-800 focus:ring-indigo-500';
@endphp

<button
    @if($isAlpine && $loading === 'loading')
    x-data="{
        loading: false,
        isSubmitter: false,
        selectSubmitter(event) {
            if (!this.$el.form || event.target !== this.$el.form) return;
            const submitter = event.submitter || this.$el.form.querySelector('button[type=submit], button:not([type]), input[type=submit]');
            this.isSubmitter = submitter === this.$el;
        },
        syncFormLoading(event) {
            if (!this.$el.form || event.target !== this.$el.form) return;
            this.loading = !!event.detail && this.isSubmitter;
        },
        startNativeSubmit(event) {
            if (this.$el.form && event.target === this.$el.form && !event.defaultPrevented) {
                this.loading = this.isSubmitter;
            }
        }
    }"
    @submit.window.capture="selectSubmitter($event)"
    @submit.window="startNativeSubmit($event)"
    @form-loading.window.capture="syncFormLoading($event)"
    @endif
    {{ $attributes->merge([
    'type' => 'submit',
    'class' => "inline-flex items-center justify-center {$sizeClasses} {$colorClasses} border border-transparent rounded-md font-semibold text-white uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
]) }} 
{{-- 兼容 Alpine 和原生 Blade 的 disabled 绑定 --}}
@if($isAlpine)
    :disabled="{{ $loading }}{{ $disabledWhen ? ' || ('.$disabledWhen.')' : '' }}"
    :aria-busy="{{ $loading }}"
    {{-- Only add the pageshow reset if it's a direct variable name, not a comparison expression containing '===' --}}
    @if(!str_contains($loading, '==='))
        @pageshow.window="{{ $loading }} = false"
    @else
        @pageshow.window="subAction = ''"
    @endif
@else
    @if($disabledWhen) :disabled="{{ $disabledWhen }}" @endif
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
    <span class="flex items-center justify-center inline-flex">
        {{ $slot }}
    </span>
</button>
