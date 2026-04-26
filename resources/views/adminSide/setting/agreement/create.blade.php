<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen" x-data="{ agreementType: 'tos' }">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="mb-6">
                <a href="{{ route('admin.agreements.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to List
                </a>
                <h1 class="text-2xl font-bold text-slate-900 mt-2">Create New Agreement Template</h1>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <form action="{{ route('admin.agreements.store') }}" method="POST" class="p-8">
                    @csrf
                    
                    <div class="space-y-6">
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <label for="type" class="block text-sm font-semibold text-gray-700">Agreement Type</label>
                            <select name="type" id="type" x-model="agreementType" required 
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @if ($isOwnerAgentAdmin === false)
                                    <option value="tos">Register T&C (Terms of Service)</option>
                                    <option value="privacy">Register Privacy Policy</option>
                                @else
                                    <option value="rental_lease">Lease Agreement</option>
                                @endif
                            </select>
                            <p class="mt-2 text-xs text-gray-500 italic">Select the category of this document.</p>
                            @error('type') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div x-show="agreementType === 'rental_lease'" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 transform -translate-y-2"
                             x-transition:enter-end="opacity-100 transform translate-y-0"
                             class="pb-4 border-b border-gray-100">
                            <label for="owner_id" class="block text-sm font-semibold text-gray-700">Assign to Owner</label>
                            <select name="owner_id" id="owner_id" :required="agreementType === 'rental_lease'"
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">-- Select Owner --</option>
                                @foreach($owners as $owner)
                                    <option value="{{ $owner->id }}" {{ old('owner_id') == $owner->id ? 'selected' : '' }}>
                                        {{ $owner->user->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('owner_id') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="md:col-span-2">
                                <label for="title" class="block text-sm font-semibold text-gray-700">Agreement Title</label>
                                <input type="text" name="title" id="title" value="{{ old('title') }}" placeholder="e.g. Standard 1-Year Lease" required 
                                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('title') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="version" class="block text-sm font-semibold text-gray-700">Version</label>
                                <input type="text" name="version" id="version" value="{{ old('version') }}" placeholder="e.g. 1.0.0" 
                                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('version') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label for="content" class="block text-sm font-semibold text-gray-700">Core Content</label>
                                <span class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest bg-indigo-50 px-2 py-0.5 rounded">LongText Supported</span>
                            </div>
                            <textarea name="content" id="content" rows="12" required placeholder="Enter the legal terms here..."
                                      class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-serif">{{ old('content') }}</textarea>
                            <div class="mt-3 flex items-start gap-2 text-xs text-gray-500 italic">
                                <svg class="w-4 h-4 text-indigo-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                <p>You can use placeholders like <span class="font-mono font-bold text-indigo-600">{owner_name}</span>, <span class="font-mono font-bold text-indigo-600">{tenant_name}</span>, or <span class="font-mono font-bold text-indigo-600">{property_address}</span>. These will be replaced automatically when generating a lease.</p>
                            </div>
                            @error('content') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex items-center gap-2 pt-2">
                            <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <label for="is_active" class="text-sm font-medium text-gray-700">Set as active version immediately</label>
                        </div>

                        <div class="pt-6 border-t border-gray-100 flex justify-end gap-3">
                            <a href="{{ route('admin.agreements.index') }}" class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                Cancel
                            </a>
                            <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-md transition-all">
                                Save Agreement Template
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>