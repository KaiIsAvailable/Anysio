@extends('adminSide.maintenance.frame')
@section('content')
@php
    $firstAssignedAt = $maintenance->events->firstWhere('action', 'assign')?->created_at ?? $maintenance->assigned_at;
    $statusDate = match ($maintenance->status) {
        'pending' => $maintenance->created_at,
        'maintaining' => $firstAssignedAt,
        'completed' => $maintenance->completed_at,
        default => null,
    };
    $elapsed = $statusDate && $statusDate->lte(now()) ? $statusDate->diffForHumans(now(), true) : null;
    $statusSummary = match ($maintenance->status) {
        'pending' => 'Awaiting staff assignment',
        'maintaining' => 'Staff assigned · Maintenance in progress',
        'completed' => 'Maintenance completed · Work order closed',
        default => 'Maintenance work order',
    };
@endphp
<div class="mb-8 flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
    <div>
        <nav class="mb-2 flex" aria-label="Breadcrumb">
            <a href="{{ route('admin.maintenance.work-orders.index') }}" class="flex items-center text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-700">
                <svg class="mr-1 h-4 w-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                Back to Maintenance List
            </a>
        </nav>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Maintenance Details</h1>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        @if(in_array($maintenance->status, ['pending', 'maintaining']))
            @canany(['super-admin', 'maintenance.edit'])
                <a href="{{ route('admin.maintenance.work-orders.edit', $maintenance) }}" data-edit-maintenance class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition ease-in-out duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <svg class="mr-2 h-4 w-4 text-gray-500" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    Edit Maintenance
                </a>
            @endcanany
            @canany(['super-admin', 'maintenance.assign'])
                @if($maintenance->status === 'pending')
                    <x-form.primary-button type="button" x-data x-on:click="$dispatch('open-modal', 'assign-maintenance')">Assign Contractor</x-form.primary-button>
                @else
                    <x-form.secondary-button type="button" x-data x-on:click="$dispatch('open-modal', 'assign-maintenance')">Change Contractor</x-form.secondary-button>
                @endif
            @endcanany
        @endif
        @if($maintenance->status === 'maintaining')
            @canany(['super-admin', 'maintenance.complete'])
                <x-form.primary-button type="button" x-data x-on:click="$dispatch('open-modal', 'complete-maintenance')">Complete</x-form.primary-button>
            @endcanany
        @endif
    </div>
</div>

<div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-lg">
    <div class="border-b border-gray-100 px-6 py-6 sm:px-8">
        <div class="min-w-0">
            <div class="mb-3 flex flex-wrap items-center gap-3">
                <h2 class="min-w-0 break-words text-2xl font-bold tracking-tight text-slate-900">{{ $maintenance->maintenance_number }} {{ $maintenance->title }}(QTY:{{ $maintenance->qty ?? 1 }}) - @if($maintenance->ticket_id)<a href="{{ route('admin.maintenance.index', ['ticket' => $maintenance->ticket_id]) }}" class="rounded text-indigo-600 hover:text-indigo-900 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500">service request</a>@elseif($maintenance->source_type === 'refund')<a href="{{ route('admin.leases.show', $maintenance->lease_id) }}" class="text-indigo-600 hover:text-indigo-900 hover:underline">checkout refund</a>@else{{ $maintenance->source_type === 'manual' ? 'manual' : 'service request' }}@endif</h2>
                <x-maintenance.status :status="$maintenance->status" class="shrink-0" />
            </div>
            <p class="text-sm font-medium text-slate-700">{{ $statusSummary }}</p>
        </div>
        <dl class="mt-6 grid grid-cols-1 gap-5 rounded-lg border border-gray-100 bg-gray-50 p-4 md:grid-cols-3 md:gap-6">
            <div class="min-w-0">
                <dt class="text-xs font-medium text-gray-500">{{ match ($maintenance->status) { 'pending' => 'Time Waiting', 'maintaining' => 'Time in Progress', 'completed' => 'Completed On', default => 'Status Date' } }}</dt>
                <dd class="mt-1 text-lg font-bold text-slate-900">
                    @if($maintenance->status === 'completed')
                        {{ $statusDate?->timezone('Asia/Kuala_Lumpur')->format('d/m/Y, H:i') ?? 'Not recorded' }}
                    @else
                        {{ $elapsed ? ucfirst($elapsed) : 'Not recorded' }}
                    @endif
                </dd>
                @if($statusDate && $maintenance->status !== 'completed')
                    <dd class="mt-1 text-xs text-gray-500">{{ $maintenance->status === 'pending' ? 'Created' : 'First assigned' }} {{ $statusDate->timezone('Asia/Kuala_Lumpur')->format('d/m/Y, H:i') }}</dd>
                @endif
            </div>
            <div class="min-w-0">
                <dt class="text-xs font-medium text-gray-500">Property / Unit / Room</dt>
                <dd class="mt-1 break-words text-sm font-semibold text-slate-900">{{ $maintenance->leasable_name }}{{ $maintenance->leasable_type_label ? ' - '.strtolower($maintenance->leasable_type_label) : '' }}</dd>
                @if($maintenance->location_detail)
                    <dd class="mt-1 break-words text-sm text-gray-600"><span class="font-medium">Location:</span> {{ $maintenance->location_detail }}</dd>
                @endif
            </div>
            <div class="min-w-0">
                <dt class="text-xs font-medium text-gray-500">Assigned Staff</dt>
                <dd class="mt-1 break-words text-sm font-semibold text-slate-900">{{ $maintenance->contractor_name ?: 'Unassigned' }}</dd>
                @if($staffPhone)
                    <dd class="mt-1 break-words text-sm text-gray-600">{{ $staffPhone }}</dd>
                @endif
            </div>
        </dl>
    </div>

    <div class="space-y-8 px-6 py-8 sm:px-8 sm:py-10">
        <section aria-labelledby="maintenance-details-heading">
            <h3 id="maintenance-details-heading" class="mb-4 border-l-4 border-indigo-500 pl-3 text-xs font-semibold uppercase tracking-widest text-gray-400">Reported Issue</h3>
            <dl class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div class="min-w-0 pl-4 md:col-span-2 lg:col-span-3">
                    <dt class="text-xs font-medium text-gray-400">Problem Description</dt>
                    <dd class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-900">{{ $maintenance->desc }}</dd>
                </div>
            </dl>
        </section>

        @foreach(['before' => 'Before Evidence', 'completion' => 'Completion Evidence'] as $purpose => $label)
            @if($maintenance->attachments->where('purpose', $purpose)->isNotEmpty())
                <section class="border-t border-gray-100 pt-8" aria-labelledby="maintenance-{{ $purpose }}-heading">
                    <h3 id="maintenance-{{ $purpose }}-heading" class="mb-4 border-l-4 border-indigo-500 pl-3 text-xs font-semibold uppercase tracking-widest text-gray-400">{{ $label }}</h3>
                    <div class="grid grid-cols-3 gap-3 sm:gap-4">
                        @foreach($maintenance->attachments->where('purpose', $purpose) as $file)
                            <x-maintenance.evidence :file="$file" />
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach

        <details class="border-t border-gray-100 pt-6">
            <summary class="cursor-pointer rounded text-sm font-semibold text-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500">Dates &amp; Activity <span class="font-normal text-gray-500">({{ $maintenance->events->count() }} updates)</span></summary>
            <x-maintenance.activity :maintenance="$maintenance" />
        </details>
    </div>
</div>
<x-maintenance.action-modals :maintenance="$maintenance" :staff-options="$staffOptions" :staff-create-data="$staffCreateData" />
@canany(['super-admin', 'maintenance.edit'])
    <div data-maintenance-action-host data-maintenance-edit-host></div>
@endcanany
@endsection
