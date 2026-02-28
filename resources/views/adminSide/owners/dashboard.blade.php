<x-app-layout>
    <div class="py-12 bg-gray-100 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            
            <div class="flex justify-between items-center bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h1 class="text-2xl font-bold text-gray-800">Owner Dashboard</h1>
                <div class="flex space-x-3">
                    <a href="{{ route('admin.leases.index') }}">
                        <button class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50">
                            Open Leases
                        </button>
                    </a>
                    
                    <button class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 cursor-not-allowed" title="Coming Soon">
                        Announcement
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 text-center">
                    <h3 class="text-gray-500 text-sm font-medium">Tenants</h3>
                    <p class="text-4xl font-bold text-gray-900 my-2">{{ $tenantsCount }}</p>
                    <a href="#" class="text-indigo-600 text-sm hover:underline">View</a>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 text-center">
                    <h3 class="text-gray-500 text-sm font-medium">Rooms</h3>
                    <p class="text-4xl font-bold text-gray-900 my-2">{{ $roomsCount }}</p>
                    <a href="{{ route('admin.rooms.index') }}" class="text-indigo-600 text-sm hover:underline">View</a>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 text-center">
                    <h3 class="text-gray-500 text-sm font-medium">Leases</h3>
                    <p class="text-4xl font-bold text-gray-900 my-2">{{ $leasesCount }}</p>
                    <a href="#" class="text-indigo-600 text-sm hover:underline">View</a>
                </div>
            </div>

            <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-100 flex flex-col items-center">
                <div class="w-full max-w-md">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="font-bold text-gray-800">Today's Collection</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 text-gray-400 text-xs uppercase">
                            <tr>
                                <th class="px-6 py-4 font-medium">Name</th>
                                <th class="px-6 py-4 font-medium">Rate</th>
                                <th class="px-6 py-4 font-medium">Due Date</th>
                                <th class="px-6 py-4 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @if(isset($payments) && $payments->count() > 0)
                                @foreach($payments as $payment)
                                <tr class="hover:bg-gray-50 cursor-pointer" 
                                    onclick="window.location='{{ route('admin.leases.show', $payment->tenant->leases->first()->id) }}'"> 
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ $payment->tenant->name ?? 'Unknown' }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        ${{ number_format($payment->amount_due / 100, 2) }} 
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        {{ $payment->created_at->format('Y-m-d') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="px-2 py-1 rounded text-xs {{ $payment->status === 'Paid' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                            {{ $payment->status }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-500 text-center" colspan="4">
                                        No payments found for today.
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('statusChart').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: {!! json_encode($roomStatusStats->keys()) !!},
                datasets: [{
                    data: {!! json_encode($roomStatusStats->values()) !!},
                    backgroundColor: ['#3b82f6', '#10b981', '#ef4444'],
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'right' } } }
        });
    </script>
</x-app-layout>