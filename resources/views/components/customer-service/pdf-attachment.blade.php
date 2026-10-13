<div data-pdf-url="{{ $url }}" class="w-40 max-w-full">
    <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="Open PDF preview" class="block rounded-lg border border-indigo-200 bg-white p-2">
        <canvas data-pdf-canvas hidden class="mx-auto max-w-full" style="max-height:160px" aria-label="PDF first page"></canvas>
        <span data-pdf-fallback class="flex h-28 items-center justify-center gap-2 text-indigo-700">
            <svg aria-hidden="true" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z M14 2v6h6 M8 13h8 M8 17h5"/></svg>PDF
        </span>
    </a>
    <p class="mt-1 break-words text-xs">{{ $filename }}</p>
    <a href="{{ $url }}" target="_blank" rel="noopener" class="text-sm text-indigo-700 underline">Open preview</a>
</div>
