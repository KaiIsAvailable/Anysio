
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
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Agreement Templates</h1>
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
                        {{-- 1. 初始化 x-data，增加 currentVersion 和 currentContent --}}
                        <div x-data="{ 
                                expanded: {{ request('expanded_id') == $agreement->id ? 'true' : 'false' }},
                                currentVersion: '{{ $agreement->version }}',
                                {{-- 默认显示当前 active 的内容 --}}
                                currentContent: `{{ addslashes($agreement->content) }}`,
                                currentId: '{{ $agreement->id }}',
                                statusUpdating: false
                            }" 
                            class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden transition-all hover:shadow-lg">
                            
                            <div class="px-6 py-4 bg-slate-50 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 cursor-pointer">
                                <div class="flex items-center gap-4">
                                    <!-- 图标和标题 -->
                                    <div>
                                        <h2 class="text-xl font-bold text-slate-900">{{ $agreement->title }}</h2>
                                        
                                        {{-- 2. 版本切换器 --}}
                                        <div class="flex items-center gap-2 mt-2 flex-wrap">
                                            <span class="text-xs text-gray-400 uppercase font-bold tracking-widest">Version:</span>
                                            
                                            <!-- 1. 当前版本按钮 (始终显示) -->
                                            <button @click.stop="expanded = true; currentVersion = '{{ $agreement->version }}'; currentContent = `{{ addslashes($agreement->content) }}`; currentId = '{{ $agreement->id }}';" 
                                                    :class="currentVersion === '{{ $agreement->version }}' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                                    class="px-2 py-0.5 rounded text-[10px] font-mono transition-colors">
                                                v{{ $agreement->version }} (Latest)
                                            </button>

                                            <!-- 2. 循环显示所有历史版本 -->
                                            @foreach($agreement->allHistory as $history)
                                            <button @click.stop="expanded = true; currentVersion = '{{ $history->version }}'; currentContent = `{{ addslashes($history->content) }}`; currentId = '{{ $history->id }}';"
                                                    :class="currentVersion === '{{ $history->version }}' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                                    class="px-2 py-0.5 rounded text-[10px] font-mono transition-colors">
                                                v{{ $history->version }}
                                            </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-4">
                                    <!-- 展开/收起的状态图标 -->
                                    <span class="text-sm font-medium text-indigo-600 flex items-center cursor-pointer select-none" @click="expanded = !expanded">
                                        <span x-text="expanded ? 'Click to collapse' : 'Click to preview'"></span>
                                        <svg class="w-4 h-4 ml-1 transition-transform duration-300" 
                                            :style="expanded ? 'transform: rotate(180deg);' : 'transform: rotate(0deg);'"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </span>
                                    
                                    <button type="button" 
                                            {{-- 使用 Alpine.js 获取当前卡片内的内容并传入函数 --}}
                                            @click.stop="printContract($refs.content_{{ $agreement->id }}.innerHTML)" 
                                            class="p-2 text-gray-400 hover:text-slate-600 hover:bg-gray-100 rounded-lg transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                        </svg>
                                    </button>

                                    <a :href="'{{ route('admin.agreements.create') }}?from_id=' + currentId" 
                                    class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                                    title="Create new version based on this selected version">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>
                                </div>
                            </div>

                            <!-- 内容容器：带动画效果 -->
                            <div x-show="expanded" 
                                x-collapse
                                class="px-8 bg-white p-10 border-t border-gray-50">
                                
                                <div class="flex justify-between mb-4 border-b pb-2">
                                    <!-- 这里的 x-text 会根据你点击的版本按钮实时变化 -->
                                    <span class="text-sm font-bold text-indigo-600">Showing Content: Version <span x-text="currentVersion"></span></span>
                                    <div class="flex items-center gap-1.5 mt-2"> {{-- gap-1.5 控制文字与 checkbox 的整体距离 --}}
                                        <span class="text-xs text-gray-400 italic">Status:</span>
                                        
                                        <label class="inline-flex items-center cursor-pointer group">
                                            <!-- Checkbox -->
                                            <input type="checkbox" 
                                                class="form-checkbox h-3.5 w-3.5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 transition duration-150 ease-in-out cursor-pointer disabled:opacity-50"
                                                :checked="currentVersion === '{{ $agreement->version }}' || statusUpdating"
                                                :disabled="currentVersion === '{{ $agreement->version }}' || statusUpdating"
                                                @change="activateVersion(currentId)"
                                            >
                                            
                                            <span class="ml-1.5 text-[11px] font-medium" {{-- ml-1.5 控制 checkbox 与后面文字的距离 --}}
                                                :class="currentVersion === '{{ $agreement->version }}' ? 'text-green-600' : 'text-gray-400'"
                                                x-text="statusUpdating ? 'Updating...' : (currentVersion === '{{ $agreement->version }}' ? 'Active' : 'Set as Active')">
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <div class="tos-content text-slate-700 leading-relaxed quill-content" 
                                    x-ref="content_{{ $agreement->id }}"
                                    x-html="currentContent" {{-- 这里是关键：它负责渲染当前选中的 HTML --}}
                                    style="font-family: 'Times New Roman', serif;">
                                </div>
                            </div>

                            <!-- 底部栏：一直显示 -->
                            <div class="px-8 py-3 bg-gray-50 border-t border-gray-100 flex justify-between items-center text-[11px] text-gray-400 font-medium italic">
                                <span>{{ $agreement->owner ? $agreement->owner->user->name : 'Global System' }}</span>
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

    <script>
        function printContract(content) {
            // 1. 创建一个隐藏的 iframe
            const iframe = document.createElement('iframe');
            iframe.style.position = 'fixed';
            iframe.style.right = '0';
            iframe.style.bottom = '0';
            iframe.style.width = '0';
            iframe.style.height = '0';
            iframe.style.border = '0';
            document.body.appendChild(iframe);

            // 2. 写入打印内容和样式
            const doc = iframe.contentWindow.document;
            doc.open();
            doc.write(`
                <html>
                    <head>
                        <title></title>
                        <style>
                            body { font-family: 'Times New Roman', serif; padding: 20px; line-height: 1.6; }
                            h1, h2, h3 { text-align: center; text-transform: uppercase; }
                            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                            table, th, td { border: 1px solid black; padding: 8px; }
                            .quill-content p { margin-bottom: 1em; }
                            @media print {
                                body { padding: 0; }
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

            // 3. 触发打印
            iframe.contentWindow.focus();
            setTimeout(() => {
                iframe.contentWindow.print();
                // 打印完成后移除 iframe
                document.body.removeChild(iframe);
            }, 500);
        }
        function activateVersion(id) {
            // 1. 设置状态为更新中，防止重复点击
            // 如果你在 Alpine 组件外定义，这里可能无法直接改变量，但 fetch 会继续执行
            console.log("Activating version ID:", id); 

            let url = "{{ route('admin.agreements.activate', ':id') }}".replace(':id', id);

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => {
                if (response.ok) {
                    // 成功：跳转到带参数的URL
                    console.log("Success! Redirecting...");
                    window.location.href = window.location.pathname + '?expanded_id=' + id;
                } else {
                    // 失败：服务器返回错误码 (如 500, 404, 419)
                    return response.json().then(data => {
                        throw new Error(data.message || 'Server Error');
                    });
                }
            })
            .catch(error => {
                // 捕获网络错误或上面抛出的错误
                console.error('Full Error Detail:', error);
                alert('Status update failed: ' + error.message);
            });
        }
    </script>
</x-app-layout>