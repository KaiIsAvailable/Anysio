<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <nav class="flex mb-2 text-xs font-medium uppercase tracking-wider text-gray-400">
                        <a href="{{ route('admin.properties.index') }}" class="hover:text-indigo-600 transition-colors">Properties</a>
                        <span class="mx-2">/</span>
                        <span class="text-indigo-600">{{ $property->name }}</span>
                    </nav>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">{{ $property->name }}</h1>
                    <div class="mt-2 flex items-center text-sm text-gray-500">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        {{ $property->address }}, {{ $property->postcode }} {{ $property->city }}
                    </div>
                </div>
                <div class="flex-shrink-0 flex gap-3">
                    @can('owner-admin')
                        <a href="{{ route('admin.units.create', ['property_id' => $property->id]) }}"
                        class="inline-flex items-center justify-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition-all duration-200">
                            <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Add New Unit
                        </a>
                    @endcan
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="p-5 border-b border-gray-100 bg-white">
                    <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                        <h2 class="text-lg font-semibold text-slate-800 italic">Listing Units</h2>
                        
                        <form method="GET" action="{{ route('admin.properties.show', $property->id) }}" class="flex items-stretch gap-2">
                            <div class="flex items-stretch">
                                <a href="{{ route('admin.roomAsset.index') }}" 
                                class="inline-flex items-center px-5 py-2.5 text-sm font-medium text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors mr-4">
                                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                    View Room Assets
                                </a>
                                <div class="relative flex-1">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <input type="text" name="search" value="{{ request('search') }}"
                                           style="padding-left: 45px;"
                                           class="block w-64 sm:w-72 pr-4 py-2.5 bg-gray-50 border border-gray-300 text-slate-900 text-sm rounded-l-lg focus:ring-indigo-500 focus:border-indigo-500 transition-colors placeholder-gray-400"
                                           placeholder="Search Unit No (e.g. 1-1-1a)...">
                                </div>
                                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-r-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                                    Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="table-auto w-full min-w-[1000px] divide-y divide-gray-200 text-left">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Unit No</th>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Owner</th>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Current Tenant</th>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Price</th>
                                @can('owner-admin')
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($property->units as $unit)
                                @php
                                    $uStatus = $unit->status;
                                    $uBadge = match ($uStatus) {
                                        'Vacant' => 'bg-green-100 text-green-800',
                                        'Occupied'  => 'bg-amber-100 text-amber-800',
                                        'Maintenance' => 'bg-blue-100 text-blue-800',
                                        default     => 'bg-gray-100 text-gray-800',
                                    };
                                @endphp
                                <tr class="hover:bg-indigo-50 transition-colors cursor-pointer group"
                                    @if($unit->has_rooms)
                                        onclick="window.location='{{ route('admin.units.show', $unit->id) }}'"
                                        style="cursor: pointer;"
                                    @else
                                        {{-- 如果是 false (0)，可以不写 onclick，或者弹个窗 --}}
                                        onclick="alert('This unit is a single unit (No rooms inside).')"
                                        style="cursor: default;"
                                    @endif
                                    >
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 font-bold shadow-sm">
                                                {{ mb_strtoupper(mb_substr($unit->unit_no ?? 'U', 0, 1, 'UTF-8')) }}
                                            </div>
                                            <div class="ml-3 text-sm font-medium text-gray-900">{{ $unit->unit_no }}</div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 text-sm text-slate-600 italic">
                                        <div class="text-sm font-medium text-slate-900">{{ $unit->owner->user->name ?? '-' }}</div>
                                        <div class="text-xs text-gray-500">{{ $unit->owner->user->email ?? '-' }}</div>
                                    </td>
                                    

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $uBadge }}">
                                            {{ ucfirst($uStatus) }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-slate-900">{{ $unit->tenant->name ?? 'No Tenant' }}</div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 font-medium">
                                        RM {{ number_format($unit->price ?? 0, 2) }}
                                    </td>

                                    @can('owner-admin')
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <div class="flex items-center justify-center space-x-2" onclick="event.stopPropagation();">
                                                <a href="{{ route('admin.units.edit', $unit->id) }}" class="p-2 text-indigo-600 hover:text-indigo-900 bg-indigo-50 rounded-lg" title="Edit Unit">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                </a>
                                                    @if($unit->status == 'Removed' && $property->status != 'Removed')
                                                    {{-- Restore Unit 按钮 --}}
                                                    <form action="{{ route('admin.units.restore', $unit->id) }}" method="POST" class="inline-block"
                                                        onsubmit="return confirm('Restore unit {{ addslashes($unit->unit_number) }}? This will set the unit and all its rooms back to Vacant.');">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" 
                                                                class="p-2 text-green-600 hover:text-green-900 bg-green-50 hover:bg-green-100 rounded-lg transition-colors" 
                                                                title="Restore Unit">
                                                            {{-- 恢复图标 --}}
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @else
                                                    @if($property->status != 'Removed')
                                                        {{-- Destroy (Remove) Unit 按钮 --}}
                                                        <form action="{{ route('admin.units.destroy', $unit->id) }}" method="POST" class="inline-block"
                                                            onsubmit="return confirm('Delete unit {{ addslashes($unit->unit_number) }}? This will mark the unit and all its rooms as Removed.');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" 
                                                                    class="p-2 text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 rounded-lg transition-colors" 
                                                                    title="Remove Unit">
                                                                {{-- 删除图标 --}}
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    @endif
                                                @endif
                                            </div>
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>