{{-- resources/views/components/notification-banner.blade.php --}}
@props([
    'type' => 'info', 
    'message' => '', 
    'actionLabel' => null, 
    'actionUrl' => '#',
    'secondaryActionLabel' => null,
    'secondaryActionUrl' => '#',
    'show' => true
])

@if($show)
<div {{ $attributes->merge(['class' => 'mb-6']) }}>
    <div class="rounded-xl border p-4 shadow-sm transition-all duration-300 {{ 
        match($type) {
            'warning' => 'bg-amber-50 border-amber-200 text-amber-800',
            'success' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
            'danger' => 'bg-rose-50 border-rose-200 text-rose-800',
            default => 'bg-indigo-50 border-indigo-200 text-indigo-800',
        }
    }}">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    @if($type === 'warning')
                        <svg class="h-5 w-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    @elseif($type === 'success')
                        <svg class="h-5 w-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @else
                        <svg class="h-5 w-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @endif
                </div>

                {{-- 支持 Slot 或者普通 Message --}}
                <div class="text-sm font-medium leading-relaxed">
                    {{ $slot->isEmpty() ? $message : $slot }}
                </div>
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto">
                @if($secondaryActionLabel)
                    <a href="{{ $secondaryActionUrl }}" class="flex-1 sm:flex-none text-center px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-gray-200 hover:bg-gray-50 transition-colors text-slate-700">
                        {{ $secondaryActionLabel }}
                    </a>
                @endif

                @if($actionLabel)
                    <a href="{{ $actionUrl }}" class="flex-1 sm:flex-none text-center px-3 py-1.5 text-xs font-semibold rounded-lg text-white transition-colors {{
                        match($type) {
                            'warning' => 'bg-amber-600 hover:bg-amber-700',
                            'danger' => 'bg-rose-600 hover:bg-rose-700',
                            'success' => 'bg-emerald-600 hover:bg-emerald-700',
                            default => 'bg-indigo-600 hover:bg-indigo-700',
                        }
                    }}">
                        {{ $actionLabel }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endif