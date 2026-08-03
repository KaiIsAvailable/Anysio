<x-app-layout>
    @php
        // 1. 尝试获取传递进来的模板对象
        $targetTemplate = $template ?? $documentTemplate ?? null;

        // 双重保底：如果 Controller 没传递，前端直接根据 URL 里的 from_id 去查找
        if (!$targetTemplate && request()->filled('from_id')) {
            $targetTemplate = \App\Models\DocumentTemplate::find(request()->query('from_id'));
        }

        // 2. 角色与分类权限控制
        $userRole = auth()->user()->role ?? '';
        $isAdmin = in_array($userRole, ['admin', 'superadmin', 'super-admin']);
        
        $categories = [
            'agreement' => 'Agreement',
            'invoice'   => 'Invoice',
            'receipt'   => 'Receipt'
        ];
        
        if ($isAdmin) {
            $categories = [
                'tos'     => 'Terms of Service',
                'privacy' => 'Privacy Policy'
            ] + $categories;
        }

        // 3. 数据回填初值 (针对 from_id 继承模式)
        $defaultTitle    = $targetTemplate->title ?? '';
        $defaultCategory = $targetTemplate->category ?? 'agreement';
        $defaultVersion  = $targetTemplate->version ?? '1.0.0';
        $defaultOwner    = $targetTemplate->user_id ?? '';
        $savedHtml       = $targetTemplate->html_template ?? '';
    @endphp

    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-[95rem] mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="mb-6 flex justify-between items-end">
                <div>
                    <a href="{{ route('admin.document-templates.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center transition-colors">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        Back to List
                    </a>
                    <h1 class="text-2xl font-bold text-slate-900 mt-2">
                        {{ request()->filled('from_id') ? 'Edit Document Template' : 'Design Document Template' }}
                    </h1>
                </div>
                <div class="flex gap-3">
                    <button onclick="saveTemplate()" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-bold shadow-sm hover:bg-indigo-700 transition-all flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Save Template
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-3">
                <div class="px-8 py-4 border-b border-gray-100 bg-gray-50 flex gap-6 items-center">
                    <div class="w-56">
                        <x-form.input-label value="Owner" class="mb-1" />
                        <div class="w-56">
                            <x-form.input-select
                                id="doc-owner"
                                name="user_id"
                                :options="$ownerOptions"
                                valueField="id" 
                                labelField="name"
                                :disabled="$isOwnerAdmin"
                                :value="$defaultOwner"
                            />
                        </div>
                    </div>
                    <div class="w-64">
                        <x-form.input-label value="Template Title" class="mb-1" />
                        <x-form.text-input
                            id="doc-title"
                            name="title"
                            type="text"
                            class="w-full text-sm"
                            :value="$defaultTitle"
                        />
                    </div>
                    <div class="w-32">
                        <x-form.input-label value="Version" class="mb-1" />
                        <x-form.text-input
                            id="doc-version"
                            name="version"
                            type="text"
                            class="w-full text-sm"
                            :value="$defaultVersion"
                        />
                    </div>
                    <div class="w-48">
                        <x-form.input-label value="Category" class="mb-1" />
                        <x-form.input-select
                            id="doc-category"
                            name="category"
                            :options="$categories"
                            :value="$defaultCategory"
                        />
                    </div>
                </div>
            </div>
            <x-ui.editor id="gjs-editor" />
        </div>
    </div>

    {{-- ========================================== --}}
    {{-- CSS 样式区 --}}
    {{-- ========================================== --}}
    <style>
        .gjs-rte-toolbar {
            display: flex !important;
            align-items: center !important;
            padding: 4px 6px !important;
            gap: 4px !important; 
        }

        .gjs-rte-action {
            width: auto !important; 
            min-width: 24px !important;
            height: auto !important;
        }
    </style>

    {{-- ========================================== --}}
    {{-- JS 逻辑区 --}}
    {{-- ========================================== --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const categorySelect = document.getElementById('doc-category');

            // 监听类别切换，动态刷新左侧的 Blocks 列表
            categorySelect.addEventListener('change', function () {
                if (window.updateBlocks) {
                    window.updateBlocks(this.value);
                }
            });

            // ⚠️ 核心修复 1：使用 json_encode 确保复杂的 HTML 和换行符能被安全转义
            const savedHtml = {!! json_encode($savedHtml ?? '') !!};

            console.log("Checking template data...", savedHtml ? "Data Found" : "No Data");

            // ⚠️ 核心修复 2：无限轮询检测，绝不错过 GrapesJS 的就绪时刻
            const checkEditorInterval = setInterval(() => {
                // 只有当 editor 存在，且底层的 Canvas 画板完全渲染完毕时才执行
                if (window.editor && window.editor.Canvas) {
                    clearInterval(checkEditorInterval); // 目标达成，停止轮询

                    // 如果有历史数据，安全地注入到画板中
                    if (savedHtml && savedHtml.trim() !== '') {
                        try {
                            window.editor.DomComponents.clear(); // 先清理白纸
                            window.editor.setComponents(savedHtml); // 塞入进度
                            console.log("Template fully loaded into GrapesJS!");
                        } catch (e) {
                            console.error('Error loading components:', e);
                        }
                    }

                    // 同步刷新左侧 Blocks 工具栏对应当前选中的 Category
                    if (categorySelect && categorySelect.value && window.updateBlocks) {
                        window.updateBlocks(categorySelect.value);
                    }
                }
            }, 100); // 每 0.1 秒检查一次，既不卡顿又能做到秒速填充
        });

        // 提交保存逻辑
        async function saveTemplate() {
            const data = {
                user_id: document.getElementById('doc-owner').value,
                title: document.getElementById('doc-title').value,
                version: document.getElementById('doc-version').value,
                category: document.getElementById('doc-category').value,

                // 从 GrapesJS 提取 HTML 和 CSS 并合并
                html_template: `<style>${window.editor.getCss()}</style>\n${window.editor.getHtml()}`,
                details: ''
            };

            try {
                const response = await fetch("{{ route('admin.document-templates.store') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                        "Accept": "application/json"
                    },
                    body: JSON.stringify(data)
                });

                if (!response.ok) {
                    const error = await response.json();
                    console.log(error);
                    alert("Failed to save template.");
                    return;
                }

                alert("Template saved successfully!");
                window.location = "{{ route('admin.document-templates.index') }}";
            } catch (e) {
                console.error(e);
                alert("Unexpected error.");
            }
        }
    </script>
</x-app-layout>