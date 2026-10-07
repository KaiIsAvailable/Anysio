<x-app-layout>
    <div x-data="{ 
        loading: false,
        openPayment: false, 
        shakePayment: false,
        paymentData: { id: '', invoiceNo: '', dueDate: '', totalAmount: 0, invoiceItems: [], walletBalance: 0, actionUrl: '' },
        voidModalOpen: false, 
        actionUrl: ''
    }"
    @open-payment.window="paymentData = $event.detail; openPayment = true;">

        <x-slot name="header">
            <div class="flex justify-between items-center">            
                <!-- Left Side: Title -->
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Dashboard') }}
                </h2>

                <!-- Right Side: Action Buttons (Restricted to Super Admin) -->
                @can('super-admin')
                    <div class="flex items-center space-x-3">
                        <button type="button" @click="console.log('Button clicked'); window.dispatchEvent(new CustomEvent('open-seeder-modal'));"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150">
                            Run Seeders
                        </button>

                        <a href="{{ route('run.migrations') . '?redirect=' . urlencode(request()->url()) }}" 
                        class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                            Run Migrations
                        </a>
                    </div>
                @endcan

                <x-modals.confirmation-modal id="seeder-modal" title="Run Database Seeders">
                    <div x-data="{ loading: false }">
                        <x-form.form action="{{ route('run.seeders') }}" method="GET" loading="loading">
                            <input type="hidden" name="redirect" value="{{ request()->url() }}">

                            <!-- Choose Specific Seeder -->
                            <div class="mb-4 p-6">
                                <x-form.input-label value="Choose Specific Seeder" class="mb-1" />
                                <x-form.input-select 
                                    name="seeder" 
                                    :options="$seeders" 
                                    maxHeight="max-h-20"
                                />
                            </div>

                            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 mt-4 flex justify-end space-x-3">
                                <button type="button" @click="$dispatch('close-seeder-modal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-xs font-semibold uppercase">
                                    Cancel
                                </button>
                                <x-form.primary-button type="submit" loading="loading" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-xs font-semibold uppercase hover:bg-indigo-700">
                                    Confirm & Run
                                </x-form.primary-button>
                            </div>
                        </x-form.form>
                    </div>
                </x-modals.confirmation-modal>
            </div>
        </x-slot>

        <div class="py-12 bg-gray-50 min-h-screen">

            @php
                $isSetupComplete = !in_array(false,$checks);
            @endphp

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                @if(!$isSetupComplete)
                    <x-notification-banner type="warning" class="mb-6">
                        <span class="font-bold">Getting Started:</span> Please complete the following to create your first lease:
                        <ul class="mt-2 list-disc list-inside px-4">
                            @if(!$checks['tenant'])   <li><a href="{{ route('admin.tenants.create') }}">Add your first tenant</a></li> @endif
                            @if(!$checks['owner'])   <li><a href="{{ route('admin.owners.create') }}">Add your first owner</a></li> @endif
                            @if(!$checks['property']) <li><a href="{{ route('admin.properties.create') }}">Add your first property</a></li> @endif
                            @if(!$checks['asset'])    <li><a href="{{ route('admin.roomAsset.create') }}">Add your first asset</a></li> @endif
                            @if(!$checks['template']) <li><a href="{{ route('admin.document-templates.create') }}">Setup agreement template</a></li> @endif
                        </ul>
                    </x-notification-banner>
                @endif

                <!-- Main Layout Grid: Left (Leases needing attention) | Right (Stats / Charts) -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8" id="lease-section">

                    <!-- LEFT SIDE: Pending Renewal & Ended Leases List (Takes up 2 columns on large screens) -->
                    <div class="lg:col-span-2 space-y-6">
                        @canany(['owner-admin', 'dashboard.lease list'])
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                                <h3 class="font-bold text-slate-900 text-base uppercase tracking-wider">Leases Needing Attention</h3>
                                
                                <div class="flex items-center gap-3">
                                    {{-- Month Filter Form --}}
                                    <form id="leaseFilterForm" method="GET" action="{{ url()->current() }}" class="flex items-center gap-2">
                                        {{-- Preserve other query parameters except lease_month so the page doesn't break other filters --}}
                                        @foreach(request()->except(['lease_month', 'lease_page']) as $key => $value)
                                            @if(is_array($value))
                                                @foreach($value as $v)
                                                    <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                                                @endforeach
                                            @else
                                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                            @endif
                                        @endforeach

                                        <input type="hidden" name="lease_year" value="{{ request('lease_year', date('Y')) }}">

                                        @php
                                            // Start with "All Months" as the first option (with an empty value)
                                            $monthOptions = [
                                                '' => 'All Months'
                                            ];

                                            // Append the 12 months
                                            for ($m = 1; $m <= 12; $m++) {
                                                $monthValue = str_pad($m, 2, '0', STR_PAD_LEFT);
                                                $monthName = date('F', mktime(0, 0, 0, $m, 1));
                                                $monthOptions[$monthValue] = $monthName;
                                            }
                                        @endphp

                                        <div class="w-44">
                                            <x-form.input-select 
                                                name="lease_month" 
                                                id="lease_month_filter"
                                                :options="$monthOptions"
                                                :value="request('lease_month', '')"
                                                placeholder="All Months"
                                                @change="document.getElementById('leaseFilterForm').submit()"
                                            />
                                        </div>
                                    </form>

                                    <span class="text-xs bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full font-semibold whitespace-nowrap">
                                        {{ isset($pendingOrEndedLeases) ? $pendingOrEndedLeases->total() : 0 }} Total
                                    </span>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="border-b border-slate-100 text-[11px] uppercase tracking-wider font-bold text-slate-400">
                                            <th class="py-3 px-4">Tenant / Property</th>
                                            <th class="py-3 px-4">Phone Number</th>
                                            <th class="py-3 px-4">End Date</th>
                                            <th class="py-3 px-4">Status</th>
                                            @canany(['owner-admin', 'leases.renew lease', 'leases.check out lease'])
                                            <th class="py-3 px-4">Actions</th>
                                            @endcanany
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50 text-sm">
                                        @forelse($pendingOrEndedLeases ?? [] as $lease)
                                            <tr class="hover:!bg-indigo-50 transition-colors cursor-pointer group duration-150"
                                                @canany(['owner-admin', 'leases.show'])
                                                onclick="window.location='{{ route('admin.leases.show', $lease->id) }}'"
                                                @endcanany
                                                >
                                                <td class="py-3 px-4">
                                                    <p class="font-bold text-slate-800">{{ $lease->tenant->user->name ?? 'N/A' }}</p>
                                                    <p class="text-xs text-slate-400">
                                                        @php
                                                            $leasable = $lease->leasable;
                                                            $locationName = 'Property/Unit';
                                                            
                                                            if ($leasable instanceof \App\Models\Property) {
                                                                $locationName = $leasable->name;
                                                            } elseif ($leasable instanceof \App\Models\Unit) {
                                                                $locationName = $leasable->name ?? $leasable->unit_no;
                                                            } elseif ($leasable instanceof \App\Models\Room) {
                                                                $locationName = 'Property: ' . ($leasable->unit->property->name ?? '') . ' - Room: ' . ($leasable->room_no ?? '');
                                                            }
                                                        @endphp
                                                        {{ $locationName }}
                                                    </p>
                                                </td>
                                                <td class="py-3 px-4">
                                                    {{ $lease->tenant->phone ?? 'N/A' }}
                                                </td>
                                                <td class="py-3 px-4 text-slate-600 font-medium">
                                                    {{ \Carbon\Carbon::parse($lease->end_date)->format('d/m/Y') }}
                                                </td>
                                                <td class="py-3 px-4">
                                                    @if($lease->status === 'End')
                                                        <span class="px-2.5 py-1 text-[10px] font-bold uppercase bg-rose-50 text-rose-600 rounded-full">Ended</span>
                                                    @elseif($lease->is_pending_renewal)
                                                        <span class="px-2.5 py-1 text-[10px] font-bold uppercase bg-amber-50 text-amber-600 rounded-full">{{ $lease->status }} (Pending Renewal)</span>
                                                    @else
                                                        <span class="px-2.5 py-1 text-[10px] font-bold uppercase bg-slate-100 text-slate-600 rounded-full">{{ $lease->status }}</span>
                                                    @endif
                                                </td>
                                                @canany(['owner-admin', 'leases.renew lease', 'leases.check out lease'])
                                                <td class="py-3 px-4" @click.stop>
                                                    <div class="flex flex-col gap-1.5 w-full">
                                                        {{-- Renew Button (Emerald Theme) --}}
                                                        @canany(['owner-admin', 'leases.renew lease'])
                                                        @if(!in_array(strtolower($lease->status), ['cancelled', 'check out', 'end agreement']))
                                                            <button type="button" 
                                                                @click.stop="window.dispatchEvent(new CustomEvent('open-lease-modal', { detail: { status: 'Renew', leaseId: '{{ $lease->id }}' } }))"
                                                                class="px-3 py-1.5 bg-emerald-50 text-emerald-600 text-xs font-black rounded-lg border border-emerald-100 hover:bg-emerald-600 hover:text-white transition-all shadow-sm flex items-center justify-center">
                                                                RENEW LEASE
                                                            </button>
                                                        @endif
                                                        @endcanany

                                                        {{-- Check Out Button (Amber Theme) --}}
                                                        @canany(['owner-admin', 'leases.check out lease'])
                                                        @if(!in_array(strtolower($lease->status), ['cancelled', 'check out', 'end agreement']))
                                                            <button type="button" 
                                                                @click.stop="window.dispatchEvent(new CustomEvent('open-lease-modal', { detail: { status: 'Check Out', leaseId: '{{ $lease->id }}' } }))"
                                                                class="px-3 py-1.5 bg-amber-50 text-amber-600 text-xs font-black rounded-lg border border-amber-100 hover:bg-amber-600 hover:text-white transition-all shadow-sm flex items-center justify-center whitespace-nowrap">
                                                                CHECK OUT LEASE
                                                            </button>
                                                        @endif
                                                        @endcanany
                                                    </div>
                                                </td>
                                                @endcanany
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="py-8 text-center text-slate-400 text-sm font-medium">
                                                    No leases require immediate attention. Great job!
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>

                                <x-modals.lease-create-modal 
                                    :tenants="$tenants" 
                                    :leases="$modalLeases" {{-- Use unfiltered modal leases here --}}
                                    :properties="$properties" 
                                    :units="$units" 
                                    :rooms="$rooms" 
                                    :templates="$templates" 
                                    :rentFeeTypes="$rentFeeTypes" 
                                    :serviceFeeTypes="$serviceFeeTypes" 
                                    :depositFeeTypes="$depositFeeTypes" 
                                    :managementFeeTypes="$managementFeeTypes" 
                                    :leasePreviewData="$leasePreviewData" 
                                />
                                
                                @if($pendingOrEndedLeases->hasPages())
                                    <div class="px-4 py-3 border-t border-slate-100">
                                        {{ $pendingOrEndedLeases->links() }}
                                    </div>
                                @endif
                            </div>
                        </div>
                        @endcanany

                        @canany(['owner-admin', 'dashboard.invoice list'])
                        <div class="lg:col-span-2 space-y-6" id="overdue-section">
                            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col">
                                
                                <!-- Header (Fixed) -->
                                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4 shrink-0">
                                    <h3 class="font-bold text-slate-900 text-base uppercase tracking-wider">Overdue Invoices</h3>
                                    
                                    <div class="flex items-center gap-3">
                                        {{-- Invoice Month Filter Form --}}
                                        <form id="invoiceFilterForm" method="GET" action="{{ url()->current() }}#overdue-section" class="flex items-center gap-2">
                                            {{-- Preserve other query parameters except invoice_month and invoice_page --}}
                                            @if(request()->except(['invoice_month', 'invoice_page']))
                                                @foreach(request()->except(['invoice_month', 'invoice_page']) as $key => $value)
                                                    @if(is_array($value))
                                                        @foreach($value as $v)
                                                            <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                                                        @endforeach
                                                    @else
                                                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                                    @endif
                                                @endforeach
                                            @endif

                                            <input type="hidden" name="invoice_year" value="{{ request('invoice_year', date('Y')) }}">

                                            @php
                                                $invoiceMonthOptions = [
                                                    '' => 'All Months'
                                                ];

                                                for ($m = 1; $m <= 12; $m++) {
                                                    $monthValue = str_pad($m, 2, '0', STR_PAD_LEFT);
                                                    $monthName = date('F', mktime(0, 0, 0, $m, 1));
                                                    $invoiceMonthOptions[$monthValue] = $monthName;
                                                }
                                            @endphp

                                            <div class="w-44">
                                                <x-form.input-select 
                                                    name="invoice_month" 
                                                    id="invoice_month_filter"
                                                    :options="$invoiceMonthOptions"
                                                    :value="request('invoice_month', '')"
                                                    placeholder="All Months"
                                                    @change="document.getElementById('invoiceFilterForm').submit()"
                                                />
                                            </div>
                                        </form>

                                        <span class="text-xs bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full font-semibold whitespace-nowrap">
                                            {{ isset($overdueInvoices) ? $overdueInvoices->total() : 0 }} Total
                                        </span>
                                    </div>
                                </div>

                                <!-- Content Area (Scrollable / Flexible Container) -->
                                <div class="flex-1 overflow-y-auto min-h-0">
                                    <table class="w-full text-left border-collapse">
                                        <!-- Table Headers -->
                                        <thead class="sticky top-0 bg-white border-b border-slate-100 text-slate-400 uppercase text-[10px] tracking-wider z-10">
                                            <tr>
                                                <th class="py-2.5 px-2 font-bold">Tenant / Invoice</th>
                                                <th class="py-2.5 px-2 font-bold">Amount</th>
                                                <th class="py-2.5 px-2 font-bold">Due Date / Status</th>
                                                <th class="py-2.5 px-2 font-bold">Actions</th>
                                            </tr>
                                        </thead>

                                        <!-- Table Body -->
                                        <tbody class="divide-y divide-slate-50">
                                            @forelse($overdueInvoices ?? [] as $invoice)
                                                <tr class="hover:bg-slate-50/50 transition">
                                                    <!-- Tenant & Invoice Info -->
                                                    <td class="py-3 px-2">
                                                        <p class="font-bold text-slate-800 text-sm">
                                                            {{ $invoice->recipient_name }}
                                                        </p>
                                                        <p class="text-xs text-slate-400">
                                                            Invoice #{{ $invoice->invoice_no ?? $invoice->id }}
                                                        </p>
                                                    </td>

                                                    <!-- Amount -->
                                                    <td class="py-3 px-2 text-slate-700 font-semibold text-sm">
                                                        RM {{ number_format($invoice->total_amount / 100 ?? $invoice->amount / 100 ?? 0, 2) }}
                                                    </td>

                                                    <!-- Due Date & Status Badge -->
                                                    <td class="py-3 px-2">
                                                        <span class="text-xs text-rose-600 font-medium block">
                                                            Due: {{ \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y') }}
                                                        </span>
                                                        <span class="px-2 py-0.5 text-[10px] font-bold uppercase bg-rose-50 text-rose-600 rounded-full inline-block mt-0.5">
                                                            Unpaid
                                                        </span>
                                                    </td>

                                                    <!-- Actions -->
                                                    @canany(['owner-admin', 'invoice.record payment', 'invoice.void'])
                                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                                        <div class="flex justify-center items-center gap-2">
                                                            @canany(['owner-admin', 'invoice.record payment'])
                                                            @if(in_array($invoice->status, ['unpaid', 'partial']))
                                                            @php
                                                            $paymentPayload = json_encode([
                                                                'id' => $invoice->id,
                                                                'invoiceNo' => $invoice->invoice_no,
                                                                'dueDate' => $invoice->due_date,
                                                                'totalAmount' => number_format($invoice->amount_balance / 100, 2),
                                                                'invoiceItems' => $invoice->invoice_items,
                                                                'walletBalance' => $invoice->wallet_balance,
                                                                'actionUrl' => route('admin.invoices.payment', $invoice->id)
                                                            ]);
                                                            @endphp
                                                            <button type="button"
                                                                @click="$dispatch('open-payment', {{ $paymentPayload }})"
                                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200/60 rounded-lg transition-all shadow-sm">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                                                </svg>
                                                                <span>Record Payment</span>
                                                            </button>
                                                            @endif
                                                            @endcanany

                                                            <!-- Void Button (Fixed Blade Conditional instead of Alpine x-if on server loop) -->
                                                            @canany(['owner-admin', 'invoice.void'])
                                                            @if(!in_array($invoice->status, ['void']))
                                                            <button type="button"
                                                                @click="
                                                                        $dispatch('open-void-modal', { actionUrl: '{{ route('admin.invoices.void', $invoice->id) }}', invoiceNumber: '{{ $invoice->invoice_no }}' });
                                                                    "
                                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200/60 rounded-lg transition-all shadow-sm">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                                                                </svg>
                                                                <span>Void</span>
                                                            </button>
                                                            @endif
                                                            @endcanany
                                                        </div>
                                                    </td>
                                                    @endcanany
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="py-16 text-center text-slate-400 text-sm font-medium">
                                                        <div class="flex flex-col items-center justify-center space-y-2">
                                                            <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                            </svg>
                                                            <span>No overdue invoices found. Excellent!</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                    @if(isset($overdueInvoices) && $overdueInvoices->hasPages())
                                        <div class="pt-3 border-t border-slate-100 mt-auto shrink-0">
                                            {{ $overdueInvoices->links() }}
                                        </div>
                                    @endif

                                    <x-modals.confirmation-modal id="void-modal" title="Void Invoice" maxWidth="sm:max-w-lg">
                                        <div x-data="{ actionUrl: '', invoiceNumber: '', loading: false }"
                                            @open-void-modal.window="
                                                actionUrl = $event.detail.actionUrl;
                                                invoiceNumber = $event.detail.invoiceNumber;
                                            ">

                                            <!-- Optional: Display invoice number in the modal content -->
                                            <x-form.form :action="''" x-bind:action="actionUrl" method="POST" loading="loading">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="redirect" value="{{ request()->url() }}">

                                                <div class="p-6 space-y-4">
                                                    <div class="flex items-center gap-3 text-amber-600 bg-amber-50 p-4 rounded-xl border border-amber-100 mb-4">
                                                        <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                        </svg>
                                                        <p class="text-sm text-slate-600 font-medium">
                                                            Are you sure you want to void invoice <span class="font-bold text-slate-900" x-text="invoiceNumber"></span>? This action cannot be undone.
                                                        </p>
                                                    </div>

                                                    <div>
                                                        <x-form.input-label value="Reason for Voiding" class="mb-1" />
                                                        <textarea name="reason" rows="3" required
                                                            class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                                                    </div>
                                                </div>

                                                <div class="px-6 py-4 bg-gray-50/50 border-t border-gray-100 flex justify-end space-x-3">
                                                    <button type="button" @click="$dispatch('close-void-modal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-xs font-semibold uppercase hover:bg-gray-300 transition-colors">
                                                        Cancel
                                                    </button>
                                                    <x-form.primary-button type="submit" loading="loading" class="px-4 py-2 bg-rose-600 text-white rounded-md text-xs font-semibold uppercase hover:bg-rose-700">
                                                        Confirm Void
                                                    </x-form.primary-button>
                                                </div>
                                            </x-form.form>
                                        </div>
                                    </x-modals.confirmation-modal>

                                    <x-modals.payment-modal />
                                </div>
                            </div>
                        </div>
                        @endcanany
                    </div>

                    <!-- RIGHT SIDE: Property, Unit, Room Stats & Charts (Takes up 1 column) -->
                    <div class="space-y-6">
                        @foreach([
                            ['title' => 'Properties', 'permission' => 'dashboard.property analysis', 'total' => $counts['total_properties'] ?? 0, 'vacant' =>$counts['vacant_properties'] ?? 0, 'occ' => $counts['occ_properties'] ?? 0, 'main' =>$counts['main_properties'] ?? 0, 'clean' => $counts['clean_properties'] ?? 0],                         
                            ['title' => 'Units',      'permission' => 'dashboard.unit analysis', 'total' =>$counts['total_units'] ?? 0,      'vacant' => $counts['vacant_units'] ?? 0,      'occ' =>$counts['occ_units'] ?? 0,      'main' => $counts['main_units'] ?? 0,      'clean' =>$counts['clean_units'] ?? 0],
                            ['title' => 'Rooms',      'permission' => 'dashboard.room analysis', 'total' => $counts['total_rooms'] ?? 0,      'vacant' =>$counts['vacant_rooms'] ?? 0,      'occ' => $counts['occ_rooms'] ?? 0,      'main' =>$counts['main_rooms'] ?? 0,      'clean' => $counts['clean_rooms'] ?? 0],                     
                        ] as $stat)

                        @canany(['owner-admin', $stat['permission']])
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100" x-cloak
                            x-data="{ view: 'stats' }">
                            
                            <div class="flex justify-between items-center mb-6">
                                <h4 class="font-bold text-slate-900 uppercase tracking-wider text-sm">{{ $stat['title'] }}</h4>
                                <button @click="view = (view === 'stats' ? 'graph' : 'stats'); if(view === 'graph') initChart('{{ $stat['title'] }}')" 
                                        class="text-[10px] px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded font-bold text-slate-600 transition">
                                    <span x-text="view === 'stats' ? 'View Chart' : 'View Stats'"></span>
                                </button>
                            </div>

                            <div x-show="view === 'stats'" x-transition class="grid grid-cols-2 gap-4">
                                <div class="col-span-2 mb-2">
                                    <span class="text-xs text-slate-400">Total Count</span>
                                    <h3 class="text-3xl font-extrabold text-slate-900">{{ $stat['total'] }}</h3>
                                </div>
                                <div class="border-t pt-3">
                                    <span class="text-[10px] uppercase font-bold text-amber-500">Vacant</span>
                                    <p class="text-lg font-bold text-amber-600">{{ $stat['vacant'] }}</p>
                                </div>
                                <div class="border-t pt-3">
                                    <span class="text-[10px] uppercase font-bold text-emerald-500">Occupied</span>
                                    <p class="text-lg font-bold text-emerald-600">{{ $stat['occ'] }}</p>
                                </div>
                                <div class="border-t pt-3">
                                    <span class="text-[10px] uppercase font-bold text-purple-500">Cleaning</span>
                                    <p class="text-lg font-bold text-purple-600">{{ $stat['clean'] }}</p>
                                </div>
                                <div class="border-t pt-3">
                                    <span class="text-[10px] uppercase font-bold text-rose-500">Maintenance</span>
                                    <p class="text-lg font-bold text-rose-600">{{ $stat['main'] }}</p>
                                </div>
                            </div>

                            <div x-show="view === 'graph'" x-transition class="min-h-[220px]">
                                <div id="chart-{{ $stat['title'] }}"></div>
                            </div>
                        </div>
                        @endcanany
                        @endforeach
                    </div>

                </div>
            </div>

            <script>
                // 用于记录哪些图表已经渲染过
                const renderedCharts = {};

                function initChart(title) {
                    if (renderedCharts[title]) return;

                    const counts = @json($counts);
                    const element = document.querySelector("#chart-" + title);
                    
                    // 映射数据
                    let series = [];
                    if (title === 'Properties') series = [counts.occ_properties, counts.vacant_properties, counts.main_properties, counts.clean_properties];
                    else if (title === 'Units') series = [counts.occ_units, counts.vacant_units, counts.main_units, counts.clean_units];
                    else if (title === 'Rooms') series = [counts.occ_rooms, counts.vacant_rooms, counts.main_rooms, counts.clean_rooms];

                    // 将 null 或 undefined 转换为 0，防止 reduce 报错
                    const safeSeries = series.map(val => val || 0);
                    const hasData = safeSeries.some(value => value > 0);

                    if (!hasData) {
                        element.innerHTML = `
                            <div class="flex items-center justify-center w-full h-[220px]">
                                <span class="text-slate-400 text-sm font-bold uppercase tracking-widest">
                                    No Data Available
                                </span>
                            </div>
                        `;
                        renderedCharts[title] = true;
                        return;
                    }

                    const options = {
                        chart: { type: 'donut', height: 220, width: '100%' },
                        series: safeSeries,
                        labels: ['Occupied', 'Vacant', 'Cleaning', 'Maintenance'],
                        colors: ['#10b981', '#f59e0b', '#8b5cf6', '#ef4444'],
                        dataLabels: { enabled: false },
                        legend: { position: 'bottom', fontSize: '10px' }
                    };

                    const chart = new ApexCharts(element, options);
                    chart.render();
                    
                    renderedCharts[title] = true;
                }
            </script>
        </div>
    </div>
</x-app-layout>