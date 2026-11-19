<x-app-layout :full-height="true">
    <div class="flex min-h-0 flex-1 flex-col">
    @if(!$canCreate)
    <div class="shrink-0 px-4 pt-2 text-sm text-amber-800" role="status">
        @unless(app(\App\Services\MaintenanceService::class)->ready())
            <p>Maintenance database upgrade is required. Existing conversations remain available.</p>
        @endunless
    </div>
    @endif
    <div class="flex min-h-0 flex-1 flex-col bg-gray-50 py-2 font-sans sm:py-3" data-customer-service data-index-url="{{ route($routePrefix.'.index') }}" data-ticket-url-template="{{ route($routePrefix.'.show', ['ticket' => '__TICKET__']) }}" data-selected-ticket="{{ $selectedTicket?->id }}">
        <div class="flex min-h-0 w-full flex-1 flex-col px-2 sm:px-4">
            {{-- Reserve feedback space: refreshing the list must not change the page height. --}}
            <p data-page-feedback role="status" class="h-5 shrink-0 overflow-y-auto text-sm text-indigo-700"></p>
            <div class="grid min-h-0 flex-1 grid-rows-[minmax(0,1fr)] gap-3 md:grid-cols-[minmax(260px,340px)_minmax(0,1fr)]">
                <aside data-ticket-sidebar class="{{ $selectedTicket ? 'hidden' : 'flex' }} md:flex flex-col min-h-0 min-w-0 rounded-2xl border border-gray-100 bg-white shadow-sm">
                    <div class="flex shrink-0 items-center justify-between p-4 border-b">
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
                    <form method="GET" action="{{ route($routePrefix.'.index') }}" data-ticket-search class="shrink-0 p-3">
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
                            <x-customer-service.chat :current-role="\Illuminate\Support\Facades\Auth::user()->role" :embedded="true" :can-attach="\Illuminate\Support\Facades\Gate::any(['is-tenant', 'owner-admin', 'is-owner', 'service request.reply'])" />
                        @endif
                    </div>
                </section>
            </div>
        </div>
        @can('is-tenant')
        <x-customer-service.create-modal :leases="$complaintLeases" :action="route('tenants.customerService.store')" />
        @endcan
    </div>
    @if(!$canCreate)<x-maintenance.create-modal :item-options="$itemOptions" />@endif
    </div>
</x-app-layout>
