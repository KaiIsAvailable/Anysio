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
                <h1 class="text-2xl font-bold text-slate-900 font-sans whitespace-nowrap">Create Tenant</h1>

                <!-- Right: Spacer for centering -->
                <div class="flex-1"></div>
            </div>

            <div class="bg-white shadow-lg rounded-xl p-6">
                <form action="{{ route('admin.tenants.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <!-- User Details -->
                    <div class="mb-6 border-b pb-4">
                        <h2 class="text-xl font-semibold text-slate-800 mb-4">User Details</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Name -->
                            <div class="mb-4">
                                <label for="name" class="block text-sm font-medium text-slate-900 mb-1">Full Name</label>
                                <input type="text" name="name" id="name" value="{{ old('name') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                                @error('name') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <!-- Email -->
                            <div class="mb-4">
                                <label for="email" class="block text-sm font-medium text-slate-900 mb-1">Email Address</label>
                                <input type="email" name="email" id="email" value="{{ old('email') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                                @error('email') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <!-- Password -->
                            <div class="mb-4">
                                <label for="password" class="block text-sm font-medium text-slate-900 mb-1">Password</label>
                                <input type="password" name="password" id="password" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                                @error('password') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <!-- Convert Password -->
                            <div class="mb-4">
                                <label for="password_confirmation" class="block text-sm font-medium text-slate-900 mb-1">Confirm Password</label>
                                <input type="password" name="password_confirmation" id="password_confirmation" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                            </div>
                        </div>
                    </div>

                    <h2 class="text-xl font-semibold text-slate-800 mb-4">Tenant Details</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Phone -->
                        <div class="mb-4">
                            <label for="phone" class="block text-sm font-medium text-slate-900 mb-1">Phone</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                            @error('phone') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Nationality -->
                        <div class="mb-4">
                            <label for="nationality" class="block text-sm font-medium text-slate-900 mb-1">Nationality</label>
                            <input type="text" name="nationality" id="nationality" value="{{ old('nationality') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                            @error('nationality') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Gender -->
                        <div class="mb-4">
                            <label for="gender" class="block text-sm font-medium text-slate-900 mb-1">Gender</label>
                            <select name="gender" id="gender" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                                <option value="">-- Select Gender --</option>
                                <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                                <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                            </select>
                            @error('gender') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Occupation -->
                        <div class="mb-4">
                            <label for="occupation" class="block text-sm font-medium text-slate-900 mb-1">Occupation</label>
                            <input type="text" name="occupation" id="occupation" value="{{ old('occupation') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            @error('occupation') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>

                         <!-- IC Number -->
                         <div class="mb-4">
                            <label for="ic_number" class="block text-sm font-medium text-slate-900 mb-1">IC Number (if Malaysian)</label>
                            <input type="text" name="ic_number" id="ic_number" value="{{ old('ic_number') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            @error('ic_number') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Passport -->
                        <div class="mb-4">
                            <label for="passport" class="block text-sm font-medium text-slate-900 mb-1">Passport (if Non-Malaysian)</label>
                            <input type="text" name="passport" id="passport" value="{{ old('passport') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            @error('passport') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Photo -->
                    <div class="mb-6">
                        <label for="ic_photo_path" class="block text-sm font-medium text-slate-900 mb-1">IC/Passport Photo</label>
                        <input type="file" name="ic_photo_path" id="ic_photo_path" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        @error('ic_photo_path') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg shadow transition duration-150 ease-in-out">
                            Save Tenant
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
