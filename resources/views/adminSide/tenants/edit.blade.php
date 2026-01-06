<x-app-layout>
    <div class="bg-gray-50 min-h-screen p-6">
        <div class="max-w-3xl mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <!-- Left: Back Button -->
                <div class="flex-1 flex justify-start">
                    <a href="{{ route('admin.tenants.index') }}" class="inline-flex items-center text-gray-500 hover:text-indigo-600 transition-colors">
                        <svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        Back
                    </a>
                </div>
                
                <!-- Center: Title -->
                <h1 class="text-2xl font-bold text-slate-900 font-sans whitespace-nowrap">Edit Tenant</h1>

                <!-- Right: Spacer for centering -->
                <div class="flex-1"></div>
            </div>

            <div class="bg-white shadow-lg rounded-xl p-6">
                <form action="{{ route('admin.tenants.update', $tenant->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <!-- User Selection -->
                    <!-- User Tenant Name (Disabled) -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-900 mb-1">Tenant Name</label>
                        <input type="text" value="{{ $tenant->user->name }}" class="w-full rounded-lg border-gray-300 bg-gray-100 text-gray-500 shadow-sm cursor-not-allowed" disabled>
                        <input type="hidden" name="user_id" value="{{ $tenant->user_id }}">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Phone -->
                        <div class="mb-4">
                            <label for="phone" class="block text-sm font-medium text-slate-900 mb-1">Phone</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone', $tenant->phone) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                            @error('phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Nationality -->
                        <div class="mb-4">
                            <label for="nationality" class="block text-sm font-medium text-slate-900 mb-1">Nationality</label>
                            <input type="text" name="nationality" id="nationality" value="{{ old('nationality', $tenant->nationality) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                            @error('nationality') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Gender -->
                        <div class="mb-4">
                            <label for="gender" class="block text-sm font-medium text-slate-900 mb-1">Gender</label>
                            <select name="gender" id="gender" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                                <option value="">-- Select Gender --</option>
                                <option value="Male" {{ (old('gender') ?? $tenant->gender) == 'Male' ? 'selected' : '' }}>Male</option>
                                <option value="Female" {{ (old('gender') ?? $tenant->gender) == 'Female' ? 'selected' : '' }}>Female</option>
                            </select>
                            @error('gender') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Occupation -->
                        <div class="mb-4">
                            <label for="occupation" class="block text-sm font-medium text-slate-900 mb-1">Occupation</label>
                            <input type="text" name="occupation" id="occupation" value="{{ old('occupation', $tenant->occupation) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            @error('occupation') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                         <!-- IC Number -->
                         <div class="mb-4">
                            <label for="ic_number" class="block text-sm font-medium text-slate-900 mb-1">IC Number (if Malaysian)</label>
                            <input type="text" name="ic_number" id="ic_number" value="{{ old('ic_number', $tenant->ic_number) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            @error('ic_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Passport -->
                        <div class="mb-4">
                            <label for="passport" class="block text-sm font-medium text-slate-900 mb-1">Passport (if Non-Malaysian)</label>
                            <input type="text" name="passport" id="passport" value="{{ old('passport', $tenant->passport) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            @error('passport') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Photo -->
                    <div class="mb-6">
                        <label for="ic_photo_path" class="block text-sm font-medium text-slate-900 mb-1">IC/Passport Photo</label>
                        
                        @if($tenant->ic_photo_path)
                            <div class="mb-2">
                                <img src="{{ asset('storage/' . $tenant->ic_photo_path) }}" alt="Current Photo" class="h-40 w-40 object-cover rounded-lg border border-gray-200" style="width: 150px; height: 150px;">
                            </div>
                        @endif

                        <input type="file" name="ic_photo_path" id="ic_photo_path" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="text-xs text-gray-500 mt-1">Leave empty to keep current photo.</p>
                        @error('ic_photo_path') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg shadow transition duration-150 ease-in-out">
                            Update Tenant
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
