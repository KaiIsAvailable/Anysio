<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Maintenance Requests</h2>
                <a href="{{ route('admin.maintenance.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Add New Maintenance
                </a>
            </div>

            <!-- Success Message -->
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <form method="GET" action="{{ route('admin.maintenance.index') }}" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Search Input -->
                        <div class="md:col-span-2">
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <input type="text"
                                   name="search"
                                   id="search"
                                   value="{{ request('search') }}"
                                   placeholder="Search by title, description, tenant, room, or asset..."
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status"
                                    id="status"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Statuses</option>
                                <option value="Pending" {{ request('status') == 'Pending' ? 'selected' : '' }}>Pending</option>
                                <option value="Fixing" {{ request('status') == 'Fixing' ? 'selected' : '' }}>Fixing</option>
                                <option value="Resolved" {{ request('status') == 'Resolved' ? 'selected' : '' }}>Resolved</option>
                            </select>
                        </div>

                        <!-- Paid By Filter -->
                        <div>
                            <label for="paid_by" class="block text-sm font-medium text-gray-700 mb-1">Paid By</label>
                            <select name="paid_by"
                                    id="paid_by"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All</option>
                                <option value="Owner" {{ request('paid_by') == 'Owner' ? 'selected' : '' }}>Owner</option>
                                <option value="Tenant" {{ request('paid_by') == 'Tenant' ? 'selected' : '' }}>Tenant</option>
                            </select>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-2">
                        <a href="{{ route('admin.maintenance.index') }}"
                           class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 focus:bg-gray-400 active:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Clear Filters
                        </a>
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Search
                        </button>
                    </div>
                </form>
            </div>

            <!-- Maintenance Table -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                @if($maintenances->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Photo</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lease Info</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Asset</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title & Description</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid By</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($maintenances as $maintenance)
                                    <tr class="hover:bg-gray-50 transition-colors duration-150">
                                        <!-- Photo -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($maintenance->photo_path)
                                                <img src="{{ route('admin.maintenance.photo', basename($maintenance->photo_path)) }}"
                                                     class="w-16 h-16 object-cover rounded shadow-sm"
                                                     alt="Maintenance photo">
                                            @else
                                                <div class="w-16 h-16 bg-gray-200 rounded flex items-center justify-center">
                                                    <span class="text-gray-400 text-xs">No photo</span>
                                                </div>
                                            @endif
                                        </td>

                                        <!-- Lease Info -->
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">Room: {{ $maintenance->lease->room->room_no }}</div>
                                            <div class="text-sm text-gray-500">{{ $maintenance->lease->tenant->user->name }}</div>
                                        </td>

                                        <!-- Asset -->
                                        <td class="px-6 py-4">
                                            @if($maintenance->asset)
                                                <div class="text-sm text-gray-900">{{ $maintenance->asset->name }}</div>
                                                <div class="text-xs text-gray-500">{{ $maintenance->asset->condition }}</div>
                                            @else
                                                <span class="text-sm text-gray-400">-</span>
                                            @endif
                                        </td>

                                        <!-- Title & Description -->
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">{{ $maintenance->title }}</div>
                                            <div class="text-sm text-gray-500">
                                                {{ Str::limit($maintenance->desc, 50) }}
                                            </div>
                                        </td>

                                        <!-- Status -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($maintenance->status === 'Pending')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                    Pending
                                                </span>
                                            @elseif($maintenance->status === 'Fixing')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 text-amber-800">
                                                    Fixing
                                                </span>
                                            @elseif($maintenance->status === 'Resolved')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Resolved
                                                </span>
                                            @endif
                                        </td>

                                        <!-- Cost -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            RM {{ number_format($maintenance->cost / 100, 2) }}
                                        </td>

                                        <!-- Paid By -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($maintenance->paid_by === 'Owner')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Owner
                                                </span>
                                            @elseif($maintenance->paid_by === 'Tenant')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                    Tenant
                                                </span>
                                            @endif
                                        </td>

                                        <!-- Created -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $maintenance->created_at->format('d M Y') }}
                                        </td>

                                        <!-- Actions -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                            <a href="{{ route('admin.maintenance.show', $maintenance) }}"
                                               class="text-indigo-600 hover:text-indigo-900">View</a>
                                            <a href="{{ route('admin.maintenance.edit', $maintenance) }}"
                                               class="text-green-600 hover:text-green-900">Edit</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        {{ $maintenances->links() }}
                    </div>
                @else
                    <div class="text-center py-12">
                        <svg class="mx-auto h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No maintenance requests found</h3>
                        <p class="mt-1 text-sm text-gray-500">Get started by creating a new maintenance request.</p>
                        <div class="mt-6">
                            <a href="{{ route('admin.maintenance.create') }}"
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Add New Maintenance
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
