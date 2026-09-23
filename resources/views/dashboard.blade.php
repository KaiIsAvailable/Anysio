<x-app-layout>
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
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-900 text-base uppercase tracking-wider">Leases Needing Attention</h3>
                            <span class="text-xs bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full font-semibold">
                                {{ isset($pendingOrEndedLeases) ? $pendingOrEndedLeases->total() : 0 }} Total
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-100 text-[11px] uppercase tracking-wider font-bold text-slate-400">
                                        <th class="py-3 px-4">Tenant / Property</th>
                                        <th class="py-3 px-4">Phone Number</th>
                                        <th class="py-3 px-4">End Date</th>
                                        <th class="py-3 px-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 text-sm">
                                    @forelse($pendingOrEndedLeases ?? [] as $lease)
                                        <tr class="hover:bg-slate-50/50 transition">
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
                            
                            @if($pendingOrEndedLeases->hasPages())
                                <div class="px-4 py-3 border-t border-slate-100">
                                    {{ $pendingOrEndedLeases->links() }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="lg:col-span-2 space-y-6" id="overdue-section">
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col">
                            
                            <!-- Header (Fixed) -->
                            <div class="flex justify-between items-center mb-4 shrink-0">
                                <h3 class="font-bold text-slate-900 text-base uppercase tracking-wider">Overdue Invoices</h3>
                                <span class="text-xs bg-rose-50 text-rose-600 px-2.5 py-1 rounded-full font-semibold">
                                    {{ isset($overdueInvoices) ? $overdueInvoices->total() : 0 }} Total
                                </span>
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
                                        </tr>
                                    </thead>

                                    <!-- Table Body -->
                                    <tbody class="divide-y divide-slate-50">
                                        @forelse($overdueInvoices ?? [] as $invoice)
                                            <tr class="hover:bg-slate-50/50 transition">
                                                <!-- Tenant & Invoice Info -->
                                                <td class="py-3 px-2">
                                                    <p class="font-bold text-slate-800 text-sm">
                                                        {{ $invoice->lease?->tenant?->user?->name ?? ($invoice->lease?->tenant?->name ?? 'N/A') }}
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
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT SIDE: Property, Unit, Room Stats & Charts (Takes up 1 column) -->
                <div class="space-y-6">
                    @foreach([
                        ['title' => 'Properties', 'total' => $counts['total_properties'] ?? 0, 'vacant' =>$counts['vacant_properties'] ?? 0, 'occ' => $counts['occ_properties'] ?? 0, 'main' =>$counts['main_properties'] ?? 0, 'clean' => $counts['clean_properties'] ?? 0],                         
                        ['title' => 'Units',      'total' =>$counts['total_units'] ?? 0,      'vacant' => $counts['vacant_units'] ?? 0,      'occ' =>$counts['occ_units'] ?? 0,      'main' => $counts['main_units'] ?? 0,      'clean' =>$counts['clean_units'] ?? 0],
                        ['title' => 'Rooms',      'total' => $counts['total_rooms'] ?? 0,      'vacant' =>$counts['vacant_rooms'] ?? 0,      'occ' => $counts['occ_rooms'] ?? 0,      'main' =>$counts['main_rooms'] ?? 0,      'clean' => $counts['clean_rooms'] ?? 0],                     
                    ] as $stat)

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
</x-app-layout>