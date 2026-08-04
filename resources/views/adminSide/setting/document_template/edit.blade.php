<x-app-layout>
    @php
        $userRole = auth()->user()->role ?? '';
        $isAdmin = in_array($userRole, ['admin', 'superadmin', 'super-admin']);$categories = [
            'agreement' => 'Agreement',
            'invoice'   => 'Invoice',
            'receipt'   => 'Receipt'
        ];
        
        if ($isAdmin) {
            $categories = [                 'tos'     => 'Terms of Service',                 'privacy' => 'Privacy Policy'             ] +$categories;
        }

        $defaultTitle    =$documentTemplate->title ?? '';
        $defaultCategory =$documentTemplate->category ?? 'agreement';
        $oldVersion      =$documentTemplate->version ?? '1.0.0';
        $defaultOwner    =$documentTemplate->user_id ?? '';
        $savedHtml       =$documentTemplate->html_template ?? '';
    @endphp

    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-[95rem] mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- 💡 完美結合 Alpine.js 的表單提交邏輯 --}}
            <form x-data="{
                      loading: false,
                      submitForm(e) {
                          const versionInputEl = document.getElementById('doc-version');
                          const errorMsgEl = document.getElementById('version-error-msg');
                          const versionInput = versionInputEl.value.trim();
                          const oldVersion = '{{ $oldVersion }}';
                          
                          versionInputEl.classList.remove('border-red-500', 'ring-red-500');
                          errorMsgEl.classList.add('hidden');

                          // 如果版本號沒改，阻止表單提交並提示
                          if (versionInput === oldVersion) {
                              versionInputEl.classList.add('border-red-500', 'ring-red-500');
                              errorMsgEl.innerText = 'Must provide a new version number.';
                              errorMsgEl.classList.remove('hidden');
                              versionInputEl.focus();
                              
                              e.preventDefault();
                              return false;
                          }

                          // 抓取 GrapesJS 內容並塞入隱藏欄位
                          document.getElementById('hidden_html_template').value = `<style>${window.editor.getCss()}</style>\n${window.editor.getHtml()}`;
                          
                          // 確保表單已經觸發提交後，才顯示轉圈圈 (避免攔截)
                          this.loading = true;
                      }
                  }" 
                  id="templateForm" 
                  method="POST" 
                  action="{{ route('admin.document-templates.update', $documentTemplate->id) }}"
                  @submit="submitForm($event)">
                
                @csrf
                @method('PUT')

                <input type="hidden" name="html_template" id="hidden_html_template">
                
                @if($isOwnerAdmin)
                    <input type="hidden" name="user_id" value="{{ $defaultOwner }}">
                @endif

                <div class="mb-6 flex justify-between items-end">
                    <div>
                        <a href="{{ route('admin.document-templates.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                            Back to List
                        </a>
                        <h1 class="text-2xl font-bold text-slate-900 mt-2">
                            Edit Template Version
                        </h1>
                        <p class="text-sm text-red-500 mt-1">* Please provide a <b>new version number</b> for this update.</p>
                    </div>
                    <div class="flex gap-3">
                        {{-- 💡 已經移除了 @click="loading=true"，由表單接管 --}}
                        <x-form.primary-button type="submit" loading="loading" class="px-6 py-2.5 flex items-center">
                            <svg x-show="!loading" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Save New Version
                        </x-form.primary-button>
                    </div>
                </div>

                {{-- 若有後端回傳的版本重複錯誤，顯示在這裡 --}}
                @if(session('error'))
                    <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded font-semibold text-sm">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-3">
                    <div class="px-8 py-4 border-b border-gray-100 bg-gray-50 flex gap-6 items-start">
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
                                :value="old('title', $defaultTitle)" 
                            />
                        </div>
                        <div class="w-40 flex flex-col relative">
                            <x-form.input-label value="Version" class="mb-1" />
                            <x-form.text-input
                                id="doc-version"
                                name="version"
                                type="text"
                                class="w-full text-sm transition-colors"
                                :value="old('version', $oldVersion)"
                                placeholder="e.g. 1.1.0"
                            />
                            <span id="version-error-msg" class="text-xs text-red-500 mt-1 font-medium hidden"></span>
                        </div>
                        <div class="w-48">
                            <x-form.input-label value="Category" class="mb-1" />
                            <x-form.input-select
                                id="doc-category"
                                name="category"
                                :options="$categories"
                                :value="old('category', $defaultCategory)"
                            />
                        </div>
                    </div>
                </div>
            </form>

            <x-ui.editor id="gjs-editor" />
        </div>
    </div>

    <style>
        .gjs-rte-toolbar { display: flex !important; align-items: center !important; padding: 4px 6px !important; gap: 4px !important; }
        .gjs-rte-action { width: auto !important; min-width: 24px !important; height: auto !important; }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const categorySelect = document.getElementById('doc-category');

            categorySelect.addEventListener('change', function () {
                if (window.updateBlocks) {
                    window.updateBlocks(this.value);
                }
            });

            const savedHtml = {!! json_encode(old('html_template', $savedHtml ?? '')) !!};

            const checkEditorInterval = setInterval(() => {
                if (window.editor && window.editor.Canvas) {
                    clearInterval(checkEditorInterval); 

                    if (savedHtml && savedHtml.trim() !== '') {
                        try {
                            window.editor.DomComponents.clear(); 
                            window.editor.setComponents(savedHtml); 
                        } catch (e) {
                            console.error('Error loading components:', e);
                        }
                    }

                    if (categorySelect && categorySelect.value && window.updateBlocks) {
                        window.updateBlocks(categorySelect.value);
                    }
                }
            }, 100); 
        });
    </script>
</x-app-layout>