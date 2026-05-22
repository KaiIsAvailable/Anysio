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

                <div class="flex-shrink-0" x-data="{loading: false}">
                    @can('owner-admin')
                        <x-form.primary-button
                            type="button"
                            loading="loading"
                            @click="loading = true; window.location.href = '{{ route('admin.roomAsset.create') }}'"
                            >

                            <svg x-show="!loading" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Create New Asset
                        </x-form.primary-button>
                    @endcan
                </div>
            </div>

            {{-- 💡 应用领导的搜索组件 (Search Component) --}}
            <div class="mb-6 flex flex-col md:flex-row justify-end">
                <x-form.form action="{{ URL::current() }}" method="GET" class="flex items-center w-full max-w-md justify-end">
                    <x-table.search placeholder="Search asset name or category..." />
                </x-form.form>
            </div>

            <div class="bg-white shadow-sm rounded-xl overflow-hidden border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 ">
                    <thead class="bg-gray-50">
                        <tr>
                            {{-- 💡 应用领导的表头排序组件 (Table TH Component) --}}
                            <x-table.th name="Asset Name" sortField="name" />
                            <x-table.th name="Created By" /> {{-- 不传 sortField 变为普通表头 --}}
                            <x-table.th name="Category" sortField="category" />
                            <x-table.th name="Status" sortField="status" />
                            <x-table.th name="Date Created" sortField="created_at" />
                            <x-table.th name="Action" class="text-center"/>
                            
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        {{-- 下方的所有 tr, td, 状态 badge 和 SVG 按钮 100% 原封不动保留 --}}
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

                                <td class="px-6 py-4 whitespace-nowrap text-left">
                                    <div class="flex items-center justify-center space-x-2" onclick="event.stopPropagation();">
                                        
                                        {{-- Edit 按钮 --}}
                                        <a href="{{ route('admin.roomAsset.edit', $asset->id) }}"
                                        class="p-2 text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors"
                                        title="Edit">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </a>

                                        {{-- Delete / Restore 表单 --}}
                                        @if(strtolower($asset->status) === 'inactive')
                                            {{-- 恢复按钮 (Restore) --}}
                                            <x-form.form action="{{ route('admin.roomAsset.restore', $asset->id) }}" method="POST"
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
                                            </x-form.form>
                                        @else
                                            {{-- 删除按钮 (Delete) --}}
                                            <x-form.form action="{{ route('admin.roomAsset.destroy', $asset->id) }}" method="POST"
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
                                            </x-form.form>
                                        @endif

                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                                    <p class="text-base font-medium">No assets found</p>
                                </td>
                            </tr>
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