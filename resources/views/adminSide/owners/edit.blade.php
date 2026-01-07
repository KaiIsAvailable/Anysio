<x-app-layout>
    <div class="bg-gray-50 min-h-screen p-6">
        <div class="max-w-3xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div class="flex-1 flex justify-start">
                    <a href="{{ route('admin.owners.index') }}" class="inline-flex items-center text-gray-500 hover:text-indigo-600 transition-colors">
                        <svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        Back
                    </a>
                </div>
                
                <h1 class="text-2xl font-bold text-slate-900 font-sans whitespace-nowrap">Edit Owner</h1>

                <div class="flex-1"></div>
            </div>

            <div class="bg-white shadow-lg rounded-xl p-6">
                <form action="{{ route('admin.owners.update', $owner->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-900 mb-1">Owner Name</label>
                        <input type="text" value="{{ $owner->user->name }}" class="w-full rounded-lg border-gray-300 bg-gray-100 text-gray-500 shadow-sm cursor-not-allowed" disabled>
                        <input type="hidden" name="user_id" value="{{ $owner->user_id }}">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label for="company_name" class="block text-sm font-medium text-slate-900 mb-1">Company Name</label>
                            <input type="text" name="company_name" id="company_name" value="{{ old('company_name', $owner->company_name) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            @error('company_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="phone" class="block text-sm font-medium text-slate-900 mb-1">Phone</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone', $owner->phone) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                            @error('phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="ic_number" class="block text-sm font-medium text-slate-900 mb-1">IC Number</label>
                            <input type="text" name="ic_number" id="ic_number" value="{{ old('ic_number', $owner->ic_number) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            @error('ic_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="gender" class="block text-sm font-medium text-slate-900 mb-1">Gender</label>
                            <select name="gender" id="gender" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                                <option value="Male" {{ (old('gender', $owner->gender)) == 'Male' ? 'selected' : '' }}>Male</option>
                                <option value="Female" {{ (old('gender', $owner->gender)) == 'Female' ? 'selected' : '' }}>Female</option>
                            </select>
                            @error('gender') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="subscription_status" class="block text-sm font-medium text-slate-900 mb-1">Subscription Status</label>
                            <select name="subscription_status" id="subscription_status" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                <option value="Active" {{ (old('subscription_status', $owner->subscription_status)) == 'Active' ? 'selected' : '' }}>Active</option>
                                <option value="Inactive" {{ (old('subscription_status', $owner->subscription_status)) == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('subscription_status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="referred_by" class="block text-sm font-medium text-slate-900 mb-1">Referred By</label>
                            <input type="text" name="referred_by" id="referred_by" value="{{ old('referred_by', $owner->referred_by) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            @error('referred_by') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="discount_rate" class="block text-sm font-medium text-slate-900 mb-1">Discount Rate (%)</label>
                            <input type="number" step="0.01" name="discount_rate" id="discount_rate" value="{{ old('discount_rate', $owner->discount_rate) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            @error('discount_rate') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="usage_count" class="block text-sm font-medium text-slate-900 mb-1">Usage Count</label>
                            <input type="number" name="usage_count" id="usage_count" value="{{ old('usage_count', $owner->usage_count) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            @error('usage_count') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg shadow transition duration-150 ease-in-out">
                            Update Owner
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>