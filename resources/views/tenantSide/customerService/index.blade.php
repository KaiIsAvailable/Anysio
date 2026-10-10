<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans" data-customer-service data-index-url="{{ route('tenants.customerService.index') }}">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Customer Service</h1>
                    <p class="mt-2 text-sm text-gray-500">View your tickets and contact your property owner.</p>
                </div>
                <button type="button" data-open-complaint @disabled($creationReason) class="inline-flex items-center rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50">Complaint</button>
            </div>
            <p data-creation-reason class="mb-4 text-sm text-amber-800" @if(!$creationReason) hidden @endif>{{ $creationReason }}</p>
            <p data-page-feedback role="status" class="mb-4 text-sm text-indigo-700"></p>
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex justify-center">
                    <form method="GET" action="{{ route('tenants.customerService.index') }}" data-ticket-search class="flex w-full max-w-xl gap-2">
                        <label class="sr-only" for="ticket-search">Search by subject</label>
                        <input id="ticket-search" type="search" name="search" value="{{ $search }}" maxlength="255" placeholder="Search by subject..." class="w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button class="rounded-xl bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200">Search</button>
                    </form>
                </div>
                <div data-ticket-list>@include('tenantSide.customerService.table', compact('tickets'))</div>
            </div>
        </div>

        <x-customer-service.create-modal :action="route('tenants.customerService.store')" />
    </div>
</x-app-layout>
