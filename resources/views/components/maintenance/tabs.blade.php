<nav class="flex shrink-0 flex-wrap gap-2 px-2 pt-3 sm:px-4" aria-label="Maintenance sections">
    @can('maintenance.view')
        <x-form.secondary-button x-on:click="maintenanceTab = 'orders'" ::aria-pressed="maintenanceTab === 'orders'" ::class="{ 'ring-2 ring-indigo-500 text-indigo-700': maintenanceTab === 'orders' }">Work Orders</x-form.secondary-button>
    @endcan
    <x-form.secondary-button x-on:click="maintenanceTab = 'chat'" ::aria-pressed="maintenanceTab === 'chat'" ::class="{ 'ring-2 ring-indigo-500 text-indigo-700': maintenanceTab === 'chat' }">Customer Service Conversations</x-form.secondary-button>
</nav>
