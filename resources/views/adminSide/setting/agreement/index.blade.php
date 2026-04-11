
<style>
    /* 重点：限制连续换行产生的空白高度 */
    .tos-content {
        /* 如果内容里有连续换行，这个属性可以防止它们撑得太开 */
        display: block;
    }
    
    /* 打印时的特殊处理 */
    @media print {
        .tos-content { font-size: 12pt; color: black; }
    }
</style>
<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Agreements & T&C</h1>
                    <p class="mt-2 text-sm text-gray-500">View and manage your active service agreements and lease templates.</p>
                </div>
                <div class="flex gap-3">
                    @can('owner-admin')
                    <a href="{{ route('admin.agreements.create') }}" 
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-medium text-sm text-white hover:bg-indigo-700 shadow-sm transition-all">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        New Version
                    </a>
                    @endcan
                </div>
            </div>

            @if($agreements && $agreements->count() > 0)
                <div class="space-y-8">
                    @foreach($agreements as $agreement)
                        <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden transition-all hover:shadow-lg">
                            
                            <div class="px-6 py-4 bg-slate-50 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                                <div class="flex items-center gap-4">
                                    <div class="p-2 bg-white rounded-lg shadow-sm border border-gray-100">
                                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-bold text-slate-900">{{ $agreement->title }}</h2>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-xs font-mono text-gray-500">v{{ $agreement->version }}</span>
                                            <span class="text-xs px-2 py-0.5 rounded-full {{ $agreement->type === 'tos' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                                                {{ $agreement->type === 'tos' ? 'System T&C' : 'Lease Template' }}
                                            </span>
                                            @if($agreement->is_active)
                                                <span class="flex items-center text-[10px] font-bold text-emerald-600 uppercase tracking-widest">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-600 mr-1 animate-pulse"></span>
                                                    Active
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button onclick="window.print()" class="p-2 text-gray-400 hover:text-slate-600 hover:bg-gray-100 rounded-lg transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="px-8 bg-white p-10 shadow-sm border rounded-lg">
                                <div class="tos-content text-slate-700 leading-relaxed text-justify" 
                                    style="white-space: pre-line; font-family: serif;">
                                    {{ $agreement->content }}
                                </div>
                            </div>

                            <div class="px-8 py-3 bg-gray-50 border-t border-gray-100 flex justify-between items-center text-[11px] text-gray-400 font-medium italic">
                                <span>Target: {{ $agreement->owner ? $agreement->owner->user->name : 'Global System' }}</span>
                                <span>Last Modified: {{ $agreement->updated_at->format('Y-m-d H:i') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
                
                <div class="mt-8">
                    {{ $agreements->links() }}
                </div>
            @else
                <div class="text-center py-24 bg-white rounded-xl border-2 border-dashed border-gray-200">
                    <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <h3 class="text-lg font-medium text-slate-900">No agreements available</h3>
                    <p class="text-gray-500">Create your first legal template to protect your business.</p>
                </div>
            @endif
        </div>
    </div>

    <style>
        /* 让长协议内部滚动的样式更精致 */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
    </style>
</x-app-layout>