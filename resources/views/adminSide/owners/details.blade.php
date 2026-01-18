<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <nav class="flex mb-2" aria-label="Breadcrumb">
                        <a href="{{ route('admin.owners.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 flex items-center transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Back to Owners List
                        </a>
                    </nav>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Owner Details</h1>
                </div>

                @can('owner-admin')
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.owners.edit', $owner->id) }}" 
                       class="inline-flex items-center px-4 py-2.5 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-all">
                        <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Edit Owner
                    </a>
                    
                    <form action="{{ route('admin.owners.destroy', $owner->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this owner?');">
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
                            {{ strtoupper(substr($owner->user->name, 0, 1)) }}
                        </div>
                        <div class="ml-6">
                            <h2 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $owner->user->name }}</h2>
                            <p class="text-sm text-gray-500 font-medium">{{ $owner->user->email }}</p>
                        </div>
                    </div>
                </div>

                <div class="px-8 py-10 bg-white">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-y-10 gap-x-12">
                        
                        <div class="space-y-4">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">Professional Info</h3>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Company Name</label>
                                <p class="text-sm font-bold text-slate-900 mt-0.5 tracking-tight">{{ $owner->company_name ?? 'Individual Owner' }}</p>
                            </div>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">IC / Passport Number</label>
                                <p class="text-sm font-semibold text-slate-700 mt-0.5">{{ $owner->ic_number ?? '—' }}</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">Contact & Identity</h3>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Phone Number</label>
                                <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $owner->phone ?? '—' }}</p>
                            </div>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Gender</label>
                                <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $owner->gender ?? '—' }}</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">System Info</h3>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Joined Date</label>
                                <p class="text-sm font-semibold text-slate-700 mt-0.5">
                                    {{ $owner->created_at ? $owner->created_at->format('d M Y, H:i') : 'N/A' }}
                                </p>
                            </div>
                        </div>

                    </div>

                    <div class="mt-16 pt-8 border-t border-gray-100">
                        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200/60">
                            <div class="flex items-center mb-3">
                                <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Internal System Logs</h3>
                            </div>
                            <p class="text-sm text-gray-500 italic">No additional administrative notes available for this owner profile.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>