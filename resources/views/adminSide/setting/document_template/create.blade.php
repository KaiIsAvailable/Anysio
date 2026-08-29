<x-app-layout>
    @php
        $targetTemplate = $template ?? $documentTemplate ?? null;

        if (!$targetTemplate && request()->filled('from_id')) {
            $targetTemplate = \App\Models\DocumentTemplate::find(request()->query('from_id'));
        }

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

        $defaultTitle    = $targetTemplate->title ?? '';
        $defaultCategory = $targetTemplate->category ?? 'agreement';
        $defaultVersion  = $targetTemplate->version ?? '1.0.0';
        $defaultOwner    = $targetTemplate->user_id ?? '';
        $savedHtml       = $targetTemplate->html_template ?? '';
    @endphp

    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-[95rem] mx-auto px-4 sm:px-6 lg:px-8 relative">
            
            {{-- 💡 Alpine.js 表單接管狀態，加入防呆攔截器 --}}
            <form x-data="{
                      loading: false,
                      showWarningModal: false,
                      forceSubmit: false,
                      activeMap: {{ json_encode($activeMap ?? []) }},
                      
                      submitForm(e) {
                          // 🌟 修復核心 1：無論如何，第一步永遠先把編輯器的 HTML 抓下來放進隱藏欄位！
                          document.getElementById('hidden_html_template').value = `<style>${window.editor.getCss()}</style>\n${window.editor.getHtml()}`;

                          // 如果不是強制送出，先檢查是否有衝突的 Active 範本
                          if (!this.forceSubmit) {
                              let ownerSelect = document.getElementById('doc-owner');
                              let ownerId = ownerSelect && ownerSelect.value ? ownerSelect.value : '';
                              let targetKey = ownerId ? ownerId : 'system';
                              let category = document.getElementById('doc-category').value;
                              
                              if (this.activeMap[targetKey] && this.activeMap[targetKey].includes(category)) {
                                  if (e) e.preventDefault(); // 阻斷提交
                                  this.showWarningModal = true;
                                  return;
                              }
                          }
                          
                          this.loading = true;
                      }
                  }" 
                  id="templateForm" 
                  method="POST" 
                  action="{{ route('admin.document-templates.store') }}"
                  @submit="submitForm($event)">
                
                @csrf

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
                            {{ request()->filled('from_id') ? 'Clone Document Template' : 'Design Document Template' }}
                        </h1>
                    </div>
                    <div class="flex gap-3">
                        <x-form.primary-button type="submit" loading="loading" class="px-6 py-2.5 flex items-center">
                            <svg x-show="!loading" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Save Template
                        </x-form.primary-button>
                    </div>
                </div>

                @if($errors->any())
                    <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded font-semibold text-sm">
                        Please check the form for errors.
                    </div>
                @endif

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-3">
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
                                :value="old('title', $defaultTitle)"
                            />
                        </div>
                        <div class="w-32">
                            <x-form.input-label value="Version" class="mb-1" />
                            <x-form.text-input
                                id="doc-version"
                                name="version"
                                type="text"
                                class="w-full text-sm"
                                :value="old('version', $defaultVersion)"
                            />
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

                {{-- 🌟 彈出式警告 Modal --}}
                <div x-show="showWarningModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm" x-cloak style="display: none;">
                    <div @click.outside="showWarningModal = false" class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 overflow-hidden relative"
                         x-transition:enter="transition ease-out duration-300 transform"
                         x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-200 transform"
                         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                         x-transition:leave-end="opacity-0 scale-95 translate-y-4">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Active Template Exists</h3>
                        </div>
                        <p class="text-sm text-slate-600 mb-6 leading-relaxed">
                            You already have an active template for this category. Saving this new template will automatically <strong class="text-amber-600">deactivate</strong> the old one to prevent conflicts. Do you want to proceed?
                        </p>
                        <div class="flex justify-end gap-3">
                            <button type="button" @click="showWarningModal = false" class="px-4 py-2 bg-gray-100 text-gray-700 font-semibold rounded-lg hover:bg-gray-200 transition-colors">Cancel</button>
                            {{-- 🌟 修復核心 2：點擊確認時，再次觸發 submitForm() 確保加載動畫執行，然後送出表單 --}}
                            <button type="button" 
                                    @click="
                                        forceSubmit = true; 
                                        showWarningModal = false; 
                                        submitForm(null); 
                                        document.getElementById('templateForm').submit();
                                    " 
                                    class="px-4 py-2 bg-amber-500 text-white font-semibold rounded-lg hover:bg-amber-600 transition-colors shadow-sm">
                                Confirm & Deactivate
                            </button>
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