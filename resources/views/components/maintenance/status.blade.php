@props(['status'])
<span {{ $attributes->class(['inline-flex rounded-full px-2 py-1 text-xs font-semibold', get_status_badge($status)]) }}>{{ strtoupper($status) }}</span>
