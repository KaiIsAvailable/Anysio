@props(['order', 'dashboard' => false])
<div class="flex items-center justify-center gap-2">
@canany(['super-admin', 'maintenance.edit'])
    @if(!$dashboard && $order->status !== 'completed')
        <a href="{{ route('admin.maintenance.work-orders.edit', $order) }}" data-edit-maintenance class="inline-flex rounded-lg bg-indigo-50 p-2 text-indigo-600 transition-colors hover:bg-indigo-100 hover:text-indigo-900" title="Edit maintenance" aria-label="Edit maintenance">
            <svg class="h-5 w-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
        </a>
    @endif
@endcanany
@canany(['super-admin', 'maintenance.assign'])
    @if(in_array($order->status, ['pending', 'maintaining'], true))
        <a href="{{ route('admin.maintenance.work-orders.assignment', $order) }}" data-assign-maintenance
            class="inline-flex rounded-lg p-2 transition-colors {{ $order->status === 'pending' ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 hover:text-emerald-900' : 'bg-yellow-50 text-yellow-700 hover:bg-yellow-100 hover:text-yellow-900' }}"
            title="{{ $order->status === 'pending' ? 'Assign Contractor' : 'Change Contractor' }}" aria-label="{{ $order->status === 'pending' ? 'Assign Contractor' : 'Change Contractor' }}">
            @if($order->status === 'pending')
                <svg class="h-5 w-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2m18-13v6m3-3h-6M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
            @else
                <svg class="h-5 w-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M12 7a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0zM16 5h6m-2-2 2 2-2 2m2 4h-6m2-2-2 2 2 2" /></svg>
            @endif
        </a>
    @endif
@endcanany
@canany(['super-admin', 'maintenance.complete'])
    @if($order->status === 'maintaining')
        <a href="{{ route('admin.maintenance.work-orders.completion', $order) }}" data-complete-maintenance
            class="inline-flex rounded-lg bg-indigo-50 p-2 text-indigo-600 transition-colors hover:bg-indigo-100 hover:text-indigo-900"
            title="Complete Maintenance" aria-label="Complete Maintenance">
            <svg class="h-5 w-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
        </a>
    @endif
@endcanany
</div>
