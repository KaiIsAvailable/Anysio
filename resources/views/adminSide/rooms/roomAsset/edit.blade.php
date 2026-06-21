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
            <x-form.form action="{{ route('admin.roomAsset.update', $asset->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100">
                    <div class="px-6 py-5 border-b border-gray-50 bg-white">
                        <h2 class="text-lg font-semibold text-slate-900">Asset Details</h2>
                    </div>

                    <div class="p-6 space-y-5">
                        <div class="flex items-center p-4 bg-gray-50 border border-gray-200 rounded-2xl">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3 text-blue-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            
                            <div>
                                <p class="text-sm font-black text-slate-800">{{ $asset->user->name }}</p>
                                <p class="text-xs text-slate-500 capitalize">{{ ucfirst($asset->user->role) }}</p>
                            </div>
                        </div>
                        {{-- Asset Name --}}
                        <div>
                            <x-form.input-label value="Asset Name" class="mb-1" />
                            <x-form.text-input 
                                name="name" 
                                value="{{ old('name', $asset->name) }}"
                                class="w-full {{ $errors->has('name') ? 'border-red-500 focus:ring-red-500' : '' }}" 
                                placeholder="e.g. Fridge" 
                                required />
                            <x-form.input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>

                        {{-- Category --}}
                        <div>
                            <x-form.input-label value="Category" class="mb-1" />
                            <x-form.input-select 
                                name="category" 
                                :options="['General' => 'General', 'Furniture' => 'Furniture', 'Appliances' => 'Appliances', 'Electronics' => 'Electronics']"
                                :value="old('category', $asset->category)"
                                class="w-full" />
                            <x-form.input-error :messages="$errors->get('category')" class="mt-1" />
                        </div>
                    </div>

                    <div class="px-6 py-5 bg-gray-50 border-t border-gray-100 flex justify-end">
                        <x-form.primary-button loading="loading" class="py-2.5 px-8">
                            Save Changes
                        </x-form.primary-button>
                    </div>
                </div>
            </x-form.form>
        </div>
    </div>
</x-app-layout>