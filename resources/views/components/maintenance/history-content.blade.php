@props(['leasable', 'orders'])
<div class="space-y-6">
    <p class="text-sm text-gray-500">{{ $orders->total() }} work orders</p>
            @forelse($orders as $order)
                <article class="rounded-xl border border-gray-200 p-4 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="break-words font-semibold text-slate-900"><a href="{{ route('admin.maintenance.work-orders.show', $order) }}" class="text-indigo-600 hover:underline">{{ $order->maintenance_number ?: 'Maintenance' }}</a> {{ $order->title }} (QTY:{{ $order->qty ?? 1 }})</h3>
                        <x-maintenance.status :status="strtolower($order->status)" />
                    </div>
                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        @unless($leasable)<div class="sm:col-span-2"><dt class="text-xs text-gray-500">Property / Unit / Room</dt><dd class="mt-1 text-slate-900">{{ $order->leasable_name }} {{ $order->leasable_type_label ? ' - '.$order->leasable_type_label : '' }}</dd></div>@endunless
                        <div><dt class="text-xs text-gray-500">Contractor</dt><dd class="mt-1 font-medium text-slate-900">{{ $order->contractor_name ?: 'Unassigned' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Source</dt><dd class="mt-1 text-slate-900">{{ match ($order->source_type) { 'manual' => 'Manual', 'refund' => 'Checkout Refund', 'customer_service' => 'Customer Service', default => 'Not recorded' } }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Problem description</dt><dd class="mt-1 whitespace-pre-wrap break-words text-slate-900">{{ $order->desc }}</dd></div>
                        @if($order->location_detail)<div class="sm:col-span-2"><dt class="text-xs text-gray-500">Location details</dt><dd class="mt-1 break-words text-slate-900">{{ $order->location_detail }}</dd></div>@endif
                    </dl>
                    <x-maintenance.activity :maintenance="$order" />
                    @if($order->attachments->isNotEmpty())
                        <details class="mt-4"><summary class="cursor-pointer text-sm font-semibold text-slate-700">Evidence ({{ $order->attachments->count() }})</summary>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@foreach($order->attachments as $file)<x-maintenance.evidence :file="$file" />@endforeach</div>
                        </details>
                    @endif
                </article>
            @empty
                <p class="py-8 text-center text-sm text-gray-500">{{ $leasable ? 'No maintenance history for this '.strtolower(class_basename($leasable)).'.' : 'No maintenance history yet.' }}</p>
            @endforelse
            <div data-maintenance-history-pagination>{{ $orders->links() }}</div>
</div>
