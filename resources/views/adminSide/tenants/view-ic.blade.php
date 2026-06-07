<x-app-layout>
    <x-slot name="header">
        <div>
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <a href="{{ route('admin.tenants.show', $tenant->id) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 flex items-center transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to Tenant Details
                </a>
            </nav>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Identity Document Preview</h1>
            <p class="mt-2 text-sm text-gray-500">
                Tenant: <span class="font-semibold text-slate-700">{{ $tenant->user->name }}</span> 
                | Document: <span class="font-semibold text-slate-700">
                    @if($tenant->ic_number)
                        IC ({{ $tenant->ic_number }})
                    @elseif($tenant->passport)
                        Passport ({{ $tenant->passport }})
                    @else
                        Identity Photo
                    @endif
                </span>
            </p>
        </div>
    </x-slot>

    <div class="mt-2">
        <div class="max-w-[95%] mx-auto px-2">
            <div class="bg-slate-900 shadow-2xl rounded-xl border border-slate-800 overflow-hidden flex items-center justify-center p-6" 
                 style="height: calc(100vh - 180px); min-height: 600px;">
                <img 
                    src="{{ $photoData }}" 
                    class="max-w-full max-h-full object-contain rounded-lg shadow-lg select-none"
                    alt="Identity Document"
                    draggable="false">
            </div>
        </div>
    </div>
</x-app-layout>
