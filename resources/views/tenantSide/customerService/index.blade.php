<x-app-layout>
    <div x-data="{ maintenanceTab: @js(!$canCreate && !$selectedTicket && \Illuminate\Support\Facades\Gate::allows('maintenance.view') ? 'orders' : 'chat') }">
    @if(!$canCreate)
    <nav class="mx-auto flex max-w-7xl gap-2 px-4 pt-4" aria-label="Maintenance sections">
        @can('maintenance.view')<button type="button" x-on:click="maintenanceTab = 'orders'" :aria-pressed="maintenanceTab === 'orders'" class="rounded-lg border bg-white px-4 py-2 text-indigo-700">维修工单</button>@endcan
        <button type="button" x-on:click="maintenanceTab = 'chat'" :aria-pressed="maintenanceTab === 'chat'" class="rounded-lg border bg-white px-4 py-2 text-indigo-700">Customer Service 对话</button>
    </nav>
    <div class="mx-auto max-w-7xl px-4 pt-2 text-sm text-amber-800" role="status">
        @unless(app(\App\Services\MaintenanceService::class)->ready())
            <p>Maintenance database upgrade is required. Existing conversations remain available.</p>
        @endunless
        @cannot('maintenance.view')<p>Work orders require maintenance.view permission.</p>@endcannot
        @cannot('maintenance.create')<p>Create Maintenance requires maintenance.create permission.</p>@endcannot
    </div>
    @can('maintenance.view')<section x-show="maintenanceTab === 'orders'" class="mx-auto max-w-7xl p-4"><iframe title="Maintenance work orders" src="{{ route('admin.maintenance.work-orders.index') }}" class="h-[80vh] w-full rounded-xl border" loading="lazy"></iframe></section>@endcan
    @endif
    <div class="py-4 sm:py-6 bg-gray-50 font-sans" x-show="maintenanceTab === 'chat'" data-customer-service data-index-url="{{ route($routePrefix.'.index') }}" data-ticket-url-template="{{ route($routePrefix.'.show', ['ticket' => '__TICKET__']) }}" data-selected-ticket="{{ $selectedTicket?->id }}">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
            <h1 class="mb-4 text-2xl font-bold text-slate-900">{{ $canCreate ? 'Customer Service' : 'Maintenance' }}</h1>
            {{-- Reserve feedback space: refreshing the list must not change the page height. --}}
            <p data-page-feedback role="status" class="h-5 overflow-y-auto text-sm text-indigo-700"></p>
            <div class="grid grid-rows-[minmax(0,1fr)] md:grid-cols-[minmax(260px,340px)_minmax(0,1fr)] gap-3 h-[calc(100dvh-12.25rem)] min-h-[400px]">
                <aside data-ticket-sidebar class="{{ $selectedTicket ? 'hidden' : 'flex' }} md:flex flex-col min-h-0 min-w-0 rounded-2xl border border-gray-100 bg-white shadow-sm">
                    <div class="flex items-center justify-between p-4 border-b">
                        <h2 class="font-semibold text-slate-900">Conversations</h2>
                        @can('is-tenant')
                        <button type="button" data-open-complaint title="New Complaint" aria-label="New Complaint" @disabled($creationReason) class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50">
                            <svg aria-hidden="true" focusable="false" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </button>
                        @endcan
                    </div>
                    @can('is-tenant')
                    <p data-creation-reason class="px-4 text-xs text-amber-800" @if(!$creationReason) hidden @endif>{{ $creationReason }}</p>
                    @endcan
                    <form method="GET" action="{{ route($routePrefix.'.index') }}" data-ticket-search class="p-3">
                        <x-table.search :isAdvanceSearch="false" :async="true" :compact="true" placeholder="Search by subject..." />
                    </form>
                    <div data-ticket-list class="flex-1 min-h-0 overflow-y-auto">@include('tenantSide.customerService.table', compact('tickets'))</div>
                </aside>
                <section data-conversation-pane class="{{ $selectedTicket ? 'flex' : 'hidden' }} md:flex flex-col min-w-0 min-h-0">
                    <p data-conversation-feedback role="status" class="px-4 py-2 text-sm text-red-700" hidden></p>
                    <button type="button" data-retry-conversation hidden class="self-start px-4 py-2 text-indigo-700 underline">Retry</button>
                    <div data-conversation-host class="flex-1 min-h-0">
                        @if($selectedTicket)
                            {!! $chatHtml !!}
                        @else
                            <x-customer-service.chat :current-role="\Illuminate\Support\Facades\Auth::user()->role" :embedded="true" :can-attach="\Illuminate\Support\Facades\Gate::any(['is-tenant', 'owner-admin', 'is-owner'])" />
                        @endif
                    </div>
                </section>
            </div>
        </div>
        @can('is-tenant')
        <x-customer-service.create-modal :leases="$complaintLeases" :action="route('tenants.customerService.store')" />
        @endcan
    </div>
    @if(!$canCreate)<x-maintenance.create-modal />@endif
    </div>
</x-app-layout>
