<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Breadcrumb -->
            <div class="mb-6">
                <a href="{{ route('admin.maintenance.index') }}" class="text-indigo-600 hover:text-indigo-900">
                    ← Back to Maintenance List
                </a>
            </div>

            <!-- Header -->
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Create Maintenance Request</h2>

            <!-- Form -->
            <form method="POST" action="{{ route('admin.maintenance.store') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <!-- Lease Information Section -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Lease Information</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Lease Dropdown -->
                        <div>
                            <label for="lease_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Lease <span class="text-red-500">*</span>
                            </label>
                            <select name="lease_id"
                                    id="lease_id"
                                    required
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('lease_id') border-red-500 @enderror">
                                <option value="">Select Lease</option>
                                @foreach($leases as $lease)
                                    <option value="{{ $lease->id }}" {{ old('lease_id', $selectedLeaseId) == $lease->id ? 'selected' : '' }}>
                                        Room: {{ $lease->room->room_no }} - {{ $lease->tenant->user->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('lease_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Asset Dropdown -->
                        <div>
                            <label for="asset_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Asset (Optional)
                            </label>
                            <select name="asset_id"
                                    id="asset_id"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('asset_id') border-red-500 @enderror">
                                <option value="">Select Asset (Optional)</option>
                                @foreach($selectedAssets as $asset)
                                    <option value="{{ $asset->id }}" {{ old('asset_id') == $asset->id ? 'selected' : '' }}>
                                        {{ $asset->name }} ({{ $asset->condition }})
                                    </option>
                                @endforeach
                            </select>
                            @error('asset_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Maintenance Details Section -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Maintenance Details</h3>

                    <!-- Title -->
                    <div class="mb-6">
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">
                            Title <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               name="title"
                               id="title"
                               value="{{ old('title') }}"
                               required
                               placeholder="e.g., Chair broken, Water leaking..."
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('title') border-red-500 @enderror">
                        @error('title')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Description -->
                    <div class="mb-6">
                        <label for="desc" class="block text-sm font-medium text-gray-700 mb-1">
                            Description <span class="text-red-500">*</span>
                        </label>
                        <textarea name="desc"
                                  id="desc"
                                  rows="4"
                                  required
                                  placeholder="Provide more details about the maintenance issue..."
                                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('desc') border-red-500 @enderror">{{ old('desc') }}</textarea>
                        @error('desc')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Photo Upload -->
                    <div>
                        <label for="photo_path" class="block text-sm font-medium text-gray-700 mb-1">
                            Photo (Optional)
                        </label>
                        <input type="file"
                               name="photo_path"
                               id="photo_path"
                               accept="image/*"
                               class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 @error('photo_path') border-red-500 @enderror">
                        @error('photo_path')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">Max size: 5MB. Formats: JPEG, PNG, JPG, GIF</p>

                        <!-- Photo Preview -->
                        <div id="photo_preview_container" class="mt-4 hidden">
                            <img id="photo_preview" src="" alt="Photo preview" class="max-w-md rounded-lg shadow-md">
                        </div>
                    </div>
                </div>

                <!-- Status & Cost Section -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Status & Cost</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Status -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                                Status <span class="text-red-500">*</span>
                            </label>
                            <select name="status"
                                    id="status"
                                    required
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('status') border-red-500 @enderror">
                                @foreach($statusOptions as $statusOption)
                                    <option value="{{ $statusOption }}" {{ old('status', 'Pending') == $statusOption ? 'selected' : '' }}>
                                        {{ $statusOption }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Cost -->
                        <div>
                            <label for="cost" class="block text-sm font-medium text-gray-700 mb-1">
                                Cost (RM) <span class="text-red-500">*</span>
                            </label>
                            <input type="number"
                                   name="cost"
                                   id="cost"
                                   value="{{ old('cost', '0.00') }}"
                                   step="0.01"
                                   min="0"
                                   required
                                   placeholder="0.00"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('cost') border-red-500 @enderror">
                            @error('cost')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Paid By -->
                        <div>
                            <label for="paid_by" class="block text-sm font-medium text-gray-700 mb-1">
                                Paid By <span class="text-red-500">*</span>
                            </label>
                            <select name="paid_by"
                                    id="paid_by"
                                    required
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('paid_by') border-red-500 @enderror">
                                <option value="">Select Who Pays</option>
                                @foreach($paidByOptions as $paidByOption)
                                    <option value="{{ $paidByOption }}" {{ old('paid_by') == $paidByOption ? 'selected' : '' }}>
                                        {{ $paidByOption }}
                                    </option>
                                @endforeach
                            </select>
                            @error('paid_by')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end space-x-3">
                    <a href="{{ route('admin.maintenance.index') }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 focus:bg-gray-400 active:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Cancel
                    </a>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Create Maintenance
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        // Lease selection handler - Load assets via AJAX
        document.getElementById('lease_id').addEventListener('change', function() {
            const leaseId = this.value;
            const assetDropdown = document.getElementById('asset_id');

            // Clear current options
            assetDropdown.innerHTML = '<option value="">Select Asset (Optional)</option>';

            if (!leaseId) return;

            // Fetch assets via AJAX
            fetch(`/admin/maintenance/assets-by-lease?lease_id=${leaseId}`)
                .then(response => response.json())
                .then(assets => {
                    if (assets.length === 0) {
                        assetDropdown.innerHTML = '<option value="">No assets available</option>';
                        return;
                    }

                    assets.forEach(asset => {
                        const option = document.createElement('option');
                        option.value = asset.id;
                        option.textContent = `${asset.name} (${asset.condition})`;
                        assetDropdown.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error fetching assets:', error);
                    assetDropdown.innerHTML = '<option value="">Error loading assets</option>';
                });
        });

        // Photo preview handler
        document.getElementById('photo_path').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const previewContainer = document.getElementById('photo_preview_container');
            const preview = document.getElementById('photo_preview');

            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            } else {
                previewContainer.classList.add('hidden');
            }
        });

        // Trigger asset loading if lease pre-selected (e.g., from validation errors)
        window.addEventListener('DOMContentLoaded', function() {
            const leaseDropdown = document.getElementById('lease_id');
            if (leaseDropdown.value) {
                leaseDropdown.dispatchEvent(new Event('change'));
            }
        });
    </script>
    @endpush
</x-app-layout>
