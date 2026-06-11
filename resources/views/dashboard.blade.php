<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div x-data="{ openPayment: false }" class="py-12 bg-gray-50 min-h-screen">

        @php
            $isSetupComplete = !in_array(false, $checks);
        @endphp

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @if(!$isSetupComplete)
                <x-notification-banner type="warning" class="mb-6">
                    <span class="font-bold">Getting Started:</span> Please complete the following create your first lease:
                    <ul class="mt-2 list-disc list-inside px-4">
                        @if(!$checks['tenant'])   <li><a href="{{ route('admin.tenants.create') }}">Add your first tenant</a></li> @endif
                        @if(!$checks['owner'])   <li><a href="{{ route('admin.owners.create') }}">Add your first owner</a></li> @endif
                        @if(!$checks['property']) <li><a href="{{ route('admin.properties.create') }}">Add your first property</a></li> @endif
                        @if(!$checks['asset'])    <li><a href="{{ route('admin.roomAsset.create') }}">Add your first asset</a></li> @endif
                        @if(!$checks['template']) <li><a href="{{ route('admin.agreements.create') }}">Setup agreement template</a></li> @endif
                    </ul>
                </x-notification-banner>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                @foreach([
                    ['title' => 'Properties', 'total' => $counts['total_properties'] ?? 0, 'vacant' => $counts['vacant_properties'] ?? 0, 'occ' => $counts['occ_properties'] ?? 0, 'main' => $counts['main_properties'] ?? 0, 'clean' => $counts['clean_properties'] ?? 0],
                    ['title' => 'Units',      'total' => $counts['total_units'] ?? 0,      'vacant' => $counts['vacant_units'] ?? 0,      'occ' => $counts['occ_units'] ?? 0,      'main' => $counts['main_units'] ?? 0,      'clean' => $counts['clean_units'] ?? 0],
                    ['title' => 'Rooms',      'total' => $counts['total_rooms'] ?? 0,      'vacant' => $counts['vacant_rooms'] ?? 0,      'occ' => $counts['occ_rooms'] ?? 0,      'main' => $counts['main_rooms'] ?? 0,      'clean' => $counts['clean_rooms'] ?? 0],
                ] as $stat)

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100" 
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

           <div class="lg:col-span-1 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
            <h4 class="font-bold text-slate-900 mb-4">Overdue Rent Alerts</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="text-xs text-slate-400 uppercase">
                        <tr>
                            <th class="pb-3">Unit</th>
                            <th class="pb-3">Tenant</th>
                            <th class="pb-3">Amount</th>
                            <th class="pb-3">Due Date</th> </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($overduePayments as $payment)
                        <tr class="text-sm font-medium hover:bg-slate-50">
                            <td class="py-4">
                                {{ $payment->lease->leasableName ?? 'N/A' }}
                            </td>
                            
                            <td class="py-4">
                                {{ $payment->tenant->user->name ?? 'Unknown' }}
                            </td>
                            
                            <td class="py-4 text-rose-600 font-bold">
                                RM {{ number_format($payment->amount_due, 2) }}
                            </td>
                            
                            <td class="py-4 text-xs text-slate-500">
                                {{ $payment->periodDisplay }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="py-4 text-center text-slate-400">No overdue payments.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
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
                
                // 调试：看看获取到的 ID 是否正确
                console.log("Initializing chart for:", title);

                // 映射数据
                let series = [];
                if (title === 'Properties') series = [counts.occ_properties, counts.vacant_properties, counts.main_properties, counts.clean_properties];
                else if (title === 'Units') series = [counts.occ_units, counts.vacant_units, counts.main_units, counts.clean_units];
                else if (title === 'Rooms') series = [counts.occ_rooms, counts.vacant_rooms, counts.main_rooms, counts.clean_rooms]; // 请根据你后台实际的 key 检查！

                // 调试：看看 series 到底是什么
                console.log("Series data:", series);

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
                    series: safeSeries, // 使用处理后的安全数据
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