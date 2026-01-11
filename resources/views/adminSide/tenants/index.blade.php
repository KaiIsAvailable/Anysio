<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Tenants</h1>
                    <p class="mt-2 text-sm text-gray-500">Manage and organize your tenant directory.</p>
                </div>
                <div class="flex-shrink-0">
                    <a href="{{ route('admin.tenants.create') }}" 
                       class="inline-flex items-center justify-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-sm transition-all duration-200">
                        <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add New Tenant
                    </a>
                </div>
            </div>

            <!-- Content Card -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- Name Search -->
                <div class="p-5 border-b border-gray-100 bg-white mb-4">
                    <div class="flex justify-end">
                        <form method="GET" action="{{ route('admin.tenants.index') }}" class="ml-auto">
                            <div class="flex items-stretch">
                                <div class="relative flex-1">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <input type="text"
                                           name="search"
                                           value="{{ request('search') }}"
                                           style="padding-left: 22px;"
                                           class="block w-72 sm:w-80 pr-4 py-2.5 bg-gray-50 border border-gray-300 text-slate-900 text-sm rounded-l-lg focus:ring-indigo-500 focus:border-indigo-500 transition-colors placeholder-gray-400"
                                           placeholder="Search by name...">
                                </div>
                                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-r-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-sm">
                                    Search
                                </button>
                                @if(request('search'))
                                    <a href="{{ route('admin.tenants.index', array_merge(request()->except('search','page'))) }}" class="ml-2 inline-flex items-center px-3 py-2 rounded-lg bg-gray-50 hover:bg-gray-100 text-slate-900 border border-gray-200 text-sm">
                                        Clear
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Toolbar removed per request -->

                <!-- Table Section -->
                <div class="overflow-x-auto">
                    @if($tenants->count() > 0)
                        @php
                            $sort = request('sort');
                            // Default arrows show ASC when no explicit sort provided
                            $nameDesc = $sort === 'name_desc';
                            $nameAsc  = $sort === 'name_asc';
                            $nextNameSort = $nameAsc ? 'name_desc' : 'name_asc';

                            // Created date: ASC = oldest first, DESC = newest first
                            $dateDesc = $sort === 'newest';
                            $dateAsc  = is_null($sort) || $sort === 'oldest';
                            $nextDateSort = $dateAsc ? 'newest' : 'oldest';
                        @endphp
                        <table class="table-fixed w-full min-w-[1200px] divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        <a href="{{ route('admin.tenants.index', array_merge(request()->query(), ['sort' => $nextNameSort])) }}" class="inline-flex items-center space-x-1 text-gray-700 hover:text-indigo-600">
                                            <span>Name</span>
                                            @if($nameAsc) 
                                                <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                            @elseif($nameDesc)
                                                <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                            @endif
                                        </a>
                                    </th>
                                    <th scope="col" class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Phone</th>
                                    <th scope="col" class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">IC Number</th>
                                    <th scope="col" class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-40">Passport</th>
                                    <th scope="col" class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-64">Nationality</th>
                                    <th scope="col" class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Gender</th>
                                    <th scope="col" class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Occupation</th>
                                    <th scope="col" class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">IC Photo</th>
                                    <th scope="col" class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        <a href="{{ route('admin.tenants.index', array_merge(request()->query(), ['sort' => $nextDateSort])) }}" class="inline-flex items-center space-x-1 text-gray-700 hover:text-indigo-600">
                                            <span>Created At</span>
                                            @if($dateAsc)
                                                <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                            @elseif($dateDesc)
                                                <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                            @endif
                                        </a>
                                    </th>
                                    <th scope="col" class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Updated At</th>
                                    <th scope="col" class="sticky top-0 right-0 z-20 bg-gray-50 px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider shadow-[-4px_0_12px_-4px_rgba(0,0,0,0.1)]">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($tenants as $tenant)
                                    <tr class="hover:bg-gray-50 transition-colors duration-150 group">

                                        <td class="px-4 py-4 text-sm text-slate-900 whitespace-normal break-words max-w-[12rem]">{{ $tenant->user->name ?? '—' }}</td>
                                        <td class="px-4 py-4 text-sm text-slate-900 whitespace-normal break-words max-w-[10rem]">{{ $tenant->phone ?? '—' }}</td>
                                        <td class="px-4 py-4 text-sm text-slate-900 whitespace-normal break-words max-w-[10rem]">{{ $tenant->ic_number ?? '—' }}</td>
                                        <td class="px-4 py-4 text-sm text-slate-900 whitespace-normal break-words break-all w-40">{{ $tenant->passport ?? '—' }}</td>
                                        <td class="px-4 py-4 text-sm text-slate-900 whitespace-normal break-words break-all w-64">{{ $tenant->nationality ? ucfirst($tenant->nationality) : '—' }}</td>
                                        <td class="px-4 py-4 text-sm text-slate-900 whitespace-normal break-words max-w-[8rem]">{{ $tenant->gender ? ucfirst($tenant->gender) : '—' }}</td>
                                        <td class="px-4 py-4 text-sm text-slate-900 whitespace-normal break-words max-w-[14rem]">{{ $tenant->occupation ?? '—' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-slate-900">
                                            @if($tenant->ic_photo_path)
                                                <a href="{{ asset('storage/' . $tenant->ic_photo_path) }}" target="_blank" class="inline-block">
                                                    <img class="h-12 w-12 rounded-lg object-cover border border-gray-200" src="{{ asset('storage/' . $tenant->ic_photo_path) }}" alt="IC photo" style="width: 48px; height: 48px;">
                                                </a>
                                            @else
                                                <span class="text-xs text-gray-500">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-900 whitespace-normal break-words max-w-[12rem]">{{ optional($tenant->created_at)->format('Y-m-d H:i') ?? '—' }}</td>
                                        <td class="px-4 py-4 text-sm text-slate-900 whitespace-normal break-words max-w-[12rem]">{{ optional($tenant->updated_at)->format('Y-m-d H:i') ?? '—' }}</td>
                                        <td class="sticky right-0 top-0 z-10 px-4 py-4 whitespace-nowrap text-center text-sm font-medium bg-white group-hover:bg-gray-50 shadow-[-4px_0_12px_-4px_rgba(0,0,0,0.1)]">
                                            <div class="flex items-center justify-center space-x-2">
                                                <a href="{{ route('admin.tenants.edit', $tenant->id) }}" class="p-2 text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors" title="Edit">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                </a>
                                                <form action="{{ route('admin.tenants.destroy', $tenant->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete tenant {{ addslashes($tenant->user->name) }}?');" class="inline-block">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="p-2 text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 rounded-lg transition-colors" title="Delete">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <!-- Simple Empty State -->
                        <div class="text-center py-12">
                            <h3 class="mt-2 text-sm font-medium text-slate-900">No tenants found</h3>
                            <p class="mt-1 text-sm text-gray-500">Get started by creating a new tenant.</p>
                            <div class="mt-6">
                                <a href="{{ route('admin.tenants.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                    </svg>
                                    Add Tenant
                                </a>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Pagination -->
                @if($tenants->hasPages())
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        {{ $tenants->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
