@extends('adminSide.maintenance.frame')
@section('content')
<div class="mb-5 flex items-center justify-between"><h1 class="text-xl font-bold">Maintenance work orders</h1><button type="button" onclick="location.reload()" class="text-sm text-indigo-700 underline">Refresh</button></div>
@if(!$ready)
    <p role="status" class="rounded-xl border bg-white p-4">Maintenance database upgrade is required. Existing conversations remain available.</p>
@else
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <x-form.text-input name="search" :value="request('search')" placeholder="Item, work order or property" aria-label="Search work orders" />
        <select name="status" aria-label="Status" class="rounded-lg border-gray-300"><option value="">All statuses</option>@foreach(['pending','maintaining','completed'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
        <x-form.primary-button>Filter</x-form.primary-button>
    </form>
    <div class="overflow-x-auto rounded-xl border bg-white"><table class="w-full text-left text-sm"><thead class="bg-slate-50"><tr>@foreach(['Work order / item','Property / Unit / Room','Source','Contractor','Status','Created','Actions'] as $label)<th class="p-3">{{ $label }}</th>@endforeach</tr></thead><tbody>
    @forelse($orders as $order)
        <tr class="border-t"><td class="p-3"><span class="block break-all text-xs text-gray-500">{{ $order->maintenance_number }}</span>{{ $order->title }}</td><td class="p-3">{{ $order->location_snapshot }}<span class="block text-gray-500">{{ $order->location_detail }}</span></td><td class="p-3">Customer Service</td><td class="p-3">{{ $order->contractor_name ?: 'Unassigned' }}</td><td class="p-3"><x-maintenance.status :status="$order->status" /></td><td class="p-3">{{ $order->created_at?->format('d M Y H:i') }}</td><td class="p-3"><a href="{{ route('admin.maintenance.work-orders.show', $order) }}" class="text-indigo-700 underline">View</a>@if(in_array($order->status, ['pending','maintaining'])) @can('maintenance.assign')<a href="{{ route('admin.maintenance.work-orders.show', $order) }}#assign" class="block text-indigo-700 underline">{{ $order->status === 'pending' ? 'Assign' : 'Reassign' }}</a>@endcan @endif @if($order->status === 'maintaining') @can('maintenance.complete')<a href="{{ route('admin.maintenance.work-orders.show', $order) }}#complete" class="block text-indigo-700 underline">Complete</a>@endcan @endif</td></tr>
    @empty <tr><td colspan="7" class="p-8 text-center text-gray-500">No work orders found. Create one from a verified Customer Service conversation.</td></tr> @endforelse
    </tbody></table></div><div class="mt-4">{{ $orders->links() }}</div>
@endif
@endsection
