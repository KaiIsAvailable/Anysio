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
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                
                <!-- Search Section -->
                <div class="p-5 border-b border-gray-100 bg-white">
                    <div class="flex justify-end">
                        <form method="GET" action="{{ route('admin.tenants.index') }}" class="flex items-stretch gap-2">
                            <div class="flex items-stretch">
                                <div class="relative flex-1">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <input type="text" name="search" value="{{ request('search') }}"
                                           style="padding-left: 45px;"
                                           class="block w-72 sm:w-80 pr-4 py-2.5 bg-gray-50 border border-gray-300 text-slate-900 text-sm rounded-l-lg focus:ring-indigo-500 focus:border-indigo-500 transition-colors placeholder-gray-400"
                                           placeholder="Search by tenant name...">
                                </div>
                                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-r-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                                    Search
                                </button>
                                @if(request('search'))
                                    <a href="{{ route('admin.tenants.index') }}" class="ml-2 inline-flex items-center px-3 py-2 rounded-lg bg-gray-50 hover:bg-gray-100 text-slate-900 border border-gray-200 text-sm">
                                        Clear
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Table Section -->
                <div class="overflow-x-auto">
                    @if($tenants->count() > 0)
                        <table class="w-full divide-y divide-gray-200 text-left">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Tenant Details</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Contact Info</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Demographics</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Emergency</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Document</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Joined Date</th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($tenants as $tenant)
                                    <tr class="hover:!bg-indigo-50 transition-colors cursor-pointer group duration-150"
                                        onclick="window.location='{{ route('admin.tenants.show', $tenant->id) }}'">
                                        
                                        <!-- Tenant Details (Avatar + Name + Email) -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 font-bold flex-shrink-0">
                                                    {{ strtoupper(substr($tenant->user->name ?? 'T', 0, 1)) }}
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-slate-900">{{ $tenant->user->name }}</div>
                                                    <div class="text-xs text-gray-500">{{ $tenant->user->email }}</div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Contact Info (Phone + IC/Passport) -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-slate-900 font-medium">{{ $tenant->phone }}</div>
                                            <div class="text-xs text-indigo-600 font-medium">
                                                {{ $tenant->ic_number ? 'IC: ' . $tenant->ic_number : 'Pass: ' . $tenant->passport }}
                                            </div>
                                        </td>

                                        <!-- Demographics (Nationality + Gender + Occupation) -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-slate-900">{{ $tenant->nationality }}</div>
                                            <div class="text-xs text-gray-500">
                                                {{ $tenant->gender }} • {{ $tenant->occupation ?? 'N/A' }}
                                            </div>
                                        </td>

                                        <!-- Emergency Contacts -->
                                        <td class="px-6 py-4">
                                            @if($tenant->emergencyContacts->count())
                                                <div class="flex flex-col gap-1">
                                                    @foreach($tenant->emergencyContacts as $contact)
                                                        <div class="text-xs">
                                                            <span class="font-medium text-slate-900">{{ $contact->name }}</span>
                                                            <span class="text-gray-500">({{ $contact->phone }})</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-gray-400 text-xs">—</span>
                                            @endif
                                        </td>

                                        <!-- Document (IC/Passport Photo) -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="h-12 w-20 flex-shrink-0 overflow-hidden rounded bg-gray-100 border border-gray-200">
                                                 @if($tenant->ic_photo_path)
                                                    <a href="{{ route('admin.tenants.ic_photo', basename($tenant->ic_photo_path)) }}" target="_blank" onclick="event.stopPropagation();" class="block h-full w-full">
                                                        <img src="{{ route('admin.tenants.ic_photo', basename($tenant->ic_photo_path)) }}" alt="IC" class="h-full w-full object-cover">
                                                    </a>
                                                @else
                                                    <div class="flex items-center justify-center h-full w-full text-gray-400">
                                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>

                                        <!-- Joined Date -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm text-slate-700 font-medium">
                                                {{ $tenant->created_at->format('d M Y') }}
                                            </span>
                                        </td>

                                        <!-- Actions -->
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <div class="flex items-center justify-center space-x-2" onclick="event.stopPropagation();">
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
                        <!-- Empty State -->
                        <div class="text-center py-20 bg-white">
                            <h3 class="text-lg font-medium text-slate-900">No tenants found</h3>
                            <p class="mt-1 text-gray-500">Get started by creating a new tenant.</p>
                            <div class="mt-6">
                                <a href="{{ route('admin.tenants.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
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
                    <div class="bg-white px-6 py-4 border-t border-gray-100">
                        {{ $tenants->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
