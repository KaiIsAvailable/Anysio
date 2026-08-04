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

                                    <button @click.stop="expanded = true; currentVersion = '{{ $agreement->version }}'; currentContent = {{ json_encode($agreement->html_template ?? '') }}; currentId = '{{ $agreement->id }}';"
                                        :class="currentVersion === '{{ $agreement->version }}' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                        class="px-2 py-0.5 rounded text-[10px] font-mono transition-colors">
                                        v{{ $agreement->version }} (Active)
                                    </button>

                                    @foreach($agreement->full_history as $history)
                                    @if($history->id !== $agreement->id)
                                    <button @click.stop="expanded = true; currentVersion = '{{ $history->version }}'; currentContent = {{ json_encode($history->html_template ?? '') }}; currentId = '{{ $history->id }}';"
                                        :class="currentVersion === '{{ $history->version }}' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                        class="px-2 py-0.5 rounded text-[10px] font-mono transition-colors">
                                        v{{ $history->version }}
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
                            <div class="flex items-center gap-1.5 mt-2">
                                <span class="text-xs text-gray-400 italic">Status:</span>

                                {{-- 💡 重点：隐藏的表单，用于标准提交 --}}
                                <form x-ref="activateForm" method="POST" :action="'{{ route('admin.document-templates.activate', 'PLACEHOLDER') }}'.replace('PLACEHOLDER', currentId)" class="hidden">
                                    @csrf
                                </form>

                                <label class="inline-flex items-center cursor-pointer group">
                                    {{-- 💡 重点：Checkbox 修改状态后，直接触发提交上面的隐藏表单 --}}
                                    <input type="checkbox"
                                        class="form-checkbox h-3.5 w-3.5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 transition duration-150 ease-in-out cursor-pointer disabled:opacity-50"
                                        :checked="currentVersion === '{{ $agreement->version }}' || statusUpdating"
                                        :disabled="currentVersion === '{{ $agreement->version }}' || statusUpdating"
                                        @change="statusUpdating = true; $refs.activateForm.submit();">

                                    <span class="ml-1.5 text-[11px] font-medium"
                                        :class="currentVersion === '{{ $agreement->version }}' ? 'text-green-600' : 'text-gray-400'"
                                        x-text="statusUpdating ? 'Updating...' : (currentVersion === '{{ $agreement->version }}' ? 'Active' : 'Set as Active')">
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="tos-content text-slate-700 leading-relaxed quill-content"
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
        // 💡 所有多余的 JS 通知代码都已经彻底删除！
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