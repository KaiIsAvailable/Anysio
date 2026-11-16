@extends('adminSide.maintenance.frame')
@section('content')
<a href="{{ route('admin.maintenance.work-orders.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">&larr; Back to work orders</a>
<div class="my-6 flex flex-wrap items-center justify-between gap-4">
    <div><h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ $maintenance->title }}</h1><p class="mt-2 break-all text-sm text-gray-500">{{ $maintenance->maintenance_number }}</p></div>
    <x-maintenance.status :status="$maintenance->status" />
</div>
<div class="mb-6 grid gap-6 md:grid-cols-2">
    <x-maintenance.section title="Problem and location">
        <p class="whitespace-pre-wrap">{{ $maintenance->desc }}</p>
        <dl class="space-y-2"><div><dt class="font-medium text-gray-500">Property / Unit / Room</dt><dd>{{ $maintenance->location_snapshot }}</dd></div><div><dt class="font-medium text-gray-500">Location details</dt><dd>{{ $maintenance->location_detail }}</dd></div></dl>
        <a target="_top" href="{{ route('admin.maintenance.index', ['ticket' => $maintenance->ticket_id]) }}" class="inline-block font-medium text-indigo-600 hover:text-indigo-900">Open source conversation</a>
    </x-maintenance.section>
    <x-maintenance.section title="External Contractor">
        <p class="font-medium text-slate-900">{{ $maintenance->contractor_name ?: 'Unassigned' }}</p>
        @if($maintenance->contractor_phone)<p>{{ $maintenance->contractor_phone }}</p>@endif
        <div class="flex flex-wrap gap-2">
            @if(in_array($maintenance->status, ['pending','maintaining']))
                @can('maintenance.assign')<x-form.secondary-button x-data x-on:click="$dispatch('open-modal', 'assign-maintenance')">{{ $maintenance->status === 'pending' ? 'Assign Contractor' : 'Change Contractor' }}</x-form.secondary-button>@endcan
            @endif
            @if($maintenance->status === 'maintaining')
                @can('maintenance.complete')<x-form.primary-button type="button" x-data x-on:click="$dispatch('open-modal', 'complete-maintenance')">Complete</x-form.primary-button>@endcan
            @endif
        </div>
    </x-maintenance.section>
</div>
@foreach(['before' => 'Before evidence', 'completion' => 'Completion evidence'] as $purpose => $label)
    @if($maintenance->attachments->where('purpose', $purpose)->isNotEmpty())
        <x-maintenance.section :title="$label" class="mb-6"><div class="grid gap-4 sm:grid-cols-3">@foreach($maintenance->attachments->where('purpose', $purpose) as $file)<x-maintenance.evidence :file="$file" />@endforeach</div></x-maintenance.section>
    @endif
@endforeach
<x-maintenance.section title="Activity">
    <ol class="divide-y divide-gray-100">
        @forelse($maintenance->events as $event)
            <li class="py-3 first:pt-0"><p class="font-medium text-slate-900">{{ ucfirst($event->action) }} <span class="font-normal text-gray-500">&middot; {{ $event->created_at?->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i:s') }}</span></p><p class="break-all text-xs text-gray-500">User {{ $event->actor_id }}</p>
                @foreach($event->changes as $field => $change)<p class="mt-1">{{ ucfirst(str_replace('_', ' ', $field)) }}: {{ $change['from'] ?? 'None' }} &rarr; {{ $change['to'] ?? 'None' }}</p>@endforeach
            </li>
        @empty <li class="text-gray-500">No activity yet.</li> @endforelse
    </ol>
</x-maintenance.section>
<x-maintenance.action-modals :maintenance="$maintenance" />
@endsection
