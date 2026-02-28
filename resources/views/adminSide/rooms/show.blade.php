<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <nav class="flex mb-2" aria-label="Breadcrumb">
                        <a href="{{ route('admin.rooms.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 flex items-center transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Back to Rooms List
                        </a>
                    </nav>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Room Details</h1>
                </div>

                @can('owner-admin')
                    <div class="flex items-center gap-3">
                        <a href="{{ route('admin.rooms.edit', $room->id) }}"
                        class="inline-flex items-center px-4 py-2.5 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-all">
                            <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Edit Room
                        </a>

                        <form action="{{ route('admin.rooms.destroy', $room->id) }}" method="POST" onsubmit="return confirm('Delete room {{ addslashes($room->room_no ?? $room->id) }}?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="inline-flex items-center px-4 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-red-600 hover:bg-red-700 shadow-sm transition-all">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Delete
                            </button>
                        </form>
                    </div>
                @endcan
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="px-8 py-8 border-b border-gray-100 bg-white">
                    <div class="flex items-center">
                        <div class="h-16 w-16 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 text-2xl font-bold">
                            {{ strtoupper(substr($room->room_no ?? 'R', 0, 1)) }}
                        </div>
                        <div class="ml-6">
                            <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Room {{ $room->room_no ?? $room->id }}</h2>
                            <p class="text-sm text-gray-500 font-medium">{{ $room->room_type ?? '—' }}</p>
                        </div>
                    </div>
                </div>

                <div class="px-8 py-10 bg-white">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-y-10 gap-x-12">

                        <div class="space-y-4">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">Room Info</h3>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Room No</label>
                                <p class="text-sm font-bold text-slate-900 mt-0.5 tracking-tight">{{ $room->room_no ?? '—' }}</p>
                            </div>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Address</label>
                                <p class="text-sm font-semibold text-slate-700 mt-0.5">{{ $room->address ?? '—' }}</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">Owner Info</h3>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Owner Name</label>
                                <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $room->owner?->user?->name ?? '—' }}</p>
                            </div>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Owner Email</label>
                                <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $room->owner?->user?->email ?? '—' }}</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">System Info</h3>
                            @php
                                $status = strtolower((string) ($room->status ?? ''));
                                $badge = match ($status) {
                                    'vacant','available' => 'bg-green-100 text-green-800',
                                    'occupied' => 'bg-amber-100 text-amber-800',
                                    'maintenance' => 'bg-blue-100 text-blue-800',
                                    default => 'bg-gray-100 text-gray-800',
                                };
                            @endphp
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Status</label>
                                <div class="mt-1">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                        {{ $room->status ?? '—' }}
                                    </span>
                                </div>
                            </div>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Created Date</label>
                                <p class="text-sm font-semibold text-slate-700 mt-0.5">
                                    {{ $room->created_at ? $room->created_at->format('d M Y, H:i') : 'N/A' }}
                                </p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="mt-10 space-y-6">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900">Assets</h2>
                        <span class="text-sm text-gray-500">
                            Total: {{ $room->assets->count() }}
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        @if($room->assets->count() > 0)
                            <table class="table-fixed w-full min-w-[1100px] divide-y divide-gray-200">
                                <colgroup>
                                    <col class="w-[26.5%]">
                                    <col class="w-[18.5%]">
                                    <col class="w-[22.5%]">
                                    <col class="w-[32.5%]">
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
                                    </tr>
                                </thead>

                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($room->assets as $asset)
                                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                                            <td class="px-6 py-4">
                                                <div class="w-full text-left">
                                                    <div class="text-sm font-medium text-slate-900">{{ $asset->name ?? '—' }}</div>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="w-full text-left text-sm text-slate-900">
                                                    {{ $asset->condition ?? '—' }}
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="w-full text-left text-sm text-slate-900 whitespace-nowrap">
                                                    @if(!empty($asset->last_maintenance))
                                                        {{ \Illuminate\Support\Carbon::parse($asset->last_maintenance)->format('d M Y') }}
                                                    @else
                                                        —   
                                                    @endif
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="w-full text-left text-sm text-slate-900 break-words line-clamp-2">
                                                    {{ $asset->remark ?? '—' }}
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

                <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900">Leases</h2>
                        <span class="text-sm text-gray-500">
                            Total: {{ $room->leases->count() }}
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        @if($room->leases->count() > 0)
                            <table class="table-fixed w-full min-w-[1050px] divide-y divide-gray-200">
                                <colgroup>
                                    <col class="w-[28%]">
                                    <col class="w-[18%]">
                                    <col class="w-[18%]">
                                    <col class="w-[10%]">
                                    <col class="w-[16%]">
                                    <col class="w-[10%]">
                                </colgroup>

                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            <span class="block w-full text-left">Tenant</span>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            <span class="block w-full text-center">Start</span>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            <span class="block w-full text-center">End</span>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            <span class="block w-full text-center">Rent (RM)</span>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            <span class="block w-full text-left">Deposit</span>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            <span class="block w-full text-center">Status</span>
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($room->leases as $lease)
                                        @php
                                            $ls = strtolower((string) ($lease->status ?? ''));
                                            $lBadge = match ($ls) {
                                                'active' => 'bg-green-100 text-green-800',
                                                'ended', 'expired' => 'bg-gray-100 text-gray-800',
                                                default => 'bg-blue-100 text-blue-800',
                                            };

                                            $t  = $tenantsById[$lease->tenant_id] ?? null;
                                            $tu = $t?->user;
                                        @endphp

                                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                                            <td class="px-6 py-4">
                                                <div class="w-full text-left">
                                                    <div class="text-sm font-medium text-slate-900">{{ $tu->name ?? '—' }}</div>
                                                    <div class="text-xs text-gray-500">{{ $tu->email ?? '—' }}</div>
                                                    <div class="text-xs text-gray-400">ID: {{ $lease->tenant_id ?? '—' }}</div>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="w-full text-center text-sm text-slate-900 whitespace-nowrap">
                                                    {{ $lease->start_date ?? '—' }}
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="w-full text-center text-sm text-slate-900 whitespace-nowrap">
                                                    {{ $lease->end_date ?? '—' }}
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="w-full text-center text-sm text-slate-900 whitespace-nowrap">
                                                    {{ $lease->monthly_rent ?? '—' }}
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="w-full text-left text-sm text-slate-900">
                                                    SD: {{ $lease->security_deposit ?? '—' }}
                                                    <div class="text-xs text-gray-500">Util: {{ $lease->utilities_depost ?? '—' }}</div>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="w-full text-center whitespace-nowrap">
                                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $lBadge }}">
                                                        {{ $lease->status ?? '—' }}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="p-8 text-center">
                                <div class="text-sm font-medium text-slate-900">No leases</div>
                                <div class="text-sm text-gray-500 mt-1">This room currently has no lease records.</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
