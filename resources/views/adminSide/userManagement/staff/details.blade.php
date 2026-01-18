<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <nav class="flex mb-2" aria-label="Breadcrumb">
                        <a href="{{ route('admin.staff.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 flex items-center transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Back to Staff List
                        </a>
                    </nav>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Staff Details</h1>
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.staff.edit', $staff->id) }}" 
                       class="inline-flex items-center px-4 py-2.5 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-all">
                        <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Edit Staff
                    </a>
                    
                    <form action="{{ route('admin.staff.destroy', $staff->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to remove this staff member?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="inline-flex items-center px-4 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-red-600 hover:bg-red-700 shadow-sm transition-all">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Delete
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="px-8 py-8 border-b border-gray-100 bg-white">
                    <div class="flex items-center">
                        <div class="h-16 w-16 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 text-2xl font-bold">
                            {{ strtoupper(substr($staff->user->name ?? 'S', 0, 1)) }}
                        </div>
                        <div class="ml-6">
                            <h2 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $staff->user->name ?? 'N/A' }}</h2>
                            <p class="text-sm text-gray-500 font-medium">{{ $staff->user->email ?? 'N/A' }}</p>
                        </div>
                        <div class="ml-auto text-right">
                            <span class="inline-flex items-center px-4 py-1.5 rounded-full text-xs font-bold tracking-wide bg-blue-100 text-blue-800">
                                {{ strtoupper($staff->role ?? 'STAFF') }} POSITION
                            </span>
                        </div>
                    </div>
                </div>

                <div class="px-8 py-10 bg-white">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-y-10 gap-x-12">
                        
                        <div class="space-y-4">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">Employment Overview</h3>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Position / Title</label>
                                <p class="text-sm font-bold text-slate-900 uppercase mt-0.5 tracking-tight">{{ $staff->role ?? 'Staff' }}</p>
                            </div>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Date Joined</label>
                                <p class="text-sm font-semibold text-slate-700 mt-0.5">
                                    {{ $staff->created_at ? $staff->created_at->format('d M Y, H:i') : 'N/A' }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">Management Info</h3>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Direct Manager</label>
                                <div class="flex items-center mt-0.5">
                                    <span class="text-sm font-bold text-slate-900">{{ $staff->user_management->user->name ?? 'System Admin' }}</span>
                                </div>
                            </div>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Management ID</label>
                                <p class="text-xs font-mono font-medium text-indigo-600 mt-0.5">{{ $staff->user_mgnt_id }}</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">Work Assignment</h3>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Department / Branch</label>
                                <div class="mt-1">
                                    <span class="text-sm font-mono font-bold text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-md border border-indigo-100">
                                        {{ $staff->branch ?? 'GENERAL' }}
                                    </span>
                                </div>
                            </div>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Status</label>
                                <p class="text-sm font-semibold text-slate-700 mt-0.5 flex items-center">
                                    <span class="w-1.5 h-1.5 bg-green-400 rounded-full mr-2"></span>
                                    Active Account
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
                                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Internal Staff Notes</h3>
                            </div>
                            <p class="text-sm text-gray-500 italic">No additional administrative notes or performance logs available for this staff member.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>