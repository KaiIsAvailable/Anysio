@props(['counts' => [], 'inline' => false])

@php
    $visibleCounts = array_filter($counts, fn ($count) => $count > 0);
@endphp

@if($visibleCounts)
    <div @class([
        'text-xs',
        'mt-2 space-y-1 border-t border-slate-200 pt-2' => !$inline,
        'flex flex-wrap items-center gap-x-4 gap-y-1 border-l border-slate-200 pl-3' => $inline,
    ])>
        @foreach($visibleCounts as $status => $count)
            <div class="font-medium {{ get_status_color($status) }}">{{ $status }} - {{ $count }}</div>
        @endforeach
    </div>
@endif
