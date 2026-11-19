@props(['maintenance'])
@php($startedAt = $maintenance->events->firstWhere('action', 'assign')?->created_at ?? $maintenance->assigned_at)
<dl class="my-6 grid grid-cols-1 gap-4 rounded-lg bg-gray-50 p-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
    @foreach(['Created' => $maintenance->created_at, 'Started' => $startedAt, 'Latest Assignment' => $maintenance->assigned_at, 'Completed' => $maintenance->completed_at] as $label => $date)
        <div>
            <dt class="text-xs text-gray-500">{{ $label }}</dt>
            <dd class="mt-1 font-medium text-slate-900">{{ $date?->timezone('Asia/Kuala_Lumpur')->format('d/m/Y, H:i') ?? 'Not recorded' }}</dd>
        </div>
    @endforeach
</dl>
<ol class="divide-y divide-gray-100">
    @forelse($maintenance->events as $event)
        <li class="py-4 first:pt-0 last:pb-0">
            <div class="flex flex-col justify-between gap-1 sm:flex-row sm:gap-4">
                <p class="text-sm font-semibold text-slate-900">{{ match ($event->action) { 'create' => 'Maintenance created', 'edit' => 'Maintenance updated', 'assign' => 'Staff assigned', 'complete' => 'Maintenance completed', default => ucfirst($event->action) } }}</p>
                <p class="shrink-0 text-xs text-gray-500">{{ $event->created_at?->timezone('Asia/Kuala_Lumpur')->format('d/m/Y, H:i:s') }}</p>
            </div>
            <p class="mt-1 break-words text-xs text-gray-500">{{ $event->actor?->name ?? 'Unknown user' }}</p>
            @foreach($event->changes ?? [] as $field => $change)
                @continue($field === 'assigned_staff_id')
                <p class="mt-2 whitespace-pre-wrap break-words text-sm text-gray-600"><span class="font-medium">{{ match ($field) { 'contractor_name' => 'Assigned staff', 'contractor_phone' => 'Phone', default => ucfirst(str_replace('_', ' ', $field)) } }}:</span> {{ $change['from'] ?? 'None' }} &rarr; {{ $change['to'] ?? 'None' }}</p>
            @endforeach
        </li>
    @empty
        <li class="text-sm text-gray-500">No activity yet.</li>
    @endforelse
</ol>
