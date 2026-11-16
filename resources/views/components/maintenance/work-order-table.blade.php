@props(['orders'])
<x-data-table :collection="$orders" :columns="['Work order / item', 'Property / Unit / Room', 'Source', 'Contractor', 'Status', 'Created']">
    <x-slot name="header">
        <form method="GET" action="{{ route('admin.maintenance.work-orders.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="min-w-56 flex-1"><x-form.input-label for="maintenance-search" value="Search work orders" /><x-form.text-input id="maintenance-search" name="search" :value="request('search')" placeholder="Item, work order or property" /></div>
            <div class="w-full sm:w-48"><x-form.input-label for="maintenance-status" value="Status" /><x-form.input-select id="maintenance-status" name="status" :value="request('status')" :options="['' => 'All statuses', 'pending' => 'Pending', 'maintaining' => 'Maintaining', 'completed' => 'Completed']" placeholder="All statuses" /></div>
            <x-form.primary-button>Filter</x-form.primary-button>
        </form>
    </x-slot>
    @foreach($orders as $order)
        <tr class="transition-colors hover:bg-gray-50">
            <td class="px-6 py-4 text-sm"><span class="block break-all text-xs text-gray-500">{{ $order->maintenance_number }}</span><span class="font-medium text-slate-900">{{ $order->title }}</span></td>
            <td class="px-6 py-4 text-sm text-gray-700">{{ $order->location_snapshot }}<span class="block text-gray-500">{{ $order->location_detail }}</span></td>
            <td class="px-6 py-4 text-sm text-gray-500">Customer Service</td>
            <td class="px-6 py-4 text-sm text-gray-700">{{ $order->contractor_name ?: 'Unassigned' }}</td>
            <td class="px-6 py-4"><x-maintenance.status :status="$order->status" /></td>
            <td class="px-6 py-4 text-sm text-gray-500">{{ $order->created_at?->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i') }}</td>
            <td class="px-6 py-4 text-center text-sm"><a href="{{ route('admin.maintenance.work-orders.show', $order) }}" class="font-medium text-indigo-600 hover:text-indigo-900">View</a></td>
        </tr>
    @endforeach
</x-data-table>
