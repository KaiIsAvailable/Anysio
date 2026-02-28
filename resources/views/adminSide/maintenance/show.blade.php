<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Breadcrumb -->
            <div class="mb-6">
                <a href="{{ route('admin.maintenance.index') }}" class="text-indigo-600 hover:text-indigo-900">
                    ← Back to Maintenance List
                </a>
            </div>

            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Maintenance Details</h2>
                <a href="{{ route('admin.maintenance.edit', $maintenance) }}"
                   class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Edit Maintenance
                </a>
            </div>

            <!-- Success Message -->
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column: Maintenance Information -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Maintenance Info Card -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Maintenance Information</h3>

                        <!-- Photo -->
                        <div class="mb-6">
                            @if($maintenance->photo_path)
                                <img src="{{ route('admin.maintenance.photo', basename($maintenance->photo_path)) }}"
                                     class="max-w-2xl w-full rounded-lg shadow-md"
                                     alt="{{ $maintenance->title }}">
                            @else
                                <div class="max-w-2xl w-full h-64 bg-gray-200 rounded-lg flex items-center justify-center">
                                    <span class="text-gray-400">No photo uploaded</span>
                                </div>
                            @endif
                        </div>

                        <!-- Title -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                            <p class="text-xl font-bold text-gray-900">{{ $maintenance->title }}</p>
                        </div>

                        <!-- Description -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <p class="text-gray-700 whitespace-pre-line">{{ $maintenance->desc }}</p>
                        </div>

                        <!-- Status, Cost, Paid By -->
                        <div class="grid grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                @if($maintenance->status === 'Pending')
                                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        Pending
                                    </span>
                                @elseif($maintenance->status === 'Fixing')
                                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-amber-100 text-amber-800">
                                        Fixing
                                    </span>
                                @elseif($maintenance->status === 'Resolved')
                                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Resolved
                                    </span>
                                @endif
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Cost</label>
                                <p class="text-lg font-semibold text-gray-900">RM {{ number_format($maintenance->cost / 100, 2) }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Paid By</label>
                                @if($maintenance->paid_by === 'Owner')
                                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Owner
                                    </span>
                                @elseif($maintenance->paid_by === 'Tenant')
                                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        Tenant
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Timestamps -->
                        <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-200">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Created At</label>
                                <p class="text-sm text-gray-600">{{ $maintenance->created_at->format('d M Y, h:i A') }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Updated At</label>
                                <p class="text-sm text-gray-600">{{ $maintenance->updated_at->format('d M Y, h:i A') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Lease Information Card -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Lease Information</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Room</label>
                                <p class="text-gray-900">{{ $maintenance->lease->room->room_no }}</p>
                                <p class="text-sm text-gray-500">{{ $maintenance->lease->room->room_type }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                <p class="text-sm text-gray-900">{{ $maintenance->lease->room->address }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tenant</label>
                                <p class="text-gray-900">{{ $maintenance->lease->tenant->user->name }}</p>
                                <p class="text-sm text-gray-500">{{ $maintenance->lease->tenant->user->email }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tenant IC</label>
                                <p class="text-gray-900">{{ $maintenance->lease->tenant->ic_number ?? 'N/A' }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Lease Period</label>
                                <p class="text-gray-900">
                                    {{ $maintenance->lease->start_date->format('d M Y') }} -
                                    {{ $maintenance->lease->end_date->format('d M Y') }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Owner</label>
                                <p class="text-gray-900">{{ $maintenance->lease->room->owner->user->name }}</p>
                                <p class="text-sm text-gray-500">{{ $maintenance->lease->room->owner->phone }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Asset Information -->
                <div class="lg:col-span-1">
                    @if($maintenance->asset)
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Asset Information</h3>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Asset Name</label>
                                    <p class="text-gray-900">{{ $maintenance->asset->name }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Condition</label>
                                    <p class="text-gray-900">{{ $maintenance->asset->condition }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Maintenance</label>
                                    <p class="text-gray-900">
                                        {{ $maintenance->asset->last_maintenance ? $maintenance->asset->last_maintenance->format('d M Y') : 'N/A' }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Remark</label>
                                    <p class="text-gray-900">{{ $maintenance->asset->remark ?? '-' }}</p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Asset Information</h3>
                            <p class="text-gray-500 text-center py-8">No asset linked to this maintenance request.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
