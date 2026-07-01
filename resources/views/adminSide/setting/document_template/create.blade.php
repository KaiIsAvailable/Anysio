<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-[95rem] mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="mb-6 flex justify-between items-end">
                <div>
                    <a href="{{ route('admin.document-templates.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center transition-colors">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        Back to List
                    </a>
                    <h1 class="text-2xl font-bold text-slate-900 mt-2">Design Document Template</h1>
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
                        />
                    </div>
                    <div class="w-32">
                        <x-form.input-label value="Version" class="mb-1" />
                        <x-form.text-input
                            id="doc-version"
                            name="version"
                            type="text"
                            value="1.0.0"
                            class="w-full text-sm"
                        />
                    </div>
                    <div class="w-48">
                        <x-form.input-label value="Category" class="mb-1" />
                        <x-form.input-select
                            id="doc-category"
                            name="category"
                            :options="[
                                'tos' => 'Terms of Service',
                                'privacy' => 'Privacy Policy',
                                'agreement' => 'Agreement',
                                'invoice' => 'Invoice',
                                'receipt' => 'Receipt'
                            ]"
                        />
                    </div>
                </div>
            </div>
            <x-ui.editor id="gjs-editor" />
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const category = document.getElementById('doc-category');

            category.addEventListener('change', function () {
                window.updateBlocks(this.value);
            });

        });

        async function saveTemplate() {
            const data = {
                user_id: document.getElementById('doc-owner').value,
                title: document.getElementById('doc-title').value,
                version: document.getElementById('doc-version').value,
                category: document.getElementById('doc-category').value,

                html_template:
                    `<style>${window.editor.getCss()}</style>\n${window.editor.getHtml()}`,
                details: ''
            };

            try {
                const response = await fetch("{{ route('admin.document-templates.store') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document
                            .querySelector('meta[name="csrf-token"]')
                            .content,
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
                window.location =
                    "{{ route('admin.document-templates.index') }}";
            } catch (e) {
                console.error(e);
                alert("Unexpected error.");
            }
        }
    </script>
</x-app-layout>