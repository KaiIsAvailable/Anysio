<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Owners</h1>
                    <p class="mt-2 text-sm text-gray-500">Manage and organize your property owner directory.</p>
                </div>
                @can('owner-admin')
                <div class="flex-shrink-0">
                    <a href="{{ route('admin.owners.create') }}" 
                       class="inline-flex items-center justify-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition-all duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add New Owner
                    </a>
                </div>
                @endcan
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                
                <div class="p-5 border-b border-gray-100 bg-white">
                    <div class="flex justify-end">
                        <form method="GET" action="{{ route('admin.owners.index') }}" class="flex items-stretch gap-2">
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
                                           placeholder="Search by owner name...">
                                </div>
                                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-r-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                                    Search
                                </button>
                                @if(request('search'))
                                    <a href="{{ route('admin.owners.index') }}" class="ml-2 inline-flex items-center px-3 py-2 rounded-lg bg-gray-50 hover:bg-gray-100 text-slate-900 border border-gray-200 text-sm">
                                        Clear
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    @if($owners && $owners->count() > 0)
                        <table class="w-full divide-y divide-gray-200 text-left">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Owner Details</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Company</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Contact Info</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Joined Date</th>
                                    @can('owner-admin')
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                    @endcan
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($owners as $owner)
                                    <tr class="hover:!bg-indigo-50 transition-colors cursor-pointer group duration-150"
                                        onclick="window.location='{{ route('admin.owners.show', $owner->id) }}'">
                                        
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 font-bold">
                                                    {{ strtoupper(substr($owner->user->name ?? 'O', 0, 1)) }}
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-slate-900">{{ $owner->user->name ?? '—' }}</div>
                                                    <div class="text-xs text-gray-500">{{ $owner->user->email ?? '—' }}</div>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-slate-900 font-medium">{{ $owner->company_name ?? 'Individual' }}</div>
                                            <div class="text-xs text-gray-400 capitalize">{{ $owner->gender }}</div>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-slate-700">{{ $owner->phone ?? '—' }}</div>
                                            <div class="text-xs text-indigo-600 font-medium">IC: {{ $owner->ic_number ?? '—' }}</div>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm text-slate-700 font-medium">
                                                {{ $owner->created_at ? $owner->created_at->format('d M Y') : 'N/A' }}
                                            </span>
                                        </td>
                                        
                                        @can('owner-admin')
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <div class="flex items-center justify-center space-x-2" onclick="event.stopPropagation();">
                                                <a href="{{ route('admin.owners.edit', $owner->id) }}" class="p-2 text-indigo-600 hover:text-indigo-900 bg-indigo-50 rounded-lg transition-colors">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                </a>
                                                <form action="{{ route('admin.owners.destroy', $owner->id) }}" method="POST" onsubmit="return confirm('Delete this owner?');" class="inline">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="p-2 text-red-600 hover:text-red-900 bg-red-50 rounded-lg transition-colors">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        @endcan
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="text-center py-20 bg-white">
                            <h3 class="text-lg font-medium text-slate-900">No owners found</h3>
                            <p class="mt-1 text-gray-500">Try adjusting your search or add a new owner.</p>
                        </div>
                    @endif
                </div>

                @if($owners && method_exists($owners, 'hasPages') && $owners->hasPages())
                    <div class="bg-white px-6 py-4 border-t border-gray-100">
                        {{ $owners->appends(request()->query())->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>