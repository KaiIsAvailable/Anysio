<div x-data="{ 
    open: false, 
    notifications: [],
    async fetchNotifications() {
        this.notifications = await fetch('{{ route('admin.notifications.index') }}').then(r => r.json());
    },
    async markAsRead(item) {
        console.log('Clicked item:', item);
        // 如果已经在前端被标记为已读，直接跳转
        if (item.is_read) {
            window.location.href = item.data.url;
            return;
        }

        try {
            const response = await fetch('{{ route('admin.notifications.read', ':id') }}'.replace(':id', item.id), {
                method: 'PATCH',
                headers: { 
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();
            
            if (data.success) {
                // 成功后重新刷新列表，或者手动设置 item.is_read = true
                this.fetchNotifications();
                window.location.href = item.data.url;
            } else {
                console.error('Update failed:', data);
            }
        } catch (error) {
            console.error('Network Error:', error);
        }
    }
}" x-init="fetchNotifications()" class="relative">

    <button @click="open = !open" class="p-2 text-gray-500 hover:text-gray-700 transition relative">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
        </svg>
        <span x-show="notifications.filter(i => !i.is_read).length > 0" 
              x-text="notifications.filter(i => !i.is_read).length"
              class="absolute top-0 right-0 flex items-center justify-center h-5 w-5 rounded-full bg-red-600 text-white text-[10px] font-bold border-2 border-white">
        </span>
    </button>

    <div x-show="open" @click.away="open = false" x-cloak
         class="absolute right-0 mt-3 w-96 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 overflow-hidden">
        
        <div class="px-4 py-3 border-b bg-gray-50 text-xs font-bold text-gray-500 uppercase tracking-widest">Notifications</div>
        
        <div class="h-[300px] overflow-y-auto scrollbar-thin scrollbar-thumb-gray-200 scrollbar-track-transparent">
            <template x-for="item in notifications" :key="item.id">
                <a href="javascript:void(0)" 
                @click.stop="markAsRead(item)" 
                :class="item.is_read ? 'opacity-50 hover:bg-blue-50/50' : 'hover:bg-blue-50/50'"
                class="block p-4 border-b border-gray-50 transition cursor-pointer">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 mr-4">
                            <div :class="item.is_read ? 'bg-gray-50 text-gray-400' : 'bg-blue-50 text-blue-600'" 
                                class="p-2 rounded-lg transition-colors">
                                
                                <svg x-show="!item.is_read" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                </svg>

                                <svg x-show="item.is_read" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 9v.906a2.25 2.25 0 0 1-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 0 0 1.183 1.981l6.478 3.488m8.839 2.51-4.66-2.51m0 0-1.023-.55a2.25 2.25 0 0 0-2.134 0l-1.022.55m0 0-4.661 2.51m16.5 1.615a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V8.844a2.25 2.25 0 0 1 1.183-1.981l7.5-4.039a2.25 2.25 0 0 1 2.134 0l7.5 4.039a2.25 2.25 0 0 1 1.183 1.98V19.5Z" />
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-gray-900 truncate" x-text="item.title"></p>
                            <p class="text-xs text-gray-500 mt-0.5" x-text="`For ${item.data.entity_name}`"></p>
                            <p class="text-[10px] text-gray-400 mt-1 font-medium" x-text="item.time"></p>
                        </div>
                        <div x-show="!item.is_read" class="flex-shrink-0 self-center">
                            <button @click.stop="markAsRead(item)" 
                                    class="p-1.5 text-blue-600 hover:bg-blue-100 rounded-full transition-all"
                                    title="Mark as read">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                            </button>
                        </div>
                    </div>
                </a>
            </template>
        </div>
    </div>
</div>