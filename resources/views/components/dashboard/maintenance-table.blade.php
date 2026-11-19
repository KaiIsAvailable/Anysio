@props(['orders'])
@php
    $showActions = \Illuminate\Support\Facades\Gate::any(['super-admin', 'maintenance.assign', 'maintenance.complete']);
@endphp
<div class="overflow-x-auto">
    <table class="w-full border-collapse text-left">
        <thead>
            <tr class="border-b border-slate-100 text-xs font-bold uppercase tracking-wider text-slate-400">
                <th class="px-4 py-3">Maintenance / Property</th>
                <th class="px-4 py-3">Source</th>
                <th class="px-4 py-3">Status</th>
                @if($showActions)<th class="px-4 py-3">Actions</th>@endif
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50 text-sm">
            @forelse($orders as $order)
                @php
                    $assetRoute = match ($order->leasable_type) {
                        \App\Models\Property::class => 'admin.properties.show',
                        \App\Models\Unit::class => 'admin.units.show',
                        \App\Models\Room::class => 'admin.rooms.show',
                        default => null,
                    };
                    $badge = get_status_badge($order->status);
                    $assetPermission = match ($order->leasable_type) {
                        \App\Models\Property::class => 'property.show unit',
                        \App\Models\Unit::class => 'unit.show room',
                        \App\Models\Room::class => 'room.show',
                        default => null,
                    };
                @endphp
                <tr class="group cursor-pointer transition-colors duration-150 hover:!bg-indigo-50" onclick="if (!event.target.closest('a, button, input')) window.location.href = @js(route('admin.maintenance.work-orders.show', $order))">
                    <td class="px-4 py-3">
                        <p class="font-bold text-slate-800"><a href="{{ route('admin.maintenance.work-orders.show', $order) }}" class="hover:text-indigo-600">{{ $order->title }}</a>@if(($order->qty ?? 1) > 1) <span class="font-normal text-slate-500">&times;{{ $order->qty }}</span>@endif</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $order->maintenance_number }} &middot;
                            @if($assetRoute && $order->leasable)
                                @canany(['super-admin', $assetPermission])<a href="{{ route($assetRoute, $order->leasable_id) }}" class="text-indigo-600 hover:text-indigo-900 hover:underline">{{ $order->leasable_name }}</a>@else{{ $order->leasable_name }}@endcanany
                            @else{{ $order->location_snapshot ?: 'Not recorded' }}@endif
                        </p>
                    </td>
                    <td class="px-4 py-3 text-slate-600"><x-maintenance.source :order="$order" :compact="true" /></td>
                    <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold uppercase {{ $badge }}">{{ $order->status }}</span>@if($order->contractor_name)<p class="mt-1 text-xs text-slate-500">{{ $order->contractor_name }}</p>@endif</td>
                    @if($showActions)<td class="px-4 py-3" onclick="event.stopPropagation()"><x-maintenance.row-actions :order="$order" :dashboard="true" /></td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ $showActions ? 4 : 3 }}" class="py-8 text-center text-sm font-medium text-slate-400">No ongoing maintenance found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($orders->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $orders->links() }}</div>@endif
