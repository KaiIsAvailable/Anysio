@props(['name', 'sortField' => null])

@php
    $currentSort = request()->query('sort');
    $isAsc = $sortField && $currentSort === "{$sortField}_asc";
    $isDesc = $sortField && $currentSort === "{$sortField}_desc";
    $nextSort = $isAsc ? "{$sortField}_desc" : "{$sortField}_asc";
    
    // 获取当前路由的所有参数 (比如 property 的 ID)
    $routeParams = request()->route()->parameters();
    // 合并路由参数和查询参数
    $allParams = array_merge($routeParams, request()->query(), ['sort' => $nextSort]);
@endphp

<th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">
    @if($sortField)
        {{-- 使用 $allParams 生成路由，这样会自动包含 {property} 参数 --}}
        <a href="{{ route(request()->route()->getName(), $allParams) }}"
           class="inline-flex items-center space-x-1 text-gray-700 hover:text-indigo-600">
            <span>{{ $name }}</span>
            
            @if($isAsc)
                <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                </svg>
            @elseif($isDesc)
                <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            @else
                {{-- 只有当有 sortField 但当前未被选中时，显示灰色默认图标 --}}
                <svg class="h-4 w-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                </svg>
            @endif
        </a>
    @else
        <span class="text-gray-500">{{ $name }}</span>
    @endif
</th>