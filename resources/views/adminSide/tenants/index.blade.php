<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Tenants</h1>
                    <p class="mt-2 text-sm text-gray-500">Manage and organize your tenant directory.</p>
                </div>
                
                <!-- Added 'flex items-center gap-3' here -->
                <div class="flex-shrink-0 flex items-center gap-3" x-data="{loading: false, importModalOpen: false}">
                    @can('super-admin')
                        <x-form.primary-button
                            type="button"
                            @click="$dispatch('click', { modal: 'importModalOpen' })"
                            class="inline-flex items-center"
                        >
                            <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <span>Import Excel</span>
                        </x-form.primary-button>
                    @endcan

                    @can('owner-admin')
                        <x-form.primary-button
                            type="button"
                            loading="loading"
                            @click="loading = true; window.location.href = '{{ route('admin.tenants.create') }}'"
                            class="inline-flex items-center"
                        >
                            <svg x-show="!loading" class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            <span>Add New Tenant</span>
                        </x-form.primary-button>
                    @endcan
                </div>

                <x-modals.excel-import-modal 
                    id="importModalOpen"
                    title="Import Tenants from Excel"
                    :route="route('admin.import', 'tenants')"
                    :users="$users"
                    description="Your file must contain both <strong>Tenant</strong> and <strong>Emergency Contact</strong> sheets."
                />
            </div>

            @if(session('import_session'))
                <div class="fixed bottom-6 right-6 z-50 bg-slate-900 text-white p-4 rounded-xl shadow-2xl flex items-center gap-4 border border-slate-700">
                    <div>
                        <p class="font-bold text-sm">Review Imported Data</p>
                        <p class="text-xs text-slate-300">Check your directory. Keep or reverse this batch?</p>
                    </div>
                    <div class="flex gap-2">
                        <!-- REVERSE BUTTON -->
                        <form action="{{ route('admin.import.revert') }}" method="POST">
                            @csrf
                            <input type="hidden" name="session_key" value="{{ session('import_session') }}">
                            <button type="submit" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-xs font-bold rounded-lg">Reverse</button>
                        </form>

                        <!-- DONE BUTTON -->
                        <form action="{{ route('admin.import.confirm') }}" method="POST">
                            @csrf
                            <input type="hidden" name="session_key" value="{{ session('import_session') }}">
                            <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-xs font-bold rounded-lg">Done</button>
                        </form>
                    </div>
                </div>
            @endif

            <!-- Content Card -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                
                <!-- Search Section -->
                <div class="p-5 border-b border-gray-100 bg-white">
                    <div class="flex justify-end">
                        <x-form.form method="GET" action="{{ route('admin.tenants.index') }}" class="flex flex-wrap items-center gap-4">
                            <div class="flex items-stretch justify-between">
                                <x-table.search placeholder="Search by tenant name..." />
                            </div>
                        </x-form.form>
                    </div>                </div>

                <!-- Table Section -->
                <div class="overflow-x-auto">
                    @if($tenants->count() > 0)
                        <table class="table-auto w-full min-w-[1200px] divide-y divide-gray-200 text-left">
                            <thead class="bg-gray-50">
                                <tr>
                                    <x-table.th name="Tenant Details" sortField="n" />
                                    <x-table.th name="Contact Info" sortField="p" />
                                    <x-table.th name="Demographics" />
                                    <x-table.th name="Emergency" />
                                    <x-table.th name="Document" />
                                    <x-table.th name="Joined Date" sortField="jd" />
                                    @can('owner-admin')
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                    @endcan
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
                                                    {{ strtoupper(mb_substr($tenant->user->name ?? 'T', 0, 1, 'UTF-8')) }}
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
                                            @if($tenant->ic_photo_path)
                                                <a href="{{ route('admin.tenants.view-ic', $tenant->id) }}" 
                                                onclick="event.stopPropagation();" 
                                                class="inline-flex items-center px-2 py-1 bg-green-50 text-green-700 border border-green-200 rounded text-[10px] font-bold uppercase">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                    Uploaded
                                                </a>
                                            @else
                                                <span class="text-gray-300 text-[10px] font-bold uppercase tracking-widest">Missing</span>
                                            @endif
                                        </td>

                                        <!-- Joined Date -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm text-slate-700 font-medium">
                                                {{ $tenant->created_at->format('d M Y') }}
                                            </span>
                                        </td>

                                        <!-- Actions -->
                                        @can('owner-admin')
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
                                        @endcan
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <!-- Empty State -->
                        <div class="text-center py-20 bg-white">
                            <h3 class="text-lg font-medium text-slate-900">No tenants found</h3>
                            <p class="mt-1 text-gray-500">Get started by creating a new tenant.</p>
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
