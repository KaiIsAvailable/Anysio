<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- 1. 反馈提示区域 (新增) --}}
            @if(session('success'))
                <x-alert type="success" :message="session('success')" />
            @endif
            @if(session('error'))
                <x-alert type="error" :message="session('error')" />
            @endif
            @if(session('status')) {{-- 处理 Store 方法返回的特殊状态 --}}
                <x-alert type="success" :message="session('status')['message']" :details="'Email: '.session('status')['email']" />
            @endif

            {{-- Header Section --}}
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">User Management</h1>
                    <p class="mt-2 text-sm text-gray-500">Managing subscriptions, usage, and roles for system users.</p>
                </div>
                <div class="flex-shrink-0">
                    <a href="{{ route('admin.userManagement.create') }}" 
                       class="inline-flex items-center justify-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition-all duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add New Manager
                    </a>
                </div>
            </div>

            {{-- DataTable Component Apply --}}
            <x-data-table 
                :collection="$userManagement" 
                :columns="['User Details', 'Role & Referral', 'Subscription', 'Usage/Discount', 'Joined Date']"
            >
                {{-- Search Bar Slot --}}
                <x-slot name="header">
                    <div class="flex justify-end">
                        <form method="GET" action="{{ route('admin.userManagement.index') }}" class="flex items-stretch gap-2">
                            <div class="flex items-stretch relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none z-10">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                
                                {{-- 使用你的 x-text-input 组件 (修改点) --}}
                                <x-text-input 
                                    name="search" 
                                    value="{{ request('search') }}"
                                    class="w-72 sm:w-80 !rounded-r-none"
                                    style="padding-left: 2.75rem;"
                                    placeholder="Search by name or email..."
                                />
                                
                                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-r-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                                    Search
                                </button>

                                @if(request('search'))
                                    <a href="{{ route('admin.userManagement.index') }}" class="ml-2 inline-flex items-center px-3 py-2 rounded-lg bg-white hover:bg-gray-100 text-slate-900 border border-gray-200 text-sm transition-all">
                                        Clear
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>
                </x-slot>

                {{-- Table Body Row Content --}}
                @foreach($userManagement as $user)
                    <tr class="hover:!bg-indigo-50/50 transition-colors cursor-pointer group duration-150" 
                        onclick="window.location='{{ route('admin.userManagement.show', $user->id) }}'">
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 font-bold shadow-sm">
                                    {{ strtoupper(substr($user->user_name ?? $user->name ?? 'U', 0, 1)) }}
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-semibold text-slate-900">{{ $user->user_name ?? $user->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $user->user_email ?? $user->email }}</div>
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="inline-flex items-center px-2.5 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-50 text-blue-700 border border-blue-100 mb-1">
                                {{ $user->role ?? 'N/A' }}
                            </div>
                            <div class="text-xs text-gray-400">
                                Ref: <span class="font-medium text-indigo-600">{{ $user->applied_ref_code ?? 'None' }}</span>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $status = strtolower($user->subscription_status ?? 'unknown');
                                $color = match($status) {
                                    'active' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                    'pending' => 'bg-amber-100 text-amber-800 border-amber-200',
                                    'expired' => 'bg-rose-100 text-rose-800 border-rose-200',
                                    default => 'bg-gray-100 text-gray-800 border-gray-200',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $color }}">
                                {{ ucfirst($status) }}
                            </span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-slate-700 font-medium">Count: {{ $user->usage_count ?? 0 }}</div>
                            <div class="text-xs text-indigo-600">Disc: {{ $user->discount_rate ?? 0 }}%</div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-slate-600">
                                {{ $user->created_at ? \Carbon\Carbon::parse($user->created_at)->format('d M, Y') : 'N/A' }}
                            </span>
                        </td>

                        {{-- 操作列 --}}
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" onclick="event.stopPropagation()">
                            <div class="flex items-center justify-end space-x-2">
                                <a href="{{ route('admin.userManagement.edit', $user->id) }}" class="p-2 text-indigo-600 hover:text-indigo-900 bg-white border border-gray-200 rounded-lg hover:border-indigo-300 shadow-sm transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </a>
                                <form action="{{ route('admin.userManagement.destroy', $user->id) }}" method="POST" onsubmit="return confirm('Delete this manager?');" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-2 text-rose-600 hover:text-rose-900 bg-white border border-gray-200 rounded-lg hover:border-rose-300 shadow-sm transition-all">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>
</x-app-layout>