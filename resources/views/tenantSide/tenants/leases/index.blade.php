<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Leases') }}
        </h2>
    </x-slot>

    <div class="min-h-screen bg-gray-50 py-6 sm:py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- =========================================================
                Page Header
            ========================================================= --}}
            <div class="mb-6">
                <a href="{{ route('tenants.dashboard') }}"
                   class="inline-flex items-center text-sm font-semibold text-indigo-600 hover:text-indigo-700 mb-4">

                    <svg class="w-4 h-4 mr-1"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M15 19l-7-7 7-7" />
                    </svg>

                    Back to Dashboard
                </a>

                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">
                        My Leases
                    </h1>

                    <p class="mt-1 text-sm text-gray-500">
                        View your current tenancy information and agreement details.
                    </p>
                </div>
            </div>


            @if($leases->count() > 0)

                {{-- =====================================================
                    MOBILE VERSION
                    Hidden on large screens
                ====================================================== --}}
                <div class="space-y-4 lg:hidden">

                    @foreach($leases as $lease)

                        @php
                            /*
                            |--------------------------------------------------------------------------
                            | Lease Type
                            |--------------------------------------------------------------------------
                            */
                            $type = class_basename($lease->leasable_type);

                            /*
                            |--------------------------------------------------------------------------
                            | Property / Unit / Room Name
                            |--------------------------------------------------------------------------
                            */
                            if ($type === 'Room') {
                                $propertyName = $lease->leasable?->room_no ?? 'N/A';
                            } elseif ($type === 'Unit') {
                                $propertyName = $lease->leasable?->unit_no ?? 'N/A';
                            } elseif ($type === 'Property') {
                                $propertyName = $lease->leasable?->name ?? 'N/A';
                            } else {
                                $propertyName = 'N/A';
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | Owner Name
                            |--------------------------------------------------------------------------
                            */
                            if ($type === 'Room') {
                                $ownerName = $lease->leasable?->unit?->owner?->name ?? 'N/A';
                            } else {
                                $ownerName = $lease->leasable?->owner?->name ?? 'N/A';
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | Rent Amount
                            |--------------------------------------------------------------------------
                            | FeeType category is Enum, so use ->value
                            */
                            $rentAmount = $lease->charges
                                ->filter(function ($charge) {
                                    return strtolower(
                                        $charge->feeType?->category?->value ?? ''
                                    ) === 'rent';
                                })
                                ->sum('amount');

                            /*
                            |--------------------------------------------------------------------------
                            | Status Style
                            |--------------------------------------------------------------------------
                            */
                            $status = strtolower((string) $lease->status);

                            $statusClass = match($status) {
                                'new' =>
                                    'bg-indigo-50 text-indigo-700 border-indigo-200',

                                'renew' =>
                                    'bg-emerald-50 text-emerald-700 border-emerald-200',

                                'check out' =>
                                    'bg-amber-50 text-amber-700 border-amber-200',

                                'end agreement' =>
                                    'bg-gray-100 text-gray-600 border-gray-200',

                                'cancelled' =>
                                    'bg-red-50 text-red-700 border-red-200',

                                default =>
                                    'bg-gray-100 text-gray-600 border-gray-200',
                            };
                        @endphp


                        {{-- Mobile Lease Card --}}
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">

                            {{-- Header --}}
                            <div class="p-5 border-b border-gray-100">
                                <div class="flex items-start justify-between gap-3">

                                    <div class="min-w-0">
                                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400">
                                            {{ $type }}
                                        </p>

                                        <h2 class="text-xl font-bold text-slate-900 mt-1 truncate">
                                            {{ $propertyName }}
                                        </h2>
                                    </div>

                                    <span class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border {{ $statusClass }}">
                                        {{ $lease->status ?? 'N/A' }}
                                    </span>

                                </div>
                            </div>


                            {{-- Content --}}
                            <div class="p-5 space-y-5">

                                {{-- Owner --}}
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                        Owner
                                    </p>

                                    <p class="text-sm font-semibold text-slate-900 mt-1">
                                        {{ $ownerName }}
                                    </p>
                                </div>


                                {{-- Lease Period --}}
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                        Lease Period
                                    </p>

                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm font-medium text-slate-900">

                                        <span>
                                            {{ $lease->start_date_formatted ?? '-' }}
                                        </span>

                                        <span class="text-gray-400">
                                            →
                                        </span>

                                        <span>
                                            {{ $lease->end_date_formatted ?? '-' }}
                                        </span>

                                    </div>
                                </div>


                                {{-- Rental Amount --}}
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                        Rental Amount
                                    </p>

                                    <p class="text-xl font-bold text-emerald-600 mt-1">
                                        RM {{ number_format($rentAmount / 100, 2) }}
                                    </p>
                                </div>


                                {{-- View Details --}}
                                <a href="{{ route('tenants.leases.show', $lease->id) }}"
                                   class="flex items-center justify-center w-full
                                          bg-indigo-600 hover:bg-indigo-700
                                          text-white font-bold text-sm
                                          rounded-xl px-4 py-3
                                          transition-colors">

                                    View Lease Details

                                    <svg class="w-4 h-4 ml-2"
                                         fill="none"
                                         stroke="currentColor"
                                         viewBox="0 0 24 24">

                                        <path stroke-linecap="round"
                                              stroke-linejoin="round"
                                              stroke-width="2"
                                              d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>

                            </div>
                        </div>

                    @endforeach
                </div>


                {{-- =====================================================
                    DESKTOP VERSION
                    Similar layout to Admin Lease List
                ====================================================== --}}
                <div class="hidden lg:block">

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

                        <div class="overflow-x-auto">
                            <table class="w-full divide-y divide-gray-200 text-left">

                                {{-- Table Header --}}
                                <thead class="bg-gray-50">
                                    <tr>

                                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            Property / Unit / Room
                                        </th>

                                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            Owner
                                        </th>

                                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            Duration
                                        </th>

                                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            Rental Amount
                                        </th>

                                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>

                                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            Action
                                        </th>

                                    </tr>
                                </thead>


                                {{-- Table Body --}}
                                <tbody class="divide-y divide-gray-100 bg-white">

                                    @foreach($leases as $lease)

                                        @php
                                            /*
                                            |--------------------------------------------------------------------------
                                            | Lease Type
                                            |--------------------------------------------------------------------------
                                            */
                                            $type = class_basename($lease->leasable_type);

                                            /*
                                            |--------------------------------------------------------------------------
                                            | Property / Unit / Room
                                            |--------------------------------------------------------------------------
                                            */
                                            if ($type === 'Room') {
                                                $propertyName = $lease->leasable?->room_no ?? 'N/A';
                                            } elseif ($type === 'Unit') {
                                                $propertyName = $lease->leasable?->unit_no ?? 'N/A';
                                            } elseif ($type === 'Property') {
                                                $propertyName = $lease->leasable?->name ?? 'N/A';
                                            } else {
                                                $propertyName = 'N/A';
                                            }

                                            /*
                                            |--------------------------------------------------------------------------
                                            | Owner
                                            |--------------------------------------------------------------------------
                                            */
                                            if ($type === 'Room') {
                                                $ownerName = $lease->leasable?->unit?->owner?->name ?? 'N/A';
                                            } else {
                                                $ownerName = $lease->leasable?->owner?->name ?? 'N/A';
                                            }

                                            /*
                                            |--------------------------------------------------------------------------
                                            | Rental Amount
                                            |--------------------------------------------------------------------------
                                            */
                                            $rentAmount = $lease->charges
                                                ->filter(function ($charge) {
                                                    return strtolower(
                                                        $charge->feeType?->category?->value ?? ''
                                                    ) === 'rent';
                                                })
                                                ->sum('amount');

                                            /*
                                            |--------------------------------------------------------------------------
                                            | Status Badge
                                            |--------------------------------------------------------------------------
                                            */
                                            $status = strtolower((string) $lease->status);

                                            $statusClass = match($status) {
                                                'new' =>
                                                    'bg-indigo-50 text-indigo-700 border-indigo-200',

                                                'renew' =>
                                                    'bg-emerald-50 text-emerald-700 border-emerald-200',

                                                'check out' =>
                                                    'bg-amber-50 text-amber-700 border-amber-200',

                                                'end agreement' =>
                                                    'bg-gray-100 text-gray-600 border-gray-200',

                                                'cancelled' =>
                                                    'bg-red-50 text-red-700 border-red-200',

                                                default =>
                                                    'bg-gray-100 text-gray-600 border-gray-200',
                                            };
                                        @endphp


                                        <tr class="hover:bg-indigo-50/60 transition-colors">

                                            {{-- Property --}}
                                            <td class="px-6 py-5">

                                                <div class="text-sm font-semibold text-slate-900">
                                                    {{ $propertyName }}
                                                </div>

                                                <div class="mt-1 text-xs text-gray-400">
                                                    {{ $type }}
                                                </div>

                                            </td>


                                            {{-- Owner --}}
                                            <td class="px-6 py-5">
                                                <div class="text-sm text-slate-900">
                                                    {{ $ownerName }}
                                                </div>
                                            </td>


                                            {{-- Duration --}}
                                            <td class="px-6 py-5">
                                                <div class="text-sm text-slate-900 whitespace-nowrap">
                                                    {{ $lease->start_date_formatted ?? '-' }}

                                                    <span class="mx-1 text-gray-400">
                                                        to
                                                    </span>

                                                    {{ $lease->end_date_formatted ?? '-' }}
                                                </div>
                                            </td>


                                            {{-- Rental Amount --}}
                                            <td class="px-6 py-5">
                                                <div class="text-sm font-bold text-emerald-600 whitespace-nowrap">
                                                    RM {{ number_format($rentAmount / 100, 2) }}
                                                </div>
                                            </td>


                                            {{-- Status --}}
                                            <td class="px-6 py-5">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border {{ $statusClass }}">
                                                    {{ $lease->status ?? 'N/A' }}
                                                </span>
                                            </td>


                                            {{-- Action --}}
                                            <td class="px-6 py-5 text-center">

                                                <a href="{{ route('tenants.leases.show', $lease->id) }}"
                                                   class="inline-flex items-center justify-center
                                                          px-3 py-2
                                                          text-xs font-bold
                                                          text-indigo-700
                                                          bg-indigo-50
                                                          border border-indigo-200
                                                          rounded-lg
                                                          hover:bg-indigo-600
                                                          hover:text-white
                                                          transition-all">

                                                    View Details

                                                    <svg class="w-3.5 h-3.5 ml-1.5"
                                                         fill="none"
                                                         stroke="currentColor"
                                                         viewBox="0 0 24 24">

                                                        <path stroke-linecap="round"
                                                              stroke-linejoin="round"
                                                              stroke-width="2"
                                                              d="M9 5l7 7-7 7" />

                                                    </svg>

                                                </a>

                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>
                        </div>

                    </div>

                </div>


            @else

                {{-- =====================================================
                    Empty State
                ====================================================== --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 sm:p-12 text-center">

                    <div class="w-12 h-12 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">

                        <svg class="w-6 h-6 text-gray-400"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">

                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0
                                     012-2h5.586a1 1 0 01.707.293l5.414
                                     5.414a1 1 0 01.293.707V19a2 2 0
                                     01-2 2z" />

                        </svg>

                    </div>

                    <h3 class="text-lg font-bold text-slate-900">
                        No Lease Found
                    </h3>

                    <p class="text-sm text-gray-500 mt-2">
                        You currently do not have an active lease.
                    </p>

                </div>

            @endif

        </div>
    </div>
</x-app-layout>