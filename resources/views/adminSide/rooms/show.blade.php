<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <nav class="flex mb-2" aria-label="Breadcrumb">
                        <a href="{{ route('admin.units.show', $room->unit->id) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 flex items-center transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Back to Rooms List
                        </a>
                    </nav>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Room Details</h1>
                </div>

                @canany(['owner-admin', 'room.edit', 'room.delete'])
                    <div class="flex items-center gap-3">
                        {{-- Edit 按钮 --}}
                        @canany(['owner-admin', 'room.edit'])
                        <a href="{{ route('admin.rooms.edit', $room->id) }}"
                        class="inline-flex items-center px-4 py-2.5 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-all">
                            <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Edit Room
                        </a>
                        @endcanany

                        {{-- Delete 表单 - 核心修改：添加 class="inline-block" 或 "contents" --}}
                        @canany(['owner-admin', 'room.delete'])
                        <form action="{{ route('admin.rooms.destroy', $room->id) }}" 
                            method="POST" 
                            class="inline-block" 
                            onsubmit="return confirm('Delete room {{ addslashes($room->room_no ?? $room->id) }}?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="inline-flex items-center px-4 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-red-600 hover:bg-red-700 shadow-sm transition-all">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Delete
                            </button>
                        </form>
                        @endcanany
                    </div>
                @endcanany
            </div>

            {{-- Room Details --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200/80 overflow-hidden mb-8">
                {{-- Header --}}
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">
                            Room Details
                    </div>

                    @php
                        $status = $room->status ?? null;

                        $badge = match ($status) {
                            'Vacant' => 'bg-green-100 text-green-800',
                            'Occupied' => 'bg-amber-100 text-amber-800',
                            'Maintenance' => 'bg-blue-100 text-blue-800',
                            default => 'bg-gray-100 text-gray-800',
                        };
                    @endphp

                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                        {{ $room->status ?? '—' }}
                    </span>

                </div>

                {{-- Body --}}
                <div class="px-6 py-6">

                    <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-6">

                        {{-- Room Number --}}
                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                Room No
                            </dt>

                            <dd class="text-sm font-medium text-slate-900 mt-1">
                                {{ $room->room_no ?? '—' }}
                            </dd>
                        </div>

                        {{-- Room Type --}}
                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                Room Type
                            </dt>

                            <dd class="text-sm font-medium text-slate-900 mt-1">
                                {{ $room->room_type ?? '—' }}
                            </dd>
                        </div>

                        {{-- Owner Name --}}
                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                Owner
                            </dt>

                            <dd class="text-sm mt-1">
                                <a href="{{ route('admin.owners.show', $room->unit->owner->id) }}" class="font-semibold text-indigo-600 hover:text-indigo-900 hover:underline">
                                        {{ $room->unit->owner->name }}
                                </a>

                                @if($room->unit->owner?->email)
                                    <span class="block text-xs text-slate-500 mt-0.5">
                                        {{ $room->unit->owner->email }}
                                    </span>
                                @endif
                            </dd>
                        </div>

                        {{-- Address --}}
                        <div class="md:col-span-2">
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                Address
                            </dt>

                            <dd class="text-sm font-medium text-slate-800 mt-1">
                                {{ $fullAddress ?? '—' }}
                            </dd>
                        </div>

                        {{-- Created Date --}}
                        <div>
                            <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                Created Date
                            </dt>

                            <dd class="text-sm font-medium text-slate-800 mt-1">
                                {{ $room->created_at ? $room->created_at->format('d M Y, H:i') : '—' }}
                            </dd>
                        </div>

                    </dl>

                </div>
            </div>

            <div class="mt-10 space-y-6">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900">Assets</h2>
                        <span class="text-sm text-gray-500">Total: {{ $room->allAssets->count() }}</span>
                    </div>

                    <div class="overflow-x-auto">
                        @if($room->allAssets->count() > 0)
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
                                    @foreach($room->allAssets as $asset)
                                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                                            <td class="px-6 py-4">
                                                <div class="w-full text-left">
                                                    <div class="flex items-center justify-between gap-3">
                                                        <div class="text-sm font-medium text-slate-900">{{ $asset->name ?? '—' }}</div>
                                                        @if(!empty($asset->pivot->status) && strtolower($asset->pivot->status) === 'inactive')
                                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-700">Inactive</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="w-full text-left text-sm text-slate-900">
                                                    {{ $asset->pivot->condition ?? '—' }}
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="w-full text-left text-sm text-slate-900 whitespace-nowrap">
                                                    @if(!empty($asset->pivot->last_maintenance))
                                                        {{ \Illuminate\Support\Carbon::parse($asset->pivot->last_maintenance)->format('d M Y') }}
                                                    @else
                                                        No maintenance yet
                                                    @endif
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="w-full text-left text-sm text-slate-900 break-words line-clamp-2">
                                                    {{ $asset->pivot->remark ?? '—' }}
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
                            <x-table.lease-table :leases="$room->leases" :showOwner="true" :showTenant="true" :showAction="true" />
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
