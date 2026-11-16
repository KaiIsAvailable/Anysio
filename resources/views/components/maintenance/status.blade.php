@props(['status'])
<span {{ $attributes->class(['inline-flex rounded-full border px-2 py-1 text-xs font-semibold', 'bg-amber-50 text-amber-800' => $status === 'pending', 'bg-blue-50 text-blue-800' => $status === 'maintaining', 'bg-emerald-50 text-emerald-800' => $status === 'completed']) }}>{{ strtoupper($status) }}</span>
