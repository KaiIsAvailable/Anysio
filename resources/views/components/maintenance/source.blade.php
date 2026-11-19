@props(['order', 'compact' => false])
@php
    $lease = $order->lease;
@endphp
@if($order->source_type === 'customer_service' && $order->ticket_id)
    @canany(['super-admin', 'service request.tab'])
    <a href="{{ route('admin.maintenance.index', ['ticket' => $order->ticket_id]) }}" class="font-semibold text-indigo-600 hover:text-indigo-900 hover:underline">Customer Service</a>
    @else<span>Customer Service</span>@endcanany
@elseif($order->source_type === 'refund')
    @canany(['super-admin', 'leases.show'])
    <a href="{{ route('admin.leases.show', $order->lease_id) }}" class="font-semibold text-indigo-600 hover:text-indigo-900 hover:underline">Checkout Refund</a>
    @else<span>Checkout Refund</span>@endcanany
@else
    <span>Manual</span>
@endif
@if($lease && $compact)
    <div class="mt-1 flex flex-wrap gap-x-2 text-xs">
        @if($lease->tenant)
            @canany(['super-admin', 'tenant.show'])
            <a href="{{ route('admin.tenants.show', $lease->tenant) }}" class="text-indigo-600 hover:text-indigo-900 hover:underline">{{ $lease->tenant->user?->name ?? 'Not recorded' }}</a>
            @else<span>{{ $lease->tenant->user?->name ?? 'Not recorded' }}</span>@endcanany
        @endif
        @if($order->source_type !== 'refund')
            @canany(['super-admin', 'leases.show'])
            <a href="{{ route('admin.leases.show', $lease) }}" class="text-indigo-600 hover:text-indigo-900 hover:underline">Lease</a>
            @endcanany
        @endif
    </div>
@elseif($lease)
    <div class="mt-1">
        <span>Lease:</span>
        @canany(['super-admin', 'leases.show'])
        <a href="{{ route('admin.leases.show', $lease) }}" class="text-indigo-600 hover:text-indigo-900 hover:underline">{{ $lease->start_date?->format('d/m/Y') ?? 'Not recorded' }} to {{ $lease->end_date?->format('d/m/Y') ?? 'Not recorded' }}</a>
        @else<span>{{ $lease->start_date?->format('d/m/Y') ?? 'Not recorded' }} to {{ $lease->end_date?->format('d/m/Y') ?? 'Not recorded' }}</span>@endcanany
    </div>
    @if($lease->tenant)
        <div class="mt-1">
            <span>Tenant:</span>
            @canany(['super-admin', 'tenant.show'])
            <a href="{{ route('admin.tenants.show', $lease->tenant) }}" class="text-indigo-600 hover:text-indigo-900 hover:underline">{{ $lease->tenant->user?->name ?? 'Not recorded' }}</a>
            @else<span>{{ $lease->tenant->user?->name ?? 'Not recorded' }}</span>@endcanany
        </div>
    @endif
@endif
