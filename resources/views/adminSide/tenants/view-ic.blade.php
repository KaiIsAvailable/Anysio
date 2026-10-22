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
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">
                {{ $tenant->tenant_type === 'corporate' ? 'SSM Certificate Preview' : 'Identity Document Preview' }}
            </h1>
            <p class="mt-2 text-sm text-gray-500">
                Tenant: <span class="font-semibold text-slate-700">{{ $tenant->user->name }}</span> 
                | Document: <span class="font-semibold text-slate-700">
                    @if($tenant->tenant_type === 'corporate')
                        SSM Certificate ({{ $tenant->ssm_number }})
                    @elseif($tenant->ic_number)
                        IC ({{ $tenant->ic_number }})
                    @elseif($tenant->passport)
                        Passport ({{ $tenant->passport }})
                    @else
                        Document Preview
                    @endif
                </span>
            </p>
        </div>
    </x-slot>

    <div class="mt-2">
        <div class="max-w-[95%] mx-auto px-2">
            <div class="bg-slate-900 shadow-2xl rounded-xl border border-slate-800 overflow-hidden flex items-center justify-center p-6" 
                style="height: calc(100vh - 180px); min-height: 600px;">
                
                @php
                    // Select the appropriate file path based on tenant type
                    $filePath = $tenant->tenant_type === 'corporate' ? $tenant->ssm_cert_path : $tenant->ic_photo_path;
                    $filename = $filePath ? basename($filePath) : null;
                    $mimeType = $filePath ? app(\App\Services\FileService::class)->getFileMimeType($filePath) : '';
                @endphp

                @if($filePath)
                    @if($mimeType === 'application/pdf')
                        <object data="{{ route('admin.tenants.ic_photo', ['filename' => $filename]) }}" type="application/pdf" class="w-full h-full">
                            <a href="{{ route('admin.tenants.ic_photo', ['filename' => $filename]) }}" target="_blank" rel="noopener" class="text-white underline">Open PDF in New Tab</a>
                        </object>
                    @else
                        <img 
                            src="{{ route('admin.tenants.ic_photo', ['filename' => $filename]) }}" 
                            class="max-w-full max-h-full object-contain rounded-lg shadow-lg select-none"
                            alt="Document Preview"
                            draggable="false">
                    @endif
                @else
                    <div class="text-center text-gray-400">
                        <p class="text-lg font-medium">No document uploaded.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>