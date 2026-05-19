@props(['name', 'sortField'])

@php
    // 获取当前的排序字段和方向
    $currentSort = request()->query('sort'); // 假设你的 sort 参数格式是 'name_asc' 或 'name_desc'
    $isAsc = $currentSort === "{$sortField}_asc";
    $isDesc = $currentSort === "{$sortField}_desc";
    
    // 计算点击后的下一个排序状态
    $nextSort = $isAsc ? "{$sortField}_desc" : "{$sortField}_asc";
@endphp

<th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">
    <a href="{{ route(request()->route()->getName(), array_merge(request()->query(), ['sort' => $nextSort])) }}"
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
            {{-- 可选：显示一个灰色的默认图标或不显示 --}}
            <svg class="h-4 w-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
            </svg>
        @endif
    </a>
</th>