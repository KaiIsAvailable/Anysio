<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Header --}}
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('admin.rooms.index') }}"
                           class="inline-flex items-center px-3 py-2 rounded-lg bg-white border border-gray-200 text-slate-900 hover:bg-gray-50">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            Back
                        </a>

                        <h1 class="text-3xl font-bold text-slate-900 tracking-tight">
                            Room {{ $room->room_no ?? $room->id }}
                        </h1>

                        @php
                            $status = strtolower((string) ($room->status ?? ''));
                            $badge = match ($status) {
                                'available' => 'bg-green-100 text-green-800',
                                'occupied'  => 'bg-amber-100 text-amber-800',
                                'inactive', 'disabled' => 'bg-gray-100 text-gray-800',
                                default     => 'bg-blue-100 text-blue-800',
                            };
                        @endphp
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                            {{ $room->status ?? '—' }}
                        </span>
                    </div>

                    <p class="mt-2 text-sm text-gray-500">
                        View room details, assets, and leases.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.rooms.edit', $room->id) }}"
                       class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-sm transition-all duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit
                    </a>

                    <form action="{{ route('admin.rooms.destroy', $room->id) }}" method="POST"
                          onsubmit="return confirm('Delete room {{ addslashes($room->room_no ?? $room->id) }}?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 shadow-sm transition-all duration-200">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Delete
                        </button>
                    </form>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                {{-- Left: Main info --}}
                <div class="lg:col-span-2 space-y-6 min-w-0">

                    {{-- Room Info --}}
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="p-6 border-b border-gray-100">
                            <h2 class="text-lg font-semibold text-slate-900">Room Information</h2>
                        </div>

                        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Room No</div>
                                <div class="mt-1 font-medium text-slate-900">{{ $room->room_no ?? '—' }}</div>
                            </div>

                            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Type</div>
                                <div class="mt-1 font-medium text-slate-900">{{ $room->room_type ?? '—' }}</div>
                            </div>

                            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200 sm:col-span-2">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Address</div>
                                <div class="mt-1 font-medium text-slate-900">{{ $room->address ?? '—' }}</div>
                            </div>

                            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Created</div>
                                <div class="mt-1 font-medium text-slate-900">
                                    {{ optional($room->created_at)->format('d M Y, h:i A') }}
                                </div>
                            </div>

                            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Updated</div>
                                <div class="mt-1 font-medium text-slate-900">
                                    {{ optional($room->updated_at)->format('d M Y, h:i A') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Assets --}}
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
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
                                        <col class="w-[22%]"> {{-- Name --}}
                                        <col class="w-[14%]"> {{-- Condition --}}
                                        <col class="w-[18%]"> {{-- Last Maintenance --}}
                                        <col class="w-[28%]"> {{-- Remark --}}
                                        <col class="w-[18%]"> {{-- Created --}}
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
                                        @foreach($room->assets as $asset)
                                            <tr class="hover:bg-gray-50 transition-colors duration-150">
                                                <td class="px-6 py-4">
                                                    <div class="w-full text-left">
                                                        <div class="text-sm font-medium text-slate-900">{{ $asset->name ?? '—' }}</div>
                                                        <div class="text-xs text-gray-500">ID: {{ $asset->id ?? '—' }}</div>
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

                    {{-- Leases --}}
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
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
                                        <col class="w-[28%]">  {{-- Tenant --}}
                                        <col class="w-[18%]">  {{-- Start --}}
                                        <col class="w-[18%]">  {{-- End --}}
                                        <col class="w-[10%]">  {{-- Rent --}}
                                        <col class="w-[16%]">  {{-- Deposit --}}
                                        <col class="w-[10%]">  {{-- Status --}}
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

                {{-- Right: Owner + Stats (styled like Room Information) --}}
                <div class="space-y-6 min-w-0 mt-6 lg:mt-0">

                    {{-- Owner Information --}}
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="p-6 border-b border-gray-100">
                            <h2 class="text-lg font-semibold text-slate-900">Owner Information</h2>
                        </div>

                        <div class="p-6 grid grid-cols-1 gap-4 text-sm">
                            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Owner Name</div>
                                <div class="mt-1 font-medium text-slate-900">{{ $room->owner?->user?->name ?? '—' }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ $room->owner?->user?->email ?? '—' }}</div>
                            </div>

                            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Company</div>
                                <div class="mt-1 font-medium text-slate-900">{{ $room->owner?->company_name ?? 'Individual' }}</div>
                            </div>

                            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Phone</div>
                                <div class="mt-1 font-medium text-slate-900">{{ $room->owner?->phone ?? '—' }}</div>
                            </div>

                            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">IC</div>
                                <div class="mt-1 font-medium text-slate-900 break-words">{{ $room->owner?->ic_number ?? '—' }}</div>
                            </div>

                            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Gender</div>
                                <div class="mt-1 font-medium text-slate-900">{{ $room->owner?->gender ?? '—' }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Quick Stats --}}
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="p-6 border-b border-gray-100">
                            <h2 class="text-lg font-semibold text-slate-900">Quick Stats</h2>
                        </div>

                        <div class="p-6 grid grid-cols-1 gap-4 text-sm">
                            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Assets</div>
                                <div class="mt-1 font-semibold text-slate-900">{{ $room->assets->count() }}</div>
                            </div>

                            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Leases</div>
                                <div class="mt-1 font-semibold text-slate-900">{{ $room->leases->count() }}</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</x-app-layout>
