<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Lease Details') }}
        </h2>
    </x-slot>

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
    | Property Address
    |--------------------------------------------------------------------------
    */
    $propertyAddress = $lease->leasable?->full_address ?? 'N/A';

    /*
    |--------------------------------------------------------------------------
    | Owner
    |--------------------------------------------------------------------------
    */
    if ($type === 'Room') {
    $ownerName = $lease->leasable?->unit?->owner?->name ?? 'N/A';
    $ownerIc = $lease->leasable?->unit?->owner?->owner?->ic_number ?? 'N/A';
    } else {
    $ownerName = $lease->leasable?->owner?->name ?? 'N/A';
    $ownerIc = $lease->leasable?->owner?->owner?->ic_number ?? 'N/A';
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

    $securityDepositAmount = $lease->charges
    ->filter(function ($charge) {
    return strtolower($charge->feeType?->name ?? '') === 'security deposit';
    })
    ->sum('amount');

    $utilitiesDepositAmount = $lease->charges
    ->filter(function ($charge) {
    return strtolower($charge->feeType?->name ?? '') === 'utilities deposit';
    })
    ->sum('amount');
    /*
    |--------------------------------------------------------------------------
    | Other Charges
    |--------------------------------------------------------------------------
    | 排除 Rent，避免 Rental Amount 和 Additional Charges 重复显示
    */
    $otherCharges = $lease->charges
    ->filter(function ($charge) {
    $category = strtolower(
    $charge->feeType?->category?->value ?? ''
    );

    $name = strtolower(
    $charge->feeType?->name ?? ''
    );

    return $category !== 'rent'
    && $name !== 'security deposit'
    && $name !== 'utilities deposit';
    });

    /*
    |--------------------------------------------------------------------------
    | Status
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


    <div class="min-h-screen bg-gray-50 py-6 sm:py-10">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">


            {{-- =========================================================
                Back Button
            ========================================================= --}}
            <div class="mb-5">

                <a href="{{ route('tenants.leases.index') }}"
                    class="inline-flex items-center text-sm font-semibold
                          text-indigo-600 hover:text-indigo-700">

                    <svg class="w-4 h-4 mr-1"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24">

                        <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 19l-7-7 7-7" />

                    </svg>

                    Back to My Leases

                </a>

            </div>


            {{-- =========================================================
                Lease Header
            ========================================================= --}}
            <div class="bg-white rounded-2xl sm:rounded-xl
                        shadow-sm border border-gray-200
                        p-5 sm:p-6 mb-6">

                <div class="flex flex-col sm:flex-row
                            sm:items-center sm:justify-between
                            gap-4">

                    <div>

                        <p class="text-xs font-bold uppercase
                                  tracking-wider text-gray-400">

                            {{ $type }}

                        </p>

                        <h1 class="text-2xl sm:text-3xl
                                   font-bold text-slate-900 mt-1">

                            {{ $propertyName }}

                        </h1>

                        <p class="text-sm text-gray-500 mt-2">
                            Your current tenancy information and agreement details.
                        </p>

                    </div>


                    {{-- Status --}}
                    <div class="self-start sm:self-center">

                        <span class="inline-flex items-center
                                     px-3 py-1.5
                                     rounded-full
                                     text-xs font-bold border
                                     {{ $statusClass }}">

                            {{ $lease->status ?? 'N/A' }}

                        </span>

                    </div>

                </div>

            </div>


            {{-- =========================================================
                Main Information Grid
            ========================================================= --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">


                {{-- =====================================================
                    Lease Term
                ====================================================== --}}
                <div class="bg-white rounded-2xl sm:rounded-xl
                            shadow-sm border border-gray-200 p-5">

                    <div class="flex items-center gap-3 mb-5">

                        <div class="w-10 h-10 rounded-xl bg-indigo-50
                                    flex items-center justify-center">

                            <svg class="w-5 h-5 text-indigo-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24">

                                <path stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8 7V3m8 4V3M5 11h14M5 5h14
                                         a2 2 0 012 2v12a2 2 0 01-2 2H5
                                         a2 2 0 01-2-2V7a2 2 0 012-2z" />

                            </svg>

                        </div>

                        <div>
                            <h2 class="text-sm font-bold text-slate-900">
                                Lease Term
                            </h2>

                            <p class="text-xs text-gray-400">
                                Tenancy duration
                            </p>
                        </div>

                    </div>


                    <div class="space-y-4">

                        <div>
                            <p class="text-xs font-semibold uppercase
                                      tracking-wide text-gray-400">
                                Start Date
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ $lease->start_date_formatted ?? '-' }}
                            </p>
                        </div>


                        <div>
                            <p class="text-xs font-semibold uppercase
                                      tracking-wide text-gray-400">
                                End Date
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ $lease->end_date_formatted ?? '-' }}
                            </p>
                        </div>


                        <div>
                            <p class="text-xs font-semibold uppercase
                                      tracking-wide text-gray-400">
                                Term Type
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ strtoupper($lease->term_type ?? 'N/A') }}
                            </p>
                        </div>

                    </div>

                </div>


                {{-- =====================================================
                    Financial Information
                ====================================================== --}}
                <div class="bg-white rounded-2xl sm:rounded-xl
                            shadow-sm border border-gray-200 p-5">

                    <div class="flex items-center gap-3 mb-5">

                        <div class="w-10 h-10 rounded-xl bg-emerald-50
                                    flex items-center justify-center">

                            <svg class="w-5 h-5 text-emerald-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24">

                                <path stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343
                                         2 3 2 3 .895 3 2-1.343 2-3
                                         2m0-8c1.11 0 2.08.402 2.599
                                         1M12 8V7m0 1v8m0 0v1m0-1
                                         c-1.11 0-2.08-.402-2.599-1
                                         M21 12a9 9 0 11-18 0
                                         9 9 0 0118 0z" />

                            </svg>

                        </div>

                        <div>
                            <h2 class="text-sm font-bold text-slate-900">
                                Financial Information
                            </h2>

                            <p class="text-xs text-gray-400">
                                Rental and deposit details
                            </p>
                        </div>

                    </div>


                    <div class="space-y-4">

                        {{-- Rental Amount --}}
                        <div>
                            <p class="text-xs font-semibold uppercase
                                      tracking-wide text-gray-400">

                                Rental Amount

                            </p>

                            <p class="mt-1 text-xl font-bold text-emerald-600">

                                RM {{ number_format($rentAmount / 100, 2) }}

                            </p>
                        </div>


                        {{-- Security Deposit --}}
                        <div>
                            <p class="text-xs font-semibold uppercase
                                      tracking-wide text-gray-400">

                                Security Deposit

                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                RM {{ number_format($securityDepositAmount / 100, 2) }}
                            </p>
                        </div>


                        {{-- Utilities Deposit --}}
                        <div>
                            <p class="text-xs font-semibold uppercase
                                      tracking-wide text-gray-400">

                                Utilities Deposit

                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                RM {{ number_format($utilitiesDepositAmount / 100, 2) }}
                            </p>
                        </div>

                    </div>

                </div>


                {{-- =====================================================
                    Owner / Property
                ====================================================== --}}
                <div class="bg-white rounded-2xl sm:rounded-xl
                            shadow-sm border border-gray-200 p-5">

                    <div class="flex items-center gap-3 mb-5">

                        <div class="w-10 h-10 rounded-xl bg-amber-50
                                    flex items-center justify-center">

                            <svg class="w-5 h-5 text-amber-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24">

                                <path stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3 21h18M5 21V7l7-4 7 4v14
                                         M9 21v-6h6v6" />

                            </svg>

                        </div>

                        <div>
                            <h2 class="text-sm font-bold text-slate-900">
                                Property Information
                            </h2>

                            <p class="text-xs text-gray-400">
                                Property and owner details
                            </p>
                        </div>

                    </div>


                    <div class="space-y-4">

                        <div>
                            <p class="text-xs font-semibold uppercase
                                      tracking-wide text-gray-400">
                                Owner
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ $ownerName }}
                            </p>
                        </div>


                        <div>
                            <p class="text-xs font-semibold uppercase
                                      tracking-wide text-gray-400">
                                Property Type
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ $type }}
                            </p>
                        </div>


                        <div>
                            <p class="text-xs font-semibold uppercase
                                      tracking-wide text-gray-400">
                                Address
                            </p>

                            <p class="mt-1 text-sm font-medium
                                      text-slate-700 leading-relaxed">

                                {{ $propertyAddress }}

                            </p>
                        </div>

                    </div>

                </div>

            </div>


            {{-- =========================================================
                Additional Charges
            ========================================================= --}}
            <div class="bg-white rounded-2xl sm:rounded-xl
                        shadow-sm border border-gray-200
                        overflow-hidden mb-6">

                <div class="px-5 sm:px-6 py-4 border-b border-gray-100">

                    <h2 class="text-base font-bold text-slate-900">
                        Additional Charges
                    </h2>

                    <p class="text-xs text-gray-500 mt-1">
                        Other charges related to this lease.
                    </p>

                </div>


                @if($otherCharges->count() > 0)

                <div class="divide-y divide-gray-100">

                    @foreach($otherCharges as $charge)

                    <div class="px-5 sm:px-6 py-4
                                        flex items-center justify-between gap-4">

                        <div class="min-w-0">

                            <p class="text-sm font-semibold
                                              text-slate-900">

                                {{ $charge->description
                                            ?? $charge->feeType?->name
                                            ?? 'Charge' }}

                            </p>

                            @if($charge->next_billing_date)

                            <p class="text-xs text-gray-400 mt-1">

                                Next Billing:
                                {{ $charge->next_billing_date->format('d/m/Y') }}

                            </p>

                            @endif

                        </div>


                        <p class="shrink-0 text-sm font-bold
                                          text-slate-900">

                            RM {{ number_format(($charge->amount ?? 0) / 100, 2) }}

                        </p>

                    </div>

                    @endforeach

                </div>

                @else

                <div class="px-6 py-8 text-center">

                    <p class="text-sm text-gray-400">
                        No additional charges.
                    </p>

                </div>

                @endif

            </div>


            {{-- =========================================================
                Stamping / Agreement
            ========================================================= --}}
            <div class="bg-white rounded-2xl sm:rounded-xl
                        shadow-sm border border-gray-200 p-5 sm:p-6">

                <div class="flex flex-col sm:flex-row
                            sm:items-center sm:justify-between
                            gap-5">

                    <div>

                        <h2 class="text-base font-bold text-slate-900">
                            Agreement & Stamping
                        </h2>

                        <div class="mt-3 flex items-center gap-2">

                            @if($lease->stamping_status)

                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>

                            <span class="text-sm font-semibold text-emerald-700">
                                Stamped
                            </span>

                            @if($lease->stamping_reference_no)

                            <span class="text-xs text-gray-400">
                                ({{ $lease->stamping_reference_no }})
                            </span>

                            @endif

                            @else

                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>

                            <span class="text-sm font-semibold text-amber-700">
                                Not Stamped
                            </span>

                            @endif

                        </div>

                    </div>


                    {{-- View Agreement --}}
                    @if(!empty($lease->document_id) && $lease->documentTemplate)

                    <button type="button"
                        data-base-content="{{ $lease->documentTemplate?->html_template }}"
                        data-title="{{ $lease->documentTemplate?->title }}"
                        data-replacements="{{ json_encode([
                                    'status' => $lease->status ?? 'N/A',
                                    'tenant_name' => $lease->tenant?->user?->name ?? 'N/A',
                                    'tenant_ic' => $lease->tenant?->ic_number ?? 'N/A',
                                    'owner_name' => $ownerName,
                                    'owner_ic' => $ownerIc,
                                    'property_address' => $propertyAddress,
                                    'property_type' => $type,
                                    'property_name' => $propertyName,
                                    'rent_mode' => $lease->term_type ?? 'N/A',
                                    'rent_price' => number_format($rentAmount / 100, 2),
                                    'security_deposit' => number_format(($lease->security_deposit ?? 0) / 100, 2),
                                    'utilities_deposit' => number_format(($lease->utilities_deposit ?? 0) / 100, 2),
                                    'start_date' => $lease->start_date?->format('d/m/Y') ?? 'N/A',
                                    'end_date' => $lease->end_date?->format('d/m/Y') ?? 'N/A',
                                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }}"

                        onclick="viewTenantAgreement(this)"

                        class="inline-flex items-center justify-center
                                       px-4 py-2.5
                                       bg-indigo-600 hover:bg-indigo-700
                                       text-white text-sm font-bold
                                       rounded-xl transition-colors">

                        View Agreement

                        <svg class="w-4 h-4 ml-2"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24">

                            <path stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 5l7 7-7 7" />

                        </svg>

                    </button>

                    @else

                    <span class="text-sm text-gray-400">
                        Agreement unavailable
                    </span>

                    @endif

                </div>

            </div>

        </div>

    </div>


    {{-- Existing Agreement Preview Modal --}}
    <x-preview-agreement-modal />


    <script>
        window.viewTenantAgreement = function(button) {

            let content = button.dataset.baseContent;

            if (!content) {
                console.error('Agreement content is empty');
                return;
            }

            const replacements = JSON.parse(
                button.dataset.replacements
            );

            Object.keys(replacements).forEach(key => {

                const val = replacements[key] || 'N/A';

                const safeVal = `<strong>${val}</strong>`;

                /*
                 * Replace GrapesJS data-variable elements
                 */
                const dataVarRegex = new RegExp(
                    `<[^>]+data-variable=["']${key}["'][^>]*>[\\s\\S]*?<\\/\\w+>`,
                    'gi'
                );

                content = content.replace(
                    dataVarRegex,
                    safeVal
                );


                /*
                 * Replace  style placeholders
                 */
                const textRegex = new RegExp(
                    `(?:\\{|&#123;|&lcub;){1,2}` +
                    `(?:[\\s\\u200B\\u200C\\u200D\\uFEFF&nbsp;]|<[^>]*>)*` +
                    `${key}` +
                    `(?:[\\s\\u200B\\u200C\\u200D\\uFEFF&nbsp;]|<[^>]*>)*` +
                    `(?:\\}|&#125;|&rcub;){1,2}`,
                    'gi'
                );

                content = content.replace(
                    textRegex,
                    safeVal
                );

            });


            window.dispatchEvent(
                new CustomEvent(
                    'open-lease-preview', {
                        detail: {
                            content: content,
                            title: button.dataset.title
                        }
                    }
                )
            );
        };
    </script>

</x-app-layout>