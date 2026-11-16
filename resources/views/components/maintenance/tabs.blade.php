<nav class="mx-auto flex max-w-7xl flex-wrap gap-2 px-4 pt-4 sm:px-6 lg:px-8" aria-label="Maintenance sections">
    @can('maintenance.view')
        <x-form.secondary-button x-on:click="maintenanceTab = 'orders'" ::aria-pressed="maintenanceTab === 'orders'" ::class="{ 'ring-2 ring-indigo-500 text-indigo-700': maintenanceTab === 'orders' }">Work Orders</x-form.secondary-button>
    @endcan
    <x-form.secondary-button x-on:click="maintenanceTab = 'chat'" ::aria-pressed="maintenanceTab === 'chat'" ::class="{ 'ring-2 ring-indigo-500 text-indigo-700': maintenanceTab === 'chat' }">Customer Service Conversations</x-form.secondary-button>
</nav>
