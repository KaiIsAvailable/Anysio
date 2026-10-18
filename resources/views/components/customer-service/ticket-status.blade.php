@props(['status' => ''])
<span {{ $attributes->class(['shrink-0 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700']) }}>{{ strtoupper($status) }}</span>
