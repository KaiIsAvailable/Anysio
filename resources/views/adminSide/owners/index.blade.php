<x-app-layout>
    @if(session('owner_bulk_saved_draft_key'))
        <script>
            try { sessionStorage.removeItem(@js(session('owner_bulk_saved_draft_key'))); } catch (error) {}
        </script>
    @endif
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- Header & Actions --}}
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Owners</h1>
                    <p class="mt-2 text-sm text-gray-500">Manage and organize your property owner directory.</p>
                </div>
                
                <div class="flex-shrink-0 flex flex-wrap items-center gap-2" x-data="{loading: false}">
                    @canany(['super-admin', 'owner.create', 'owner.edit'])
                        <div x-data="{ bulkEditLoading: false }">
                            <x-form.primary-button type="button" loading="bulkEditLoading" @click="bulkEditLoading = true; window.location.href = '{{ route('admin.owners.bulk-edit') }}'">Bulk Edit</x-form.primary-button>
                        </div>
                    @endcanany

                    @canany(['super-admin', 'owner.create'])
                        <x-form.primary-button
                            type="button"
                            loading="loading"
                            @click="loading = true; window.location.href = '{{ route('admin.owners.create') }}'"
                            class="inline-flex items-center"
                        >
                            <svg x-show="!loading" class="w-4 h-4 mr-2 flex-shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            <span>Add New Owner</span>
                        </x-form.primary-button>
                    @endcan
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                
                {{-- Toolbar / Search --}}
                <div class="p-5 border-b border-gray-100 bg-white">
                    <div class="flex justify-end">
                        <x-form.form method="GET" action="{{ route('admin.owners.index') }}" class="flex flex-wrap items-center gap-4">
                            <div class="flex items-stretch justify-between">
                                <x-table.search placeholder="Search by owner name..." />
                            </div>
                        </x-form.form>
                    </div>
                </div>

                {{-- Table --}}
                <div class="overflow-x-auto">
                    @if($owners && $owners->count() > 0)
                        <table class="table-auto w-full min-w-[1200px] divide-y divide-gray-200 text-left">
                            <thead class="bg-gray-50">
                                <tr>
                                    <x-table.th name="Owner Details" sortField="n" />
                                    <x-table.th name="Company / Identity" sortField="c" />
                                    <x-table.th name="Contact Info" />
                                    <x-table.th name="Address" />
                                    <x-table.th name="Status" />
                                    <x-table.th name="Joined Date" sortField="jd" />
                                    @canany(['super-admin', 'owner.edit', 'owner.delete'])
                                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                    @endcan
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($owners as $owner)
                                    <tr class="hover:!bg-indigo-50 transition-colors cursor-pointer group duration-150"
                                        @canany(['super-admin', 'owner.show'])
                                        onclick="window.location='{{ route('admin.owners.show', $owner->id) }}'"
                                        @endcanany>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 font-bold">
                                                    {{ mb_strtoupper(mb_substr($owner->user->name ?? 'P', 0, 1, 'UTF-8')) }}
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-slate-900">{{ $owner->user->name ?? '—' }}</div>
                                                    <div class="text-xs text-gray-400 capitalize">{{ $owner->gender ?? '—' }}</div>
                                                    <div class="text-xs text-gray-500">{{ $owner->user->email ?? '—' }}</div>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-slate-900 font-medium">{{ $owner->user?->companies->first()?->name ?? '—' }}</div>
                                            <div class="text-xs text-indigo-600 font-medium">IC: {{ $owner->ic_number ?? '—' }}</div>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-slate-700">{{ $owner->phone ?? '—' }}</div>
                                        </td>

                                        <td class="px-6 py-4 text-sm text-slate-900">
                                            <div class="line-clamp-2 max-w-xs">
                                                {{ $owner->full_address !== '' ? $owner->full_address : '—' }}
                                            </div>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php $status = $owner->user->status; @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ get_status_badge($status) }}">
                                                {{ ucfirst($status) }}
                                            </span>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm text-slate-700 font-medium">
                                                {{ $owner->created_at ? $owner->created_at->format('d M Y') : '—' }}
                                            </span>
                                        </td>
                                        
                                        @canany(['super-admin', 'owner.edit', 'owner.delete'])
                                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                                <div class="flex items-center justify-center space-x-2" onclick="event.stopPropagation();">
                                                    <!-- Edit Button -->
                                                    @can('owner.edit')
                                                    <a href="{{ route('admin.owners.edit', $owner->id) }}" class="p-2 text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                    </a>
                                                    @endcan

                                                    @can('owner.delete')
                                                    @if(strtolower($owner->user->status) === 'inactive')
                                                        <!-- Restore Button (When Inactive) -->
                                                        <form action="{{ route('admin.owners.restore', $owner->id) }}" method="POST" class="inline">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button type="submit" class="p-2 text-emerald-600 hover:text-emerald-900 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors" title="Restore Owner">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                                            </button>
                                                        </form>
                                                    @else
                                                        <!-- Deactivate / Delete Button (When Active) -->
                                                        <form action="{{ route('admin.owners.destroy', $owner->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to deactivate this owner?');" class="inline">
                                                            @csrf 
                                                            @method('DELETE')
                                                            <button type="submit" class="p-2 text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 rounded-lg transition-colors" title="Deactivate Owner">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                            </button>
                                                        </form>
                                                    @endif
                                                    @endcan
                                                </div>
                                            </td>
                                        @endcanany
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

                {{-- Pagination --}}
                @if($owners && method_exists($owners, 'hasPages') && $owners->hasPages())
                    <div class="bg-white px-6 py-4 border-t border-gray-100">
                        {{ $owners->appends(request()->query())->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>