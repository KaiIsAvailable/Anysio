<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Properties</h1>
                    <p class="mt-2 text-sm text-gray-500">Manage and organize your property directory.</p>
                </div>
                <div class="flex-shrink-0">
                    @can('owner-admin')
                        <a href="{{ route('admin.properties.create') }}"
                        class="inline-flex items-center justify-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition-all duration-200">
                            <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Add New Property
                        </a>
                    @endcan
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="p-5 border-b border-gray-100 bg-white">
                    <div class="flex justify-end">
                        <form method="GET" action="{{ route('admin.properties.index') }}" class="flex items-stretch gap-2">
                            <div class="flex items-stretch justify-between">
                                <a href="{{ route('admin.roomAsset.index') }}" 
                                class="inline-flex items-center px-5 py-2.5 text-sm font-medium text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors mr-4">
                                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                    View Room Assets
                                </a>
                                <div class="relative flex-1">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <input type="text"
                                           name="search"
                                           value="{{ request('search') }}"
                                           style="padding-left: 45px;"
                                           class="block w-72 sm:w-80 pr-4 py-2.5 bg-gray-50 border border-gray-300 text-slate-900 text-sm rounded-l-lg focus:ring-indigo-500 focus:border-indigo-500 transition-colors placeholder-gray-400"
                                           placeholder="Search property name...">
                                </div>
                                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-r-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                                    Search
                                </button>
                                @if(request('search'))
                                    <a href="{{ route('admin.properties.index', array_merge(request()->except('search','page'))) }}"
                                       class="ml-2 inline-flex items-center px-3 py-2 rounded-lg bg-gray-50 hover:bg-gray-100 text-slate-900 border border-gray-200 text-sm">
                                        Clear
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    @if($properties->count() > 0)
                        @php
                            $sort = request('sort');
                            $noDesc = $sort === 'property_no_desc';
                            $noAsc  = $sort === 'property_no_asc';
                            $nextNoSort = $noAsc ? 'property_no_desc' : 'property_no_asc';

                            $dateDesc = $sort === 'newest';
                            $dateAsc  = is_null($sort) || $sort === 'oldest';
                            $nextDateSort = $dateAsc ? 'newest' : 'oldest';
                        @endphp

                        <table class="table-auto w-full min-w-[1200px] divide-y divide-gray-200 text-left">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase min-w-[250px]">
                                        <a href="{{ route('admin.properties.index', array_merge(request()->query(), ['sort' => $nextNoSort])) }}"
                                        class="inline-flex items-center space-x-1 text-gray-700 hover:text-indigo-600">
                                            <span>Property Name</span>
                                            @if($noAsc)
                                                <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                            @elseif($noDesc)
                                                <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                            @endif
                                        </a>
                                    </th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Type</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Owner</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase min-w-[200px]">Address</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">
                                        <a href="{{ route('admin.properties.index', array_merge(request()->query(), ['sort' => $nextDateSort])) }}"
                                        class="inline-flex items-center space-x-1 text-gray-700 hover:text-indigo-600">
                                            <span>Created</span>
                                            @if($dateAsc)
                                                <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                            @elseif($dateDesc)
                                                <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                            @endif
                                        </a>
                                    </th>
                                    @can('owner-admin')
                                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                    @endcan
                                </tr>
                            </thead>

                            <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($properties as $property)
                                @php
                                    $status = $property->status;
                                    $badge = match ($status) {
                                        'Vacant' => 'bg-green-100 text-green-800',
                                        'Occupied'  => 'bg-amber-100 text-amber-800',
                                        'Maintenance' => 'bg-blue-100 text-blue-800',
                                        default     => 'bg-gray-100 text-gray-800',
                                    };

                                    $assetNames = collect(); 
                                    if(method_exists($property, 'assets') && $property->assets) {
                                        $assetNames = $property->assets->pluck('name')->filter()->unique();
                                    }
                                @endphp
                                <tr class="hover:!bg-indigo-50 transition-colors cursor-pointer group duration-150"
                                    data-href="{{ route('admin.properties.show', $property->id) }}"
                                    onclick="window.location=this.dataset.href">

                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 font-bold shadow-sm">
                                                {{ mb_strtoupper(mb_substr($property->name ?? 'P', 0, 1, 'UTF-8')) }}
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-slate-900">{{ $property->name ?? '' }}</div>
                                                <div class="text-xs text-gray-400">{{ $property->units_count ?? 0 }} Units</div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900">
                                        {{ $property->type ?? '-' }}
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                            {{ $property->status ?? '-' }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-slate-900">{{ $property->owner->user->name ?? '-' }}</div>
                                        <div class="text-xs text-gray-500">{{ $property->owner->user->email ?? '-' }}</div>
                                    </td>

                                    <td class="px-6 py-4 text-sm text-slate-900">
                                        <div class="line-clamp-2 max-w-xs">{{ $property->address ?? '-' }}</div>
                                        <div class="line-clamp-2 max-w-xs">{{ $property->postcode ?? '-' }}</div>
                                        <div class="line-clamp-2 max-w-xs">{{ $property->city ?? '-' }}</div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900">
                                        {{ optional($property->created_at)->format('d M Y') }}
                                    </td>

                                    @can('owner-admin')
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <div class="flex items-center justify-center space-x-2" onclick="event.stopPropagation();">
                                                <a href="{{ route('admin.properties.edit', $property->id) }}"
                                                class="p-2 text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors"
                                                title="Edit">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                </a>
                                                @if($property->status == 'Removed')
                                                    {{-- Restore 按钮 --}}
                                                    <form action="{{ route('admin.properties.restore', $property->id) }}" method="POST" class="inline-block"
                                                        onsubmit="return confirm('Restore property {{ addslashes($property->name) }}? All data status under this property will be restored as vacant.');">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" 
                                                                class="p-2 text-green-600 hover:text-green-900 bg-green-50 hover:bg-green-100 rounded-lg transition-colors" 
                                                                title="Restore Property">
                                                            {{-- 恢复/撤销图标 --}}
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @else
                                                    <form action="{{ route('admin.properties.destroy', $property->id) }}" method="POST"
                                                        onsubmit="return confirm('Delete property {{ addslashes($property->name) }}? All data status under this property will be set as removed.');"
                                                        class="inline-block">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="p-2 text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 rounded-lg transition-colors" title="Delete">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
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
                    @else
                        <div class="text-center py-20 bg-white">
                            <h3 class="text-lg font-medium text-slate-900">No properties found</h3>
                            <p class="mt-1 text-gray-500">Try adjusting your search or add a new property.</p>
                        </div>
                    @endif
                </div>

                @if($properties->hasPages())
                    <div class="bg-white px-6 py-4 border-t border-gray-100">
                        {{ $properties->appends(request()->query())->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
