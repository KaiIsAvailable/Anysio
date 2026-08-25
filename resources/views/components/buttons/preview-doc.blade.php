@props([
    'type',              // 'invoice' 或 'receipt'
    'color' => 'indigo', // 前缀颜色，例如 indigo, emerald
    'titleExpr',         // Alpine 表达式: 标题
    'contentExpr',       // Alpine 表达式: 模板 HTML
    'variablesExpr',     // Alpine 表达式: 变量字典
    'itemsExpr' => '[]', // Alpine 表达式: 明细对象数组
    'extraExpr' => '{}', // Alpine 表达式: 其他特殊数据 (支付额等)
    'buttonTextExpr',    // Alpine 表达式: 按钮文字
])

<button type="button"
    class="inline-flex items-center gap-1 text-[11px] font-bold text-{{ $color }}-600 hover:text-{{ $color }}-800 bg-{{ $color }}-50 hover:bg-{{ $color }}-100 px-2 py-1.5 rounded-md border border-{{ $color }}-200 transition-all shadow-sm w-fit"
    @click="window.openDocumentPreview('{{ $type }}', {{ $contentExpr }}, {{ $variablesExpr }}, {{ $itemsExpr }}, {{ $titleExpr }}, {{ $extraExpr }})"
>
    <!-- 智能渲染对应 Icon -->
    @if($type === 'receipt')
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
    @else
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        </svg>
    @endif
    <span x-text="{{ $buttonTextExpr }}"></span>
</button>