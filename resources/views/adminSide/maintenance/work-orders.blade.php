@extends('adminSide.maintenance.frame')
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div><h1 class="text-3xl font-bold tracking-tight text-slate-900">Work Orders</h1><p class="mt-2 text-sm text-gray-500">Track maintenance raised from customer service conversations.</p></div>
    <x-form.secondary-button onclick="location.reload()">Refresh</x-form.secondary-button>
</div>
@if(!$ready)
    <x-maintenance.section title="Maintenance unavailable"><p role="status">Maintenance database upgrade is required. Existing conversations remain available.</p></x-maintenance.section>
@else
    <x-maintenance.work-order-table :orders="$orders" />
@endif
@endsection
