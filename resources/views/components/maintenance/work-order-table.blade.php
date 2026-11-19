@props(['orders'])
<x-data-table :collection="$orders" :columns="['Work order / item', 'Property / Unit / Room', 'Location', 'Source', 'Assigned Staff', 'Status', 'Created']" :overflowVisible="true">
    <x-slot name="header">
        <div class="flex justify-end">
            <x-form.form method="GET" :action="route('admin.maintenance.work-orders.index')" class="flex flex-wrap items-center gap-4">
                <x-table.search placeholder="Search by item, reference or property..." :isAdvanceSearch="false" :showStatusField="true"
                    :statusOptions="['' => 'All statuses', 'pending' => 'Pending', 'maintaining' => 'Maintaining', 'completed' => 'Completed']" />
            </x-form.form>
        </div>
    </x-slot>
    @foreach($orders as $order)
        @php
            $lease = $order->lease;
            $leasableRoute = match ($order->leasable_type) {
                \App\Models\Property::class => 'admin.properties.show',
                \App\Models\Unit::class => 'admin.units.show',
                \App\Models\Room::class => 'admin.rooms.show',
                default => null,
            };
        @endphp
        <tr class="cursor-pointer transition-colors hover:!bg-indigo-50" onclick="if (!event.target.closest('a, button, input')) window.location.href = @js(route('admin.maintenance.work-orders.show', $order))">
            <td class="px-6 py-4 text-sm"><span class="block break-all text-xs text-gray-500">{{ $order->maintenance_number }}</span><a href="{{ route('admin.maintenance.work-orders.show', $order) }}" class="font-medium text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500">{{ $order->title }}</a><span class="block text-gray-500">Qty: {{ $order->qty ?? 1 }}</span></td>
            <td class="px-6 py-4 text-sm">
                @if($order->leasable && $leasableRoute)
                    <a href="{{ route($leasableRoute, $order->leasable_id) }}" class="font-semibold text-indigo-600 hover:text-indigo-900 hover:underline">{{ $order->leasable_name }}</a>
                    <div class="text-sm text-gray-400 italic">{{ $order->leasable_type_label }}</div>
                @else
                    <span class="text-gray-700">{{ $order->location_snapshot ?: 'Not recorded' }}</span>
                @endif
            </td>
            <td class="px-6 py-4 text-sm text-gray-500">{{ $order->location_detail ?: 'Not recorded' }}</td>
            <td class="px-6 py-4 text-sm text-gray-500">
                <x-maintenance.source :order="$order" />
            </td>
            <td class="px-6 py-4 text-sm text-gray-700">{{ $order->contractor_name ?: 'Unassigned' }}</td>
            <td class="px-6 py-4"><x-maintenance.status :status="$order->status" /></td>
            <td class="px-6 py-4 text-sm text-gray-500">{{ $order->created_at?->timezone('Asia/Kuala_Lumpur')->format('d/m/Y') }}</td>
            <td class="px-6 py-4 text-center text-sm" onclick="event.stopPropagation()">
                <x-maintenance.row-actions :order="$order" />
            </td>
        </tr>
    @endforeach
</x-data-table>
