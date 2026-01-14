<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Edit Owner</h1>
                    <p class="mt-2 text-sm text-gray-500">Update property owner profile and contact information.</p>
                </div>
                <a href="{{ route('admin.owners.index') }}" class="inline-flex items-center text-sm font-medium text-gray-500 hover:text-indigo-600 transition-colors">
                    <svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to List
                </a>
            </div>

            <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                <form action="{{ route('admin.owners.update', $owner->id) }}" method="POST" class="p-8">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Owner Name</label>
                            <input type="text" value="{{ $owner->user->name }}" 
                                   class="w-full rounded-lg border-gray-200 bg-gray-50 text-gray-500 shadow-sm cursor-not-allowed" disabled>
                            <input type="hidden" name="user_id" value="{{ $owner->user_id }}">
                            <p class="mt-1 text-xs text-gray-400">Account name is managed via User Settings.</p>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                            <input type="email" name="email" id="email" 
                                   value="{{ old('email', $owner->user->email) }}" 
                                   class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm @error('email') border-red-500 @enderror" 
                                   required>
                            @error('email') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="company_name" class="block text-sm font-semibold text-gray-700 mb-2">Company Name</label>
                                <input type="text" name="company_name" id="company_name" 
                                       value="{{ old('company_name', $owner->company_name) }}" 
                                       placeholder="Individual"
                                       class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                                @error('company_name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="ic_number" class="block text-sm font-semibold text-gray-700 mb-2">IC / Passport Number</label>
                                <input type="text" name="ic_number" id="ic_number" 
                                       value="{{ old('ic_number', $owner->ic_number) }}" 
                                       class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                                @error('ic_number') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                                <input type="text" name="phone" id="phone" 
                                       value="{{ old('phone', $owner->phone) }}" 
                                       class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm" required>
                                @error('phone') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="gender" class="block text-sm font-semibold text-gray-700 mb-2">Gender</label>
                                <select name="gender" id="gender" 
                                        class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm" required>
                                    <option value="">-- Select Gender --</option>
                                    <option value="Male" {{ (old('gender') ?? $owner->gender) == 'Male' ? 'selected' : '' }}>Male</option>
                                    <option value="Female" {{ (old('gender') ?? $owner->gender) == 'Female' ? 'selected' : '' }}>Female</option>
                                </select>
                                @error('gender') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mt-10 flex items-center justify-end space-x-4 border-t border-gray-100 pt-6">
                        <a href="{{ route('admin.owners.index') }}" 
                           class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="px-6 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-md transition-all active:scale-95">
                            Update Owner
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>