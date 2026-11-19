@props(['leasable' => null])
@canany(['super-admin', 'maintenance.view'])
    @can('maintenance.tab')
    <x-form.primary-button type="button" data-maintenance-history
        data-history-url="{{ $leasable ? route('admin.maintenance.history', ['type' => strtolower(class_basename($leasable)), 'id' => $leasable->id]) : route('admin.maintenance.history.index') }}">
        <svg class="mr-2 h-4 w-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        Maintenance History
    </x-form.primary-button>
    <x-maintenance.history-modal :leasable="$leasable" />
    @endcan
@endcanany
