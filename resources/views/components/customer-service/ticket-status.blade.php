@props(['status' => ''])
<span {{ $attributes->class(['shrink-0 rounded-full px-3 py-1 text-xs font-semibold', get_status_badge($status)]) }}>{{ strtoupper($status) }}</span>
