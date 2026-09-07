<style>
    /* 重点：限制连续换行产生的空白高度 */
    .tos-content {
        display: block;
    }

    /* 打印时的特殊处理 */
    @media print {
        .tos-content {
            font-size: 12pt;
            color: black;
        }
    }
    
    /* 美化滾動條 (選用) */
    .custom-scrollbar::-webkit-scrollbar {
        width: 8px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f1f5f9; 
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1; 
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #94a3b8; 
    }
</style>
<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                <div>
                    {{-- 💡 修改标题为更通用的 Document Templates --}}
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Document Templates</h1>
                    <p class="mt-2 text-sm text-gray-500">View and manage your document templates (Agreements, Invoices, Receipts, etc).</p>
                </div>
                <div class="flex-shrink-0" x-data="{loading: false}">
                    @can('owner-admin')
                    <x-form.primary-button
                        type="button"
                        loading="loading"
                        @click="loading = true; window.location.href = '{{ route('admin.document-templates.create') }}'">

                        <svg x-show="!loading" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        New Template
                    </x-form.primary-button>
                    @endcan
                </div>
            </div>

            {{-- ========================================== --}}
            {{-- 💡 分类过滤 Tabs (Filter Tabs) --}}
            {{-- ========================================== --}}
            <div class="mb-6 flex flex-wrap gap-2 border-b border-gray-200 pb-4">
                <a href="{{ route('admin.document-templates.index', ['category' => 'all', 'search' => request('search')]) }}"
                   class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors {{ !$categoryFilter || $categoryFilter === 'all' ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100' }}">
                    All Templates
                </a>
                @foreach($availableCategories as $key => $label)
                    <a href="{{ route('admin.document-templates.index', ['category' => $key, 'search' => request('search')]) }}"
                        class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors {{ $categoryFilter === $key ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
            {{-- ========================================== --}}

            @if($agreements && $agreements->count() > 0)
            <div class="space-y-6">
                @foreach($agreements as $agreement)
                <div x-data="{ 
                                expanded: {{ request('expanded_id') == $agreement->id ? 'true' : 'false' }},
                                currentVersion: '{{ $agreement->version }}',
                                currentContent: {{ json_encode($agreement->html_template ?? '') }},
                                currentId: '{{ $agreement->id }}',
                                currentStatus: '{{ $agreement->status }}', /* 🌟 1. 這裡新增 currentStatus 追蹤目前預覽的狀態 */
                                statusUpdating: false
                            }"
                    class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden transition-all hover:shadow-md">

                    <div class="px-6 py-4 bg-slate-50 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 cursor-pointer">
                        <div class="flex items-center gap-4">
                            <div class="flex flex-col">
                                {{-- ========================================== --}}
                                {{-- 💡 彩色分类徽章 (Category Badge) --}}
                                {{-- ========================================== --}}
                                @php
                                    $catColors = [
                                        'agreement' => 'bg-blue-100 text-blue-700 border-blue-200',
                                        'invoice'   => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                        'receipt'   => 'bg-amber-100 text-amber-700 border-amber-200',
                                        'tos'       => 'bg-purple-100 text-purple-700 border-purple-200',
                                        'privacy'   => 'bg-rose-100 text-rose-700 border-rose-200',
                                    ];
                                    $badgeClass = $catColors[$agreement->category] ?? 'bg-gray-100 text-gray-700 border-gray-200';
                                    $catLabel = $availableCategories[$agreement->category] ?? strtoupper($agreement->category);
                                @endphp
                                <div class="mb-1.5">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border {{ $badgeClass }}">
                                        {{ $catLabel }}
                                    </span>
                                </div>
                                {{-- ========================================== --}}

                                <h2 class="text-xl font-bold text-slate-900">{{ $agreement->title }}</h2>

                                <div class="flex items-center gap-2 mt-2 flex-wrap">
                                    <span class="text-xs text-gray-400 uppercase font-bold tracking-widest">Version:</span>

                                    {{-- 🌟 2. 主卡片按鈕：點擊時把 currentStatus 也更新 --}}
                                    <button @click.stop="expanded = true; currentVersion = '{{ $agreement->version }}'; currentContent = {{ json_encode($agreement->html_template ?? '') }}; currentId = '{{ $agreement->id }}'; currentStatus = '{{ $agreement->status }}';"
                                        :class="currentVersion === '{{ $agreement->version }}' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                        class="px-2 py-0.5 rounded text-[10px] font-mono transition-colors">
                                        v{{ $agreement->version }} {{ $agreement->status === 'active' ? '(Active)' : '' }}
                                    </button>

                                    {{-- 🌟 3. 歷史版本按鈕：點擊時把 currentStatus 也更新 --}}
                                    @foreach($agreement->full_history as $history)
                                    @if($history->id !== $agreement->id)
                                    <button @click.stop="expanded = true; currentVersion = '{{ $history->version }}'; currentContent = {{ json_encode($history->html_template ?? '') }}; currentId = '{{ $history->id }}'; currentStatus = '{{ $history->status }}';"
                                        :class="currentVersion === '{{ $history->version }}' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                        class="px-2 py-0.5 rounded text-[10px] font-mono transition-colors">
                                        v{{ $history->version }} {{ $history->status === 'active' ? '(Active)' : '' }}
                                    </button>
                                    @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <span class="text-sm font-medium text-indigo-600 flex items-center cursor-pointer select-none" @click="expanded = !expanded">
                                <span x-text="expanded ? 'Click to collapse' : 'Click to preview'"></span>
                                <svg class="w-4 h-4 ml-1 transition-transform duration-300"
                                    :style="expanded ? 'transform: rotate(180deg);' : 'transform: rotate(0deg);'"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </span>

                            <button type="button"
                                @click.stop="printContract($refs.content_{{ $agreement->id }}.innerHTML)"
                                class="p-2 text-gray-400 hover:text-slate-600 hover:bg-gray-100 rounded-lg transition-colors" title="Print Document">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                </svg>
                            </button>

                            <a :href="'{{ route('admin.document-templates.edit', 'PLACEHOLDER') }}'.replace('PLACEHOLDER', currentId)"
                                class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                                title="Edit and create a new version">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <div x-show="expanded"
                        x-collapse
                        class="px-8 bg-white p-10 border-t border-gray-50">

                        <div class="flex justify-between mb-4 border-b pb-2">
                            <span class="text-sm font-bold text-indigo-600">Showing Content: Version <span x-text="currentVersion"></span></span>
                            
                            {{-- 🌟 4. 安全乾淨地使用 currentStatus 判斷，完全避開 HTML 引號衝突 --}}
                            <div class="flex items-center gap-1.5 mt-2">
                                
                                <span class="text-xs text-gray-400 italic">Status:</span>

                                {{-- 隱藏的表單，用於標準提交 --}}
                                <form x-ref="activateForm" method="POST" :action="'{{ route('admin.document-templates.activate', 'PLACEHOLDER') }}'.replace('PLACEHOLDER', currentId)" class="hidden">
                                    @csrf
                                </form>

                                {{-- 🌟 情況 A：已經是 Active 版本 -> 顯示綠色徽章，隱藏 Checkbox --}}
                                <template x-if="currentStatus === 'active'">
                                    <div class="inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 text-[11px] font-bold tracking-wide border border-emerald-200">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        CURRENTLY ACTIVE
                                    </div>
                                </template>

                                {{-- 🌟 情況 B：不是 Active 版本 -> 顯示 Checkbox 讓用戶打勾啟用 --}}
                                <template x-if="currentStatus !== 'active'">
                                    <label class="inline-flex items-center cursor-pointer group hover:bg-gray-50 px-2 py-1 rounded transition-colors">
                                        <input type="checkbox"
                                            class="form-checkbox h-3.5 w-3.5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 transition duration-150 ease-in-out cursor-pointer disabled:opacity-50"
                                            :disabled="statusUpdating"
                                            @change="statusUpdating = true; $refs.activateForm.submit();">

                                        <span class="ml-1.5 text-[11px] font-medium text-gray-500 group-hover:text-indigo-600 transition-colors"
                                            x-text="statusUpdating ? 'Updating...' : 'Set as Active'">
                                        </span>
                                    </label>
                                </template>

                            </div>
                        </div>

                        {{-- 🌟 加入 max-h-[60vh] 和 overflow-y-auto 來限制高度並啟用獨立滾動條 --}}
                        <div class="tos-content custom-scrollbar text-slate-700 leading-relaxed quill-content max-h-[60vh] overflow-y-auto pr-4 border border-gray-100 rounded-lg p-6 bg-white shadow-inner"
                            x-ref="content_{{ $agreement->id }}"
                            x-html="currentContent"
                            style="font-family: 'Times New Roman', serif;">
                        </div>
                    </div>

                    <div class="px-8 py-3 bg-gray-50 border-t border-gray-100 flex justify-between items-center text-[11px] text-gray-400 font-medium italic">
                        <span>Owner: {{ $agreement->user->name ?? 'Global System' }}</span>
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
                <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="text-lg font-medium text-slate-900">No templates found</h3>
                <p class="text-gray-500">Create your first document template to get started.</p>
            </div>
            @endif
        </div>
    </div>

    <script>
        // 只需要保留原本的 printContract 函数即可
        function printContract(content) {
            const iframe = document.createElement('iframe');
            iframe.style.position = 'fixed';
            iframe.style.right = '0';
            iframe.style.bottom = '0';
            iframe.style.width = '0';
            iframe.style.height = '0';
            iframe.style.border = '0';
            document.body.appendChild(iframe);

            const doc = iframe.contentWindow.document;
            doc.open();
            doc.write(`
                <html>
                    <head>
                        <title>Print Document</title>
                        <style>
                            /* 基礎字體與排版 */
                            body { font-family: 'Times New Roman', serif; padding: 20px; line-height: 1.6; color: #0f172a; }
                            h1, h2, h3 { text-align: center; text-transform: uppercase; }
                            
                            /* 💡 修正 1：移除全局的 border: 1px solid black，只保留基礎的寬度和無縫貼合 */
                            table { width: 100%; border-collapse: collapse; margin: 0; border-spacing: 0; }
                            td, th { padding: 0; } 
                            
                            .quill-content p { margin-bottom: 1em; }

                            /* 💡 修正 2：專屬列印優化 */
                            @media print {
                                body { padding: 0; }
                                /* 強制瀏覽器列印出背景顏色（例如 Receipt 的綠色背景），否則預設會是全白 */
                                * {
                                    -webkit-print-color-adjust: exact !important;
                                    print-color-adjust: exact !important;
                                    color-adjust: exact !important;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="quill-content">
                            ${content}
                        </div>
                    </body>
                </html>
            `);
            doc.close();

            iframe.contentWindow.focus();
            setTimeout(() => {
                iframe.contentWindow.print();
                document.body.removeChild(iframe);
            }, 500);
        }
    </script>
</x-app-layout>