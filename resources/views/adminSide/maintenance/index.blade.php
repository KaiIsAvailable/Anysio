<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Maintenance</h1>
                    <p class="mt-2 text-sm text-gray-500">Track manual maintenance and work orders raised from service requests.</p>
                </div>
                @if($ready)
                    <div class="flex flex-wrap items-center gap-3">
                    @canany(['super-admin', 'maintenance.create'])
                        <x-form.primary-button type="button" x-data="{ openingMaintenance: false }" loading="openingMaintenance"
                            x-on:click="$dispatch('open-modal', 'create-maintenance')">Add New Maintenance</x-form.primary-button>
                    @endcanany
                        <x-maintenance.history />
                    </div>
                @endif
            </div>

            @if(!$ready)
                <x-maintenance.section title="Maintenance unavailable">
                    <p role="status">Maintenance database upgrade is required. Existing conversations remain available.</p>
                </x-maintenance.section>
            @else
                <x-maintenance.work-order-table :orders="$orders" />
            @endif
        </div>
    </div>
    @if($ready)
        @canany(['super-admin', 'maintenance.view', 'maintenance.edit', 'maintenance.assign', 'maintenance.complete'])
            <div data-maintenance-action-host data-maintenance-edit-host></div>
        @endcanany
        @canany(['super-admin', 'maintenance.create'])
            <x-maintenance.create-modal :manual="true" :leasable-options="$leasableOptions" :item-options="$itemOptions" />
        @endcanany
    @endif
</x-app-layout>
