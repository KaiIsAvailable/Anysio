<x-app-layout>
    {{-- Header 部分保持不变 --}}
    <x-slot name="header">
        <div>
            <nav class="flex mb-2" aria-label="Breadcrumb">
                {{-- 这里改为回到当前租约的详情页，而不是列表页，用户体验更好 --}}
                <a href="{{ route('admin.leases.show', $lease->id) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 flex items-center transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to Lease Details
                </a>
            </nav>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">LHDN Stamping Certificate</h1>
            <p class="mt-2 text-sm text-gray-500">
                Ref: <span class="font-semibold text-slate-700">{{ $lease->stamping_reference_no ?? 'N/A' }}</span> 
                | Stamped on: <span class="font-semibold text-slate-700">{{ $lease->stamped_at ? $lease->stamped_at->format('d M Y') : 'N/A' }}</span>
            </p>
        </div>
    </x-slot>

    {{-- 去掉 py-6，改为小的 mt-2，尽量留出空间给 PDF --}}
    <div class="mt-2">
        <div class="max-w-[95%] mx-auto px-2"> {{-- 增加宽度到 95% --}}
            
            {{-- 关键修改点：高度使用 calc 计算 --}}
            <div class="bg-white shadow-2xl rounded-xl border border-gray-100 overflow-hidden" 
                 style="height: calc(100vh - 180px); min-height: 600px;">
                
                <iframe 
                    src="{{ $pdfData }}" 
                    class="w-full h-full border-none block"
                    style="display: block; width: %; height: 100%;"
                    title="Stamping Certificate">
                </iframe>
            </div>
        </div>
    </div>
</x-app-layout>