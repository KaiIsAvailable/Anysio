<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <nav class="flex mb-2" aria-label="Breadcrumb">
                        <a href="{{ route('admin.leases.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 flex items-center transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Back to Leases List
                        </a>
                    </nav>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Lease Details</h1>
                </div>
                @if(strtolower((string) ($lease->status ?? '')) !== 'end')
                    <div class="flex items-center gap-3">
                        <a href="{{ route('admin.leases.create', ['room_id' => $lease->room_id, 'tenant_id' => $lease->tenant_id, 'renew' => 1]) }}"
                           class="inline-flex items-center px-4 py-2.5 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-all">
                            <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M5 19a9 9 0 0114-7M19 5a9 9 0 00-14 7"></path>
                            </svg>
                            Renew Lease
                        </a>
                    </div>
                @endif
            </div>

            @php
                $formatMoney = function ($cents) {
                    if ($cents === null) {
                        return 'N/A';
                    }
                    return number_format(((int) $cents) / 100, 2);
                };

                $status = strtolower((string) ($lease->status ?? ''));
                $badge = match ($status) {
                    'new' => 'bg-blue-100 text-blue-800',
                    'renew' => 'bg-indigo-100 text-indigo-800',
                    'check out' => 'bg-amber-100 text-amber-800',
                    'end' => 'bg-gray-100 text-gray-800',
                    default => 'bg-slate-100 text-slate-800',
                };
            @endphp

            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="px-8 py-8 border-b border-gray-100 bg-white">
                    <div class="flex items-center">
                        <div class="h-16 w-16 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 text-2xl font-bold">
                            {{ strtoupper(substr($lease->room?->room_no ?? 'L', 0, 1)) }}
                        </div>
                        <div class="ml-6">
                            <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Lease {{ $lease->id }}</h2>
                            <p class="text-sm text-gray-500 font-medium">
                                {{ $lease->tenant?->user?->name ?? 'N/A' }} - Room {{ $lease->room?->room_no ?? 'N/A' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="px-8 py-10 bg-white">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-y-10 gap-x-12">

                        <div class="space-y-4">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">Lease Info</h3>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Start Date</label>
                                <p class="text-sm font-semibold text-slate-700 mt-0.5">{{ $lease->start_date ?? 'N/A' }}</p>
                            </div>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">End Date</label>
                                <p class="text-sm font-semibold text-slate-700 mt-0.5">{{ $lease->end_date ?? 'N/A' }}</p>
                            </div>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Status</label>
                                <div class="mt-1">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                        {{ $lease->status ?? 'N/A' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">Tenant Info</h3>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Name</label>
                                <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $lease->tenant?->user?->name ?? 'N/A' }}</p>
                            </div>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Email</label>
                                <p class="text-sm font-semibold text-slate-700 mt-0.5">{{ $lease->tenant?->user?->email ?? 'N/A' }}</p>
                            </div>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">IC Number</label>
                                <p class="text-sm font-semibold text-slate-700 mt-0.5">{{ $lease->tenant?->ic_number ?? 'N/A' }}</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">Room Info</h3>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Room No</label>
                                <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $lease->room?->room_no ?? 'N/A' }}</p>
                            </div>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Room Type</label>
                                <p class="text-sm font-semibold text-slate-700 mt-0.5">{{ $lease->room?->room_type ?? 'N/A' }}</p>
                            </div>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Room Status</label>
                                <p class="text-sm font-semibold text-slate-700 mt-0.5">{{ $lease->room?->status ?? 'N/A' }}</p>
                            </div>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Address</label>
                                <p class="text-sm font-semibold text-slate-700 mt-0.5">{{ $lease->room?->address ?? 'N/A' }}</p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="mt-10 space-y-6">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                    <div class="p-6 border-b border-gray-100">
                        <h2 class="text-lg font-semibold text-slate-900">Owner Info</h2>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                            <div class="text-xs text-gray-500 uppercase tracking-wider">Owner Name</div>
                            <div class="mt-1 font-medium text-slate-900">{{ $lease->room?->owner?->user?->name ?? 'N/A' }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ $lease->room?->owner?->user?->email ?? 'N/A' }}</div>
                        </div>
                        <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                            <div class="text-xs text-gray-500 uppercase tracking-wider">Company</div>
                            <div class="mt-1 font-medium text-slate-900">{{ $lease->room?->owner?->company_name ?? 'N/A' }}</div>
                        </div>
                        <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                            <div class="text-xs text-gray-500 uppercase tracking-wider">Contact</div>
                            <div class="mt-1 font-medium text-slate-900">{{ $lease->room?->owner?->phone ?? 'N/A' }}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                    <div class="p-6 border-b border-gray-100">
                        <h2 class="text-lg font-semibold text-slate-900">Lease Amounts (RM)</h2>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                            <div class="text-xs text-gray-500 uppercase tracking-wider">Monthly Rent</div>
                            <div class="mt-1 font-medium text-slate-900">{{ $formatMoney($lease->monthly_rent) }}</div>
                        </div>
                        <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                            <div class="text-xs text-gray-500 uppercase tracking-wider">Security Deposit</div>
                            <div class="mt-1 font-medium text-slate-900">{{ $formatMoney($lease->security_deposit) }}</div>
                        </div>
                        <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                            <div class="text-xs text-gray-500 uppercase tracking-wider">Utilities Deposit</div>
                            <div class="mt-1 font-medium text-slate-900">{{ $formatMoney($lease->utilities_depost) }}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900">Utilities</h2>
                        <span class="text-sm text-gray-500">
                            Total: {{ $lease->utilities?->count() ?? 0 }}
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        @if($lease->utilities && $lease->utilities->count() > 0)
                            <table class="table-fixed w-full min-w-[900px] divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            <span class="block w-full text-left">Type</span>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            <span class="block w-full text-right">Previous (RM)</span>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            <span class="block w-full text-right">Current (RM)</span>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            <span class="block w-full text-right">Amount (RM)</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($lease->utilities as $utility)
                                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-slate-900 capitalize">{{ $utility->type ?? 'N/A' }}</div>
                                            </td>
                                            <td class="px-6 py-4 text-right text-sm text-slate-900">
                                                {{ $formatMoney($utility->prev_reading) }}
                                            </td>
                                            <td class="px-6 py-4 text-right text-sm text-slate-900">
                                                {{ $formatMoney($utility->curr_reading) }}
                                            </td>
                                            <td class="px-6 py-4 text-right text-sm text-slate-900">
                                                {{ $formatMoney($utility->amount) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="p-8 text-center">
                                <div class="text-sm font-medium text-slate-900">No utilities</div>
                                <div class="text-sm text-gray-500 mt-1">This lease has no utility records.</div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900">Room Assets</h2>
                        <span class="text-sm text-gray-500">
                            Total: {{ $lease->room?->assets?->count() ?? 0 }}
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        @if($lease->room && $lease->room->assets && $lease->room->assets->count() > 0)
                            <table class="table-fixed w-full min-w-[1100px] divide-y divide-gray-200">
                                <colgroup>
                                    <col class="w-[22%]">
                                    <col class="w-[14%]">
                                    <col class="w-[18%]">
                                    <col class="w-[28%]">
                                    <col class="w-[18%]">
                                </colgroup>

                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            <span class="block w-full text-left">Name</span>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            <span class="block w-full text-left">Condition</span>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            <span class="block w-full text-left">Last Maintenance</span>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            <span class="block w-full text-left">Remark</span>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            <span class="block w-full text-center">Created</span>
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($lease->room->assets as $asset)
                                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                                            <td class="px-6 py-4">
                                                <div class="w-full text-left">
                                                    <div class="text-sm font-medium text-slate-900">{{ $asset->name ?? 'N/A' }}</div>
                                                    <div class="text-xs text-gray-500">ID: {{ $asset->id ?? 'N/A' }}</div>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="w-full text-left text-sm text-slate-900">
                                                    {{ $asset->condition ?? 'N/A' }}
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="w-full text-left text-sm text-slate-900 whitespace-nowrap">
                                                    @if(!empty($asset->last_maintenance))
                                                        {{ \Illuminate\Support\Carbon::parse($asset->last_maintenance)->format('d M Y') }}
                                                    @else
                                                        N/A
                                                    @endif
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="w-full text-left text-sm text-slate-900 break-words line-clamp-2">
                                                    {{ $asset->remark ?? 'N/A' }}
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="w-full text-center text-sm text-slate-900 whitespace-nowrap">
                                                    {{ optional($asset->created_at)->format('d M Y') }}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="p-8 text-center">
                                <div class="text-sm font-medium text-slate-900">No assets</div>
                                <div class="text-sm text-gray-500 mt-1">This room has no assets recorded.</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
