@php
    $record = $meter?->latestRecord;
    $previous = $record ? $record->prev_reading : $meter?->opening_reading;
    $previousDate = $record ? $record->prev_reading_date : $meter?->opening_reading_date;
    $calculated = $record?->status === 'confirmed' && $record->curr_reading !== null && $record->amount !== null;
@endphp

<div class="col-span-full">
    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Water Meter</dt>
    @if($meter)
        <dd class="mt-2 grid grid-cols-1 gap-x-8 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <span class="block font-medium text-slate-900">Installed · {{ ucfirst($meter->status) }}</span>
                <span class="mt-1 block break-all text-xs text-slate-500">Meter no: {{ $meter->meter_number ?: 'Not specified' }}</span>
            </div>
            <div class="tabular-nums">
                <span class="text-slate-500">Previous:</span> <span class="text-slate-800">{{ $previous !== null ? number_format((float) $previous, 3).' m³' : 'Not recorded' }}</span>
                @if($previousDate)<span class="mt-1 block text-xs text-slate-500">{{ $previousDate->format('d/m/Y') }}{{ !$record ? ' · Opening reading' : '' }}</span>@endif
            </div>
            <div class="tabular-nums">
                <span class="text-slate-500">Current:</span> <span class="text-slate-800">{{ $record?->curr_reading !== null ? number_format((float) $record->curr_reading, 3).' m³' : 'Not recorded' }}</span>
                @if($record?->curr_reading_date)<span class="mt-1 block text-xs text-slate-500">{{ $record->curr_reading_date->format('d/m/Y') }}</span>@endif
            </div>
            <div class="tabular-nums">
                <span class="text-slate-500">Charge:</span> <span class="font-semibold text-indigo-600">{{ $calculated ? 'RM '.number_format($record->amount / 100, 2) : 'Not calculated' }}</span>
                @if($record?->period)<span class="mt-1 block text-xs text-slate-500">Period: {{ $record->period->format('d/m/Y') }}</span>@endif
            </div>
        </dd>
    @else
        <dd class="mt-1 text-sm font-medium text-slate-900">Not installed</dd>
    @endif
</div>
