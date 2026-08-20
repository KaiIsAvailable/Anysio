@props(['id', 'title', 'route', 'users', 'description', 'templateRoute' => '#'])

<div x-data="{ 
        open: false, 
        shake: false,
        uploading: false,
        {{ $id }}() { this.open = true; } 
     }" 
     @click.window.stop="if ($event.detail.modal === '{{ $id }}') open = true"
     x-show="open" 
     style="display: none;" 
     class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4"
     x-cloak>

    <!-- Blur Overlay Backdrop -->
    <x-ui.blur-overlay 
        show="open" 
        onClose="shake = true; setTimeout(() => shake = false, 400)" 
        zIndex="z-0"
    />

    <!-- Modal Content Box -->
    <div class="relative bg-white rounded-xl shadow-xl max-w-lg w-full p-6 z-10 transition-all"
         :class="{ 'animate-shake': shake }"
         @click.stop>
         
        <!-- Header with Close Button -->
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-lg font-bold text-slate-800">{{ $title }}</h3>
            <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 text-2xl font-light">&times;</button>
        </div>

        <!-- Description & Template Download Link -->
        <div class="flex items-center justify-between mb-4">
            <p class="text-sm text-slate-600">{!! $description !!}</p>
            <a href="{{ $templateRoute }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg transition shrink-0 ml-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                Template
            </a>
        </div>

        <x-form.form action="{{ $route }}" method="POST" enctype="multipart/form-data" @submit="uploading = true">
            @csrf

            <!-- Select Created By User -->
            <div class="mb-4">
                <x-form.input-label for="created_by" value="Assign Created By" required />
                <x-form.input-select 
                    name="created_by" 
                    id="created_by"
                    required 
                    placeholder="Select User"
                    :options="$users"
                    valueField="id"
                    labelField="name"
                    class="mt-1"
                />
            </div>

            <!-- File Input -->
            <div class="mb-4">
                <x-form.input-label for="excel_file" value="Excel File (.xlsx, .xls, .csv)" required />
                <x-form.file-input 
                    name="excel_file" 
                    id="excel_file"
                    required 
                    accept=".xlsx, .xls, .csv"
                    class="mt-1"
                />
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" @click="open = false" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 text-sm font-semibold">Cancel</button>
                <x-form.primary-button type="submit" loading="loading">
                    Import Data
                </x-form.primary-button>
            </div>
        </x-form.form>
    </div>
</div>

@push('styles')
<style>
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-6px); }
    40%, 80% { transform: translateX(6px); }
}
.animate-shake {
    animation: shake 0.4s ease-in-out;
}
</style>
@endpush