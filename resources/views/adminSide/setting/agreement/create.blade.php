<x-app-layout>
    <style>
        /* 1. 让字体大小下拉框显示具体的像素值 */
        .ql-snow .ql-picker.ql-size .ql-picker-label::before,
        .ql-snow .ql-picker.ql-size .ql-picker-item::before {
        content: attr(data-value) !important;
        }

        /* 2. 让字体系列下拉框显示对应的名称 */
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value='serif']::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value='serif']::before {
        content: 'Serif' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value='sans-serif']::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value='sans-serif']::before {
        content: 'Sans Serif' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value='monospace']::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value='monospace']::before {
        content: 'Monospace' !important;
        }
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value='roboto']::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value='roboto']::before {
        content: 'Roboto' !important;
        font-family: 'Roboto', sans-serif;
        }
    </style>
    <div class="py-12 bg-gray-50 min-h-screen" x-data="{ agreementType: 'tos' }">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="mb-6">
                <a href="{{ route('admin.agreements.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to List
                </a>
                <h1 class="text-2xl font-bold text-slate-900 mt-2">Create New Agreement Template</h1>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <form action="{{ route('admin.agreements.store') }}" method="POST" class="p-8" id="agreementForm"
                    x-data="{ 
                        agreementType: '{{ old('type', $sourceAgreement->type ?? 'rental_lease') }}' 
                    }">
                    @csrf
                    
                    <div class="space-y-6">
                        <!-- Agreement Type 容器 -->
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 {{ $sourceAgreement ? 'bg-gray-100 opacity-75' : '' }}">
                            <label for="type" class="block text-sm font-semibold text-gray-700">
                                Agreement Type {!! $sourceAgreement ? '<span class="text-xs font-normal text-gray-400">(Locked for new version)</span>' : '' !!}
                            </label>
                            
                            <select name="{{ $sourceAgreement ? 'type_disabled' : 'type' }}" id="type" 
                                    x-model="agreementType" 
                                    {{ $sourceAgreement ? 'disabled' : 'required' }}
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 {{ $sourceAgreement ? 'cursor-not-allowed' : '' }}">
                                
                                @if ($isOwnerAgentAdmin === false)
                                    <option value="tos" @selected(old('type', $sourceAgreement->type ?? '') == 'tos')>Register T&C</option>
                                    <option value="privacy" @selected(old('type', $sourceAgreement->type ?? '') == 'privacy')>Register Privacy Policy</option>
                                    <option value="rental_lease" @selected(old('type', $sourceAgreement->type ?? '') == 'rental_lease')>Lease Agreement</option>
                                @else
                                    <option value="rental_lease" selected>Lease Agreement</option>
                                @endif
                            </select>

                            @if($sourceAgreement)
                                {{-- 如果是 edit，通过 hidden 传值 --}}
                                <input type="hidden" name="type" value="{{ $sourceAgreement->type }}">
                            @endif
                        </div>

                        <!-- Assign to Owner 容器 -->
                        <div x-show="agreementType === 'rental_lease'" class="pb-4 border-b border-gray-100">
                            <label for="owner_id" class="block text-sm font-semibold text-gray-700">
                                Assign to Owner {!! $sourceAgreement ? '<span class="text-xs font-normal text-gray-400">(Locked)</span>' : '' !!}
                            </label>
                            <select name="{{ $sourceAgreement ? 'owner_id_disabled' : 'owner_id' }}" id="owner_id" 
                                    {{ $sourceAgreement ? 'disabled' : '' }}
                                    :required="agreementType === 'rental_lease'"
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 {{ $sourceAgreement ? 'bg-gray-100 cursor-not-allowed' : '' }}">
                                <option value="">-- Select Owner --</option>
                                @foreach($owners as $owner)
                                    <option value="{{ $owner->id }}" 
                                        {{ old('owner_id', $sourceAgreement->owner_id ?? '') == $owner->id ? 'selected' : '' }}>
                                        {{ $owner->user->name }}
                                    </option>
                                @endforeach
                            </select>

                            @if($sourceAgreement)
                                {{-- 如果是 edit，通过 hidden 传值 --}}
                                <input type="hidden" name="owner_id" value="{{ $sourceAgreement->owner_id }}">
                            @endif
                        </div>

                        <input type="hidden" name="parent_agreement_id" value="{{ $sourceAgreement ? $sourceAgreement->id : '' }}" readonly>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="md:col-span-2">
                                <label for="title" class="block text-sm font-semibold text-gray-700">Agreement Title</label>
                                <input type="text" name="title" id="title" value="{{ old('title', $sourceAgreement ? $sourceAgreement->title : '') }}" placeholder="e.g. Standard 1-Year Lease" required 
                                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 uppercase">
                                @error('title') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="version" class="block text-sm font-semibold text-gray-700">Version</label>
                                <input type="text" name="version" id="version" value="{{ old('version', $sourceAgreement ? ($sourceAgreement->version) : '1.0') }}" placeholder="e.g. 1.0.0" 
                                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 uppercase">
                                @error('version') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <input type="hidden" name="content" id="content_input" value="{{ old('content') }}">

                            <div x-show="agreementType === 'rental_lease'" class="space-y-6">
                                <div class="bg-slate-50 border border-slate-200 p-5 rounded-xl">
                                    <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-4 flex items-center">
                                        Click to Insert Variables
                                    </h3>

                                    <div class="space-y-5">
                                        @foreach($placeholders as $category => $items)
                                            <div>
                                                <!-- 分类标题 -->
                                                <h4 class="text-[11px] font-semibold text-slate-400 uppercase mb-2 ml-1">
                                                    {{ $category }}
                                                </h4>
                                                
                                                <!-- 按钮容器 -->
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach($items as $item)
                                                        <button type="button" 
                                                                onclick="insertVariable('{{ $item['value'] }}')" 
                                                                class="group px-3 py-1.5 bg-white border border-slate-200 text-slate-600 text-xs font-medium rounded-lg hover:border-indigo-400 hover:text-indigo-600 hover:shadow-sm transition-all flex items-center">
                                                            <span class="text-slate-300 group-hover:text-indigo-400 mr-1.5">+</span>
                                                            {{ $item['label'] }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <br>

                            <div class="flex items-center justify-between mb-2">
                                <!-- 将 for 指向 Quill 编辑器的容器 ID -->
                                <label for="editor" class="block text-sm font-semibold text-gray-700">Core Content</label>
                                
                                <span class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest bg-indigo-50 px-2 py-0.5 rounded">
                                    Rich Text Editor
                                </span>
                            </div>

                            <div>
                                <div class="bg-white border border-gray-300 rounded-lg overflow-hidden">
                                    <div id="editor" style="height: 500px;" class="font-serif">
                                        {!! old('content', $sourceAgreement ? $sourceAgreement->content : '') !!}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 flex items-start gap-2 text-xs text-gray-500 italic">
                                <svg class="w-4 h-4 text-indigo-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                <strong>Tip:</strong> Click any variable below to insert it at your cursor. You can also type them manually, e.g., <code class="px-1 py-0.5 bg-slate-100 rounded text-indigo-600 font-mono">{owner_name}</code>.
                            </div>
                        </div>

                        <div class="pt-6 border-t border-gray-100 flex justify-end gap-3">
                            <a href="{{ route('admin.agreements.index') }}" class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                Cancel
                            </a>
                            <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-md transition-all">
                                Save Agreement Template
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
        // 1. Quill 配置 (保持不变...)
        const Size = Quill.import('attributors/style/size');
        Size.whitelist = ['12px', '14px', '16px', '18px', '20px', '24px', '32px'];
        Quill.register(Size, true);

        const Font = Quill.import('attributors/style/font');
        Font.whitelist = ['serif', 'sans-serif', 'monospace', 'roboto', 'mirza'];
        Quill.register(Font, true);

        // 2. 初始化
        quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'font': Font.whitelist }, { 'size': Size.whitelist }], 
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'header': [1, 2, 3, false] }],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'align': [] }],
                    ['clean']
                ]
            }
        });

        const contentInput = document.getElementById('content_input');
        const form = document.getElementById('agreementForm'); // 确保 HTML 里有这个 ID

        // 3. 定义同步函数
        function syncQuillToInput() {
            const html = quill.root.innerHTML;
            // Quill 默认空行是 <p><br></p>，我们要把它转为空字符串以便触发后端验证
            if (html === '<p><br></p>' || quill.getText().trim().length === 0) {
                contentInput.value = '';
            } else {
                contentInput.value = html;
            }
        }

        // 监听：文字改变时实时同步
        quill.on('text-change', syncQuillToInput);

        // 关键：拦截表单提交事件
        if (form) {
            form.addEventListener('submit', function(e) {
                syncQuillToInput(); // 提交前最后强制同步一次
                console.log('Final content check:', contentInput.value);
                
                // 如果 content 还是空的，可以阻止提交并提醒（可选）
                if (!contentInput.value) {
                    // alert('Please enter content');
                    // e.preventDefault();
                }
            });
        }
    });

    // 插入变量函数保持不变
    function insertVariable(variable) {
        const range = quill.getSelection(true);
        if (range) {
            quill.insertText(range.index, variable); 
            quill.setSelection(range.index + variable.length);
            // 触发一下同步
            document.getElementById('content_input').value = quill.root.innerHTML;
        }
    }
    </script>
</x-app-layout>