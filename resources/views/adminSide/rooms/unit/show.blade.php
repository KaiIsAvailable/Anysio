<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <nav class="flex mb-2 text-xs font-medium uppercase tracking-wider text-gray-400">
                        <a href="{{ route('admin.properties.index') }}" class="hover:text-indigo-600 transition-colors">Properties</a>
                        <span class="mx-2">/</span>
                        <a href="{{ route('admin.properties.show', $unit->property->id) }}" class="hover:text-indigo-600 transition-colors">{{ $unit->property->name }}</a>
                        <span class="mx-2">/</span>
                        <span class="text-indigo-600">Unit {{ $unit->unit_no }}</span>
                    </nav>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Unit {{ $unit->unit_no }} <span class="text-indigo-600">Rooms</span></h1>
                    <p class="mt-2 text-sm text-gray-500 italic">Manage and organize rooms within this unit.</p>
                </div>
                <div class="flex-shrink-0" x-data="{loading: false}">
                    @can('owner-admin')
                    <x-form.primary-button
                        type="button"
                        loading="loading"
                        @click="loading = true; window.location.href = '{{ route('admin.rooms.create', ['unit_id' => $unit->id]) }}'">

                        <svg x-show="!loading" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add New Room
                    </x-form.primary-button>
                    @endcan
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="p-5 border-b border-gray-100 bg-white">
                    <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                        <h2 class="text-lg font-semibold text-slate-800 italic">Listing Rooms</h2>

                        {{-- 💡 使用領導的 Search Component --}}
                        <x-form.form method="GET" action="{{ route('admin.units.show', $unit->id) }}" class="flex flex-wrap items-center gap-4">
                            <div class="flex items-stretch justify-between">
                                <x-table.search placeholder="Search room or asset..." />
                            </div>
                        </x-form.form>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    @if($unit->rooms->count() > 0)
                    <table class="table-fixed w-full min-w-[1200px] divide-y divide-gray-200 text-left">
                        <thead class="bg-gray-50">
                            <tr>
                                {{-- 💡 使用領導的 Table TH Component，並精準綁定 sortField --}}
                                <x-table.th name="Room No" sortField="room_no" />
                                <x-table.th name="Type" /> 
                                <x-table.th name="Status" sortField="status" />
                                <x-table.th name="Owner" />
                                <x-table.th name="Assets" />
                                <x-table.th name="Created" sortField="created_at" />
                                
                                @can('owner-admin')
                                    <x-table.th name="Actions" />
                                @endcan
                            </tr>
                        </thead>

                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($unit->rooms as $room)
                            @php
                                $status = strtolower((string) $room->status);
                                $badge = match ($status) {
                                    'vacant','available' => 'bg-green-100 text-green-800',
                                    'occupied' => 'bg-amber-100 text-amber-800',
                                    'maintenance' => 'bg-blue-100 text-blue-800',
                                    default => 'bg-gray-100 text-gray-800',
                                };
                                $assetNames = $room->assets->pluck('name')->filter()->unique()->values();
                            @endphp
                            <tr class="hover:!bg-indigo-50 transition-colors cursor-pointer group"
                                onclick="window.location='{{ route('admin.rooms.show', $room->id) }}'">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 font-bold">
                                            {{ strtoupper(substr($room->room_no ?? 'R', 0, 1)) }}
                                        </div>
                                        <div class="ml-4 text-sm font-medium text-gray-900">{{ $room->room_no }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-900">{{ $room->room_type }}</td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">{{ ucfirst($room->status) }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-900">{{ $room->unit->owner->name ?? '-' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-900">
                                    @if($assetNames->count())
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($assetNames->take(3) as $n)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] bg-gray-100 text-gray-700 border border-gray-200">{{ $n }}</span>
                                            @endforeach
                                            @if($assetNames->count() > 3)
                                                <span class="text-[10px] text-gray-400 font-medium">+{{ $assetNames->count() - 3 }} more</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-gray-300 italic text-xs">No assets</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-900">{{ optional($room->created_at)->format('d M Y') }}</td>
                                
                                {{-- 💡 完美還原：操作按鈕與 SVG 圖標 --}}
                                @can('owner-admin')
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="flex items-center justify-center space-x-2" onclick="event.stopPropagation();">
                                        @if($status !== 'inactive' && $status !== 'removed')
                                            {{-- Active 狀態：顯示 Edit 和 Delete --}}
                                            <a href="{{ route('admin.rooms.edit', $room->id) }}"
                                                class="p-2 text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors"
                                                title="Edit">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </a>
                                            <form action="{{ route('admin.rooms.destroy', $room->id) }}" method="POST"
                                                onsubmit="return confirm('Delete room {{ addslashes($room->room_no ?? $room->id) }}? This will mark the room and its assets inactive.');"
                                                class="inline-block">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="p-2 text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 rounded-lg transition-colors"
                                                    title="Delete">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        @else
                                            {{-- Inactive/Removed 狀態：顯示 Restore --}}
                                            <form action="{{ route('admin.rooms.restore', $room->id) }}" method="POST"
                                                onsubmit="return confirm('Restore room {{ addslashes($room->room_no ?? $room->id) }}?');"
                                                class="inline-block">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                    class="p-2 text-green-600 hover:text-green-900 bg-green-50 hover:bg-green-100 rounded-lg transition-colors"
                                                    title="Restore">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                                @endcan
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    
                    {{-- 分頁 --}}
                    <div class="px-6 py-4 border-t border-gray-100">
                        {{ $unit->rooms->links() }}
                    </div>
                    @else
                    <div class="text-center py-20 bg-white">
                        <h3 class="text-lg font-medium text-slate-900">No rooms assigned to this unit</h3>
                        <p class="mt-1 text-gray-500">Add the first room to get started.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>