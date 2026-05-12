<x-app-layout>
    <div class="bg-gray-50 min-h-screen p-6">
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    {{-- 1. 这里的返回链接建议改回管理首页或者资产首页 --}}
                    <a href="{{ route('admin.properties.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                        &larr; Back to Dashboard
                    </a>
                    {{-- 2. 标题改为 Asset Library --}}
                    <h1 class="text-2xl font-bold text-slate-900">Asset Library</h1>
                    <p class="text-sm text-gray-500 mt-1">Manage standard asset templates used across properties.</p>
                </div>

                <div class="flex items-center space-x-3">
                    {{-- 3. 创建路由改为 assets.create --}}
                    <a href="{{ route('admin.roomAsset.create') }}" 
                       class="inline-flex items-center px-4 py-2.5 text-sm font-semibold text-white bg-indigo-600 rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Create New Asset
                    </a>
                </div>
            </div>

            {{-- 搜索框部分保持不变 --}}
            <div class="mb-6 flex flex-col md:flex-row justify-end">
                <form action="{{ URL::current() }}" method="GET" class="flex items-center w-full max-w-md">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text" name="search" value="{{ request('search') }}"
                            style="padding-left: 45px;"
                            class="block w-full pl-11 pr-3 py-2 border border-gray-300 rounded-l-lg focus:ring-indigo-500 focus:border-indigo-500 text-sm" 
                            placeholder="Search asset name or category...">
                    </div>
                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-r-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                        Search
                    </button>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-xl overflow-hidden border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Asset Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Created</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($assets as $asset) 
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 font-bold shadow-sm">
                                            {{ mb_strtoupper(mb_substr($asset->name ?? 'A', 0, 1, 'UTF-8')) }}
                                        </div>
                                        <div class="ml-3 text-sm font-medium text-gray-900">{{ $asset->name ?? ''}}</div>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <span class="text-sm text-gray-600">{{ $asset->user->name ?? '' }}</span>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                                        {{ $asset->category ?? '' }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <span class="text-sm text-gray-600">{{ $asset->status ?? '' }}</span>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $asset->created_at ? $asset->created_at->format('d M Y') : '-' }}
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    {{-- 1. 确保 flex 容器包裹了所有的按钮和表单 --}}
                                    <div class="flex items-center justify-center space-x-2" onclick="event.stopPropagation();">
                                        
                                        {{-- Edit 按钮 --}}
                                        <a href="{{ route('admin.roomAsset.edit', $asset->id) }}"
                                        class="p-2 text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors"
                                        title="Edit">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </a>

                                        {{-- Delete 表单 --}}
                                        @if(strtolower($asset->status) === 'inactive')
                                            {{-- 恢复按钮 (Restore) --}}
                                            <form action="{{ route('admin.roomAsset.restore', $asset->id) }}" method="POST"
                                                onsubmit="return confirm('Restore asset {{ addslashes($asset->name) }}?');"
                                                class="inline-block m-0">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" 
                                                        class="p-2 text-green-600 hover:text-green-900 bg-green-50 hover:bg-green-100 rounded-lg transition-colors" 
                                                        title="Restore">
                                                    {{-- 恢复图标 (逆时针箭头) --}}
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        @else
                                            {{-- 删除按钮 (Delete) --}}
                                            <form action="{{ route('admin.roomAsset.destroy', $asset->id) }}" method="POST"
                                                onsubmit="return confirm('Delete asset {{ addslashes($asset->name) }}?');"
                                                class="inline-block m-0">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="p-2 text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 rounded-lg transition-colors" 
                                                        title="Delete">
                                                    {{-- 垃圾桶图标 --}}
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif

                                    </div> {{-- 2. 在这里闭合 div --}}
                                </td>
                            </tr>
                        @empty
                            {{-- 空状态部分保持不变 --}}
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $assets->links() }}
            </div>
        </div>
    </div>
</x-app-layout>