<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans text-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Invoices</h1>
                    <p class="mt-2 text-sm text-gray-500">View and process tenant invoices and payment records.</p>
                </div>
                <button type="button" @click="$dispatch('open-modal', 'invoice-statement')"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 shadow-sm transition-all">View Statement</button>
            </div>

            <x-invoice-statement-modal :endpoint="route('admin.invoices.statement')" />

            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="p-5 border-b border-gray-100 bg-white">
                    <div class="flex justify-end">
                        <x-form.form method="GET" action="{{ route('admin.invoices.index') }}" class="flex flex-wrap items-center gap-4">
                            <div class="flex items-stretch justify-between">
                                <x-table.search placeholder="Search invoices..." />
                            </div>
                        </x-form.form>
                    </div>
                </div>

                <x-table.invoice-table :invoices="$invoices" :ajax="false" :show-tenant="true" />
            </div>
        </div>
    </div>
</x-app-layout>
