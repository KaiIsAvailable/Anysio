<x-app-layout>
    <div x-data="{ 
            openPassword: false, 
            shakePassword: false, 
            loading: false, 
            passwordInput: '', 
            passwordError: '',
            pendingLogId: null, // 等待验证的 ID
            pendingBtn: null,   // 等待验证的按钮元素
            activeLogs: {},

            toggleDetails(id) {
                // 如果当前是关闭状态，则触发密码校验流程
                if (!this.activeLogs[id]) {
                    this.pendingLogId = id;
                    this.openPassword = true;
                } else {
                    // 如果已经是开启状态，则直接关闭（无需密码）
                    localStorage.removeItem('audit_unlocked_' + id);
                    this.activeLogs[id] = false;
                }
            },

            async verifyPassword(pwd) {
                if (this.loading) return;
                this.loading = true;
                this.passwordError = '';
                
                try {
                    const response = await fetch('{{ route('user.verify-password') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ password: pwd })
                    });

                    if (response.ok) {
                        this.openPassword = false;
                        localStorage.setItem('audit_unlocked_' + this.pendingLogId, 'true');
                        this.activeLogs[this.pendingLogId] = true;
                        this.passwordInput = '';
                        this.loading = false;
                    } else {
                        // 密码错误处理
                        this.shakePassword = true;
                        this.passwordError = 'Invalid password';
                        this.loading = false;
                        setTimeout(() => this.shakePassword = false, 400);
                    }
                } catch (e) {
                    this.passwordError = 'System error, please try again.';
                    this.loading = false;
                }
            }
        }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">System Audit Logs</h1>
                <p class="mt-2 text-sm text-gray-500">A complete history of changes across your system models.</p>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 text-left">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Time</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase">User</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Action</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Target Item</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Details</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($logs as $log)
                                <tr class="hover:bg-indigo-50/30 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {{ $log->created_at->format('d M Y, H:i') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                                        <div class="flex items-center">
                                            <div class="h-6 w-6 rounded-full bg-indigo-100 flex items-center justify-center text-[10px] text-indigo-700 mr-2">
                                                {{ mb_strtoupper(mb_substr($log->user->name ?? 'S', 0, 1)) }}
                                            </div>
                                            {{ $log->user->name ?? 'System' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase
                                            {{ $log->event == 'created' ? 'bg-green-100 text-green-700' : 
                                               ($log->event == 'deleted' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') }}">
                                            {{ $log->event }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                        @if($log->auditable)
                                            <span class="font-bold text-indigo-700">{{ class_basename($log->auditable) }}</span>
                                            <span class="text-gray-400">#{{ substr($log->auditable_id, 0, 8) }}</span>
                                            {{-- 这里可以显示模型的关键属性，比如 name 或 title --}}
                                            <div class="text-[10px] text-gray-400">{{ $log->auditable->name ?? $log->auditable->title ?? '' }}</div>
                                        @else
                                            <span class="text-gray-400 italic text-xs">Record deleted</span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <button 
                                            type="button"
                                            @click="toggleDetails('{{ $log->id }}')"
                                            class="text-indigo-600 hover:text-indigo-900 font-bold text-xs uppercase tracking-wider flex items-center gap-1"
                                        >
                                            <span x-text="activeLogs['{{ $log->id }}'] ? 'Hide Details' : 'View Details'"></span>
                                            
                                            <svg class="w-4 h-4 transition-transform duration-300" 
                                                :class="activeLogs['{{ $log->id }}'] ? 'rotate-180' : ''" 
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </td>   
                                </tr>

                                {{-- 详情区域 --}}
                                <tr x-show="activeLogs['{{ $log->id }}']" class="bg-slate-50" x-cloak>
                                    <td colspan="5" class="px-6 py-4">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                            
                                            {{-- Previous Data --}}
                                            <div class="bg-white p-3 rounded-lg border border-gray-200 shadow-sm">
                                                <p class="font-bold text-gray-500 mb-2 uppercase tracking-wide">Previous Data</p>
                                                {{-- 外层 div 控制滚动，内层 pre 保持 JSON 结构 --}}
                                                <div class="max-h-[300px] overflow-auto border border-gray-200 p-2 bg-gray-50 rounded">
                                                    <pre class="font-mono text-gray-700 text-[11px]">{{ json_encode($log->old_values ?? [], JSON_PRETTY_PRINT) }}</pre>
                                                </div>
                                            </div>

                                            {{-- New Changes --}}
                                            <div class="bg-indigo-50 p-3 rounded-lg border border-indigo-100 shadow-sm">
                                                <p class="font-bold text-indigo-500 mb-2 uppercase tracking-wide">New Changes</p>
                                                <div class="max-h-[300px] overflow-auto border border-indigo-200 p-2 bg-white/50 rounded">
                                                    <pre class="font-mono text-indigo-900 text-[11px]">{{ json_encode($log->new_values ?? [], JSON_PRETTY_PRINT) }}</pre>
                                                </div>
                                            </div>

                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <x-modals.password-modal />
                </div>

                <div class="bg-white px-6 py-4 border-t border-gray-100">
                    {{ $logs->links() }}
                </div>
            </div>
        </div>
    </div>
    <script>
        function toggleDetails(logId, btnElement) {
            const detailRow = document.getElementById('modal-' + logId);
            const isHidden = detailRow.classList.contains('hidden');
            
            if (isHidden) {
                detailRow.classList.remove('hidden');
            } else {
                detailRow.classList.add('hidden');
            }
        }
    </script>
</x-app-layout>