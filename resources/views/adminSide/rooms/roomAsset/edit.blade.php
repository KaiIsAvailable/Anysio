<x-app-layout>
    <div class="bg-gray-50 min-h-screen p-6">
        <div class="max-w-2xl mx-auto">
            {{-- Header --}}
            <div class="mb-6">
                <a href="{{ route('admin.roomAsset.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to List
                </a>
                <h1 class="text-2xl font-bold text-slate-900 mt-2">Edit Asset</h1>
                <p class="text-gray-500 text-sm">Update information for this specific item.</p>
            </div>

            {{-- Edit Form --}}
            <form action="{{ route('admin.roomAsset.update', $asset->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100">
                    <div class="px-6 py-5 border-b border-gray-50 bg-white">
                        <h2 class="text-lg font-semibold text-slate-900">Asset Details</h2>
                    </div>

                    <div class="p-6 space-y-5">
                        {{-- Asset Name --}}
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Asset Name</label>
                            <input type="text" 
                                   name="name" 
                                   value="{{ old('name', $asset->name) }}"
                                   class="w-full rounded-lg {{ $errors->has('name') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-indigo-500 shadow-sm' }}" 
                                   placeholder="e.g. Fridge" 
                                   required>
                            @error('name')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Category --}}
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                            <select name="category" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                @foreach(['General', 'Furniture', 'Appliances', 'Electronics'] as $cat)
                                    <option value="{{ $cat }}" {{ old('category', $asset->category) == $cat ? 'selected' : '' }}>
                                        {{ $cat }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Optional: Show who this belongs to (Read Only) --}}
                        <div class="pt-4 border-t border-gray-50">
                            <p class="text-xs text-gray-400 uppercase tracking-widest font-semibold">Owner Info</p>
                            <p class="text-sm text-slate-600 mt-1">{{ $asset->user->name }} ({{ ucfirst($asset->user->role) }})</p>
                        </div>
                    </div>

                    <div class="px-6 py-5 bg-gray-50 border-t border-gray-100 flex justify-end">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-8 rounded-lg shadow-md transition transform active:scale-95 focus:outline-none">
                            Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>