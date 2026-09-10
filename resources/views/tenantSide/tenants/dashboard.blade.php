<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Tenant Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-6 sm:py-10 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- =========================================================
                Welcome Section
            ========================================================= --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6 mb-6">

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">

                    <div>
                        <p class="text-sm text-gray-500">
                            Welcome back
                        </p>

                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-1">
                            {{ Auth::user()->name }}
                        </h1>

                        <p class="text-sm text-gray-500 mt-2">
                            Manage and view your tenancy information here.
                        </p>
                    </div>

                    {{-- Role Badge --}}
                    <div class="self-start sm:self-center">
                        <span class="inline-flex items-center px-3 py-1.5
                                     rounded-full
                                     bg-indigo-50
                                     text-indigo-700
                                     border border-indigo-100
                                     text-xs font-bold uppercase tracking-wide">
                            Tenant
                        </span>
                    </div>

                </div>

            </div>


            {{-- =========================================================
                Section Header
            ========================================================= --}}
            <div class="mb-4">
                <h2 class="text-lg sm:text-xl font-bold text-slate-900">
                    My Tenancy
                </h2>

                <p class="text-sm text-gray-500 mt-1">
                    Access your lease and billing information.
                </p>
            </div>


            {{-- =========================================================
                MOBILE VERSION
            ========================================================= --}}
            <div class="space-y-4 lg:hidden">

                {{-- My Lease --}}
                <a href="{{ route('tenants.leases.index') }}"
                   class="block bg-white rounded-2xl shadow-sm border border-gray-100
                          p-5 hover:shadow-md hover:border-indigo-200 transition-all">

                    <div class="flex items-center justify-between gap-4">

                        <div class="flex items-center gap-4 min-w-0">

                            {{-- Icon --}}
                            <div class="shrink-0 w-12 h-12 rounded-xl bg-indigo-50
                                        flex items-center justify-center">

                                <svg class="w-6 h-6 text-indigo-600"
                                     fill="none"
                                     stroke="currentColor"
                                     viewBox="0 0 24 24">

                                    <path stroke-linecap="round"
                                          stroke-linejoin="round"
                                          stroke-width="2"
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0
                                             01-2-2V5a2 2 0 012-2h5.586
                                             a1 1 0 01.707.293l5.414
                                             5.414a1 1 0 01.293.707V19
                                             a2 2 0 01-2 2z" />
                                </svg>

                            </div>

                            <div class="min-w-0">
                                <h3 class="text-lg font-bold text-gray-900">
                                    My Lease
                                </h3>

                                <p class="text-sm text-gray-500 mt-1">
                                    View your lease details and agreement.
                                </p>
                            </div>

                        </div>

                        <div class="shrink-0 text-indigo-600">
                            <svg class="w-5 h-5"
                                 fill="none"
                                 stroke="currentColor"
                                 viewBox="0 0 24 24">

                                <path stroke-linecap="round"
                                      stroke-linejoin="round"
                                      stroke-width="2"
                                      d="M9 5l7 7-7 7" />
                            </svg>
                        </div>

                    </div>

                </a>


                {{-- My Invoices --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 opacity-60">

                    <div class="flex items-center justify-between gap-4">

                        <div class="flex items-center gap-4 min-w-0">

                            {{-- Icon --}}
                            <div class="shrink-0 w-12 h-12 rounded-xl bg-emerald-50
                                        flex items-center justify-center">

                                <svg class="w-6 h-6 text-emerald-600"
                                     fill="none"
                                     stroke="currentColor"
                                     viewBox="0 0 24 24">

                                    <path stroke-linecap="round"
                                          stroke-linejoin="round"
                                          stroke-width="2"
                                          d="M9 14l2 2 4-4m5-4V6
                                             a2 2 0 00-2-2H6a2 2 0
                                             00-2 2v12a2 2 0 002 2h12
                                             a2 2 0 002-2V8z" />
                                </svg>

                            </div>

                            <div class="min-w-0">
                                <h3 class="text-lg font-bold text-gray-900">
                                    My Invoices
                                </h3>

                                <p class="text-sm text-gray-500 mt-1">
                                    View invoices, payments and receipts.
                                </p>
                            </div>

                        </div>

                        <span class="shrink-0 text-xs font-semibold
                                     bg-gray-100 text-gray-500
                                     px-2.5 py-1 rounded-full">
                            Coming Soon
                        </span>

                    </div>

                </div>

            </div>


            {{-- =========================================================
                DESKTOP VERSION
            ========================================================= --}}
            <div class="hidden lg:grid lg:grid-cols-2 gap-6">

                {{-- My Lease --}}
                <a href="{{ route('tenants.leases.index') }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-200
                          p-6 hover:shadow-md hover:border-indigo-300 transition-all">

                    <div class="flex items-start justify-between gap-6">

                        <div class="flex items-start gap-4">

                            {{-- Icon --}}
                            <div class="w-14 h-14 rounded-xl bg-indigo-50
                                        flex items-center justify-center
                                        group-hover:bg-indigo-100 transition-colors">

                                <svg class="w-7 h-7 text-indigo-600"
                                     fill="none"
                                     stroke="currentColor"
                                     viewBox="0 0 24 24">

                                    <path stroke-linecap="round"
                                          stroke-linejoin="round"
                                          stroke-width="2"
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0
                                             01-2-2V5a2 2 0 012-2h5.586
                                             a1 1 0 01.707.293l5.414
                                             5.414a1 1 0 01.293.707V19
                                             a2 2 0 01-2 2z" />
                                </svg>

                            </div>

                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-gray-400">
                                    Lease
                                </p>

                                <h3 class="text-xl font-bold text-slate-900 mt-1">
                                    My Lease
                                </h3>

                                <p class="text-sm text-gray-500 mt-2 max-w-sm">
                                    View your current lease, tenancy period,
                                    rental information and agreement details.
                                </p>
                            </div>

                        </div>

                        <div class="text-indigo-600 group-hover:translate-x-1 transition-transform mt-1">
                            <svg class="w-6 h-6"
                                 fill="none"
                                 stroke="currentColor"
                                 viewBox="0 0 24 24">

                                <path stroke-linecap="round"
                                      stroke-linejoin="round"
                                      stroke-width="2"
                                      d="M9 5l7 7-7 7" />
                            </svg>
                        </div>

                    </div>

                    <div class="mt-6 pt-4 border-t border-gray-100">
                        <span class="inline-flex items-center text-sm font-semibold text-indigo-600">
                            View Lease Details

                            <svg class="w-4 h-4 ml-1.5"
                                 fill="none"
                                 stroke="currentColor"
                                 viewBox="0 0 24 24">

                                <path stroke-linecap="round"
                                      stroke-linejoin="round"
                                      stroke-width="2"
                                      d="M9 5l7 7-7 7" />
                            </svg>
                        </span>
                    </div>

                </a>


                {{-- My Invoices --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-200
                            p-6 opacity-60">

                    <div class="flex items-start justify-between gap-6">

                        <div class="flex items-start gap-4">

                            {{-- Icon --}}
                            <div class="w-14 h-14 rounded-xl bg-emerald-50
                                        flex items-center justify-center">

                                <svg class="w-7 h-7 text-emerald-600"
                                     fill="none"
                                     stroke="currentColor"
                                     viewBox="0 0 24 24">

                                    <path stroke-linecap="round"
                                          stroke-linejoin="round"
                                          stroke-width="2"
                                          d="M9 14l2 2 4-4m5-4V6
                                             a2 2 0 00-2-2H6a2 2 0
                                             00-2 2v12a2 2 0 002 2h12
                                             a2 2 0 002-2V8z" />
                                </svg>

                            </div>

                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-gray-400">
                                    Billing
                                </p>

                                <h3 class="text-xl font-bold text-slate-900 mt-1">
                                    My Invoices
                                </h3>

                                <p class="text-sm text-gray-500 mt-2 max-w-sm">
                                    View invoice details, payment records
                                    and receipts related to your lease.
                                </p>
                            </div>

                        </div>

                        <span class="text-xs font-semibold
                                     bg-gray-100 text-gray-500
                                     px-2.5 py-1 rounded-full">
                            Coming Soon
                        </span>

                    </div>

                    <div class="mt-6 pt-4 border-t border-gray-100">
                        <span class="text-sm font-semibold text-gray-400">
                            Invoice module will be available soon
                        </span>
                    </div>

                </div>

            </div>

        </div>
    </div>
</x-app-layout>