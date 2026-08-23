<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Staff</h1>
                    <p class="mt-2 text-sm text-gray-500">Staff linked to your account.</p>
                </div>
                <div class="flex-shrink-0" x-data="{loading: false}">
                    <x-form.primary-button
                        type="button"
                        loading="loading"
                        @click="loading = true; window.location.href = '{{ route('admin.staff.create') }}'"
                        >
                        <svg x-show="!loading" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add New Staff
                    </x-form.primary-button>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="p-5 border-b border-gray-100 bg-white">
                    @if (session('status'))
                        <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-400 text-green-700">
                            <div class="font-bold">{{ session('status')['message'] }}</div>
                            <div class="text-sm">Email: {{ session('status')['email'] }} | Password: <span class="font-mono font-bold">{{ session('status')['password'] }}</span></div>
                        </div>
                    @endif

                    <div class="flex justify-end">
                        <div class="flex items-stretch">
                            <x-form.form method="GET" action="{{ route('admin.staff.index') }}" class="flex flex-wrap items-center gap-4">
                                <div class="flex items-stretch justify-between">
                                    <x-table.search placeholder="Search by user name..." />
                                </div>
                            </x-form.form>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    @if($staff && $staff->count() > 0)
                        <table class="w-full divide-y divide-gray-200 text-left">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Staff Details</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Role</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Managed By</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Joined Date</th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($staff as $member)
                                    @php
                                        $staffUser = $member->user;
                                        $manager = optional($member->user_management)->user;
                                    @endphp
                                    <tr class="hover:!bg-indigo-50 transition-colors cursor-pointer group duration-150" 
                                        onclick="window.location='{{ route('admin.staff.show', $member->id) }}'">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex items-center space-x-2">
                                                    <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 font-bold shrink-0">
                                                        {{ strtoupper(substr($staffUser->name ?? 'S', 0, 1)) }}
                                                    </div>
                                                    <div class="ml-4">
                                                        <!-- Name and Verification Badge side-by-side -->
                                                        <div class="flex items-center space-x-2">
                                                            <span class="text-sm font-medium text-slate-900">{{ $staffUser->name ?? 'N/A' }}</span>
                                                            @if(optional($staffUser)->email_verified_at)
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800" title="Email Verified">
                                                                    Verified
                                                                </span>
                                                            @else
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800" title="Email Unverified">
                                                                    Unverified
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <div class="text-xs text-gray-500 mt-0.5">{{ $staffUser->email ?? 'N/A' }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-bold uppercase bg-blue-50 text-blue-700">
                                                {{ $member->role }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700">
                                            <div class="flex flex-col">
                                                <span class="font-medium text-indigo-600">{{ $manager->name ?? 'System' }}</span>
                                                <span class="text-[10px] text-gray-400">ID: {{ substr($member->user_mgnt_id, 0, 8) }}...</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex flex-col">
                                                <span class="text-sm text-slate-700 font-medium">
                                                    {{ $member->created_at ? $member->created_at->format('d M Y') : 'N/A' }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <div class="flex items-center justify-center space-x-2">
                                                <a href="{{ route('admin.staff.edit', $member->id) }}" class="p-2 text-indigo-600 hover:text-indigo-900 bg-indigo-50 rounded-lg">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                </a>
                                                <form action="{{ route('admin.staff.destroy', $member->id) }}" method="POST" onsubmit="return confirm('Delete this staff?');" class="inline">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="p-2 text-red-600 hover:text-red-900 bg-red-50 rounded-lg">
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
                        <div class="text-center py-20 bg-white">
                            <h3 class="text-lg font-medium text-slate-900">No staff found</h3>
                            <p class="mt-1 text-gray-500">Try adjusting your search.</p>
                        </div>
                    @endif
                </div>

                @if($staff && method_exists($staff, 'hasPages') && $staff->hasPages())
                    <div class="bg-white px-6 py-4 border-t border-gray-100">
                        {{ $staff->appends(request()->query())->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>