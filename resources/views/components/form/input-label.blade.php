@props([
    'value' => null,
    'required' => false,
    'info' => null,
    'floatingInfo' => false,
])

<label {{ $attributes->merge(['class' => 'block uppercase font-medium text-sm text-gray-700 flex items-center gap-1.5']) }}>
    {{ $value ?? $slot }}

    @if($required)
        <span class="text-red-500 ml-0.5">*</span>
    @endif

    @if($info)
        <span x-data="{ open: false, floating: @js((bool) $floatingInfo), tooltipStyle: { visibility: 'hidden' },
            showInfo() {
                this.open = true;
                if (!this.floating) return;
                this.$nextTick(() => {
                    const trigger = this.$el.getBoundingClientRect();
                    const popup = this.$refs.infoPopup;
                    const left = Math.max(8, Math.min(trigger.right - popup.offsetWidth, window.innerWidth - popup.offsetWidth - 8));
                    const below = trigger.bottom + 8;
                    const top = below + popup.offsetHeight <= window.innerHeight - 8 ? below : Math.max(8, trigger.top - popup.offsetHeight - 8);
                    this.tooltipStyle = { left: left + 'px', top: top + 'px' };
                });
            } }" x-id="['field-info']" x-cloak
            tabindex="0" role="button" aria-label="More information" :aria-expanded="open" :aria-describedby="$id('field-info')"
            @mouseenter="showInfo()" @mouseleave="if (!$el.matches(':focus')) open = false" @focus="showInfo()" @blur="open = false"
            @click.prevent.stop="showInfo()" @click.outside="open = false" @keydown.escape.prevent.stop="open = false"
            @keydown.enter.prevent.stop="showInfo()" @keydown.space.prevent.stop="showInfo()"
            @if($floatingInfo) @scroll.window.capture="open = false" @resize.window="open = false" @endif
            class="relative inline-flex items-center text-gray-400 hover:text-gray-600 cursor-help focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-full">
            <!-- Info Icon SVG -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
            </svg>

            <!-- Tooltip Popup -->
            @if($floatingInfo)<template x-teleport="body">@endif
            <div x-ref="infoPopup" x-cloak x-show="open" :id="$id('field-info')" role="tooltip" :style="floating ? tooltipStyle : null"
                @class(['p-2 bg-gray-900 text-white text-xs rounded-md shadow-xl pointer-events-none normal-case font-normal text-left [&>b]:font-bold',
                    $floatingInfo ? 'fixed z-[160] w-80 max-w-[calc(100vw-1rem)]' : 'absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-56 z-50'])>
                {!! $info !!}
            </div>
            @if($floatingInfo)</template>@endif
        </span>
    @endif
</label>
