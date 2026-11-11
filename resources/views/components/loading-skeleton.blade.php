@props([
    'variant' => 'document',
    'label' => 'Loading...',
    'columns' => 6,
])

{{-- Pass Alpine visibility directives and layout classes through the attribute bag. --}}
<div {{ $attributes->merge(['role' => 'status', 'aria-live' => 'polite']) }}>
    <span class="sr-only">{{ $label }}</span>
    @if ($variant === 'table-rows')
        <div class="divide-y divide-gray-100 motion-safe:animate-pulse" aria-hidden="true">
            @foreach (range(1, 5) as $row)
                <div class="grid items-center gap-4 px-4 py-5" style="grid-template-columns: repeat({{ (int) $columns }}, minmax(0, 1fr))">
                    @foreach (range(1, (int) $columns) as $column)
                        <div class="h-4 rounded bg-slate-100"></div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @elseif ($variant === 'statement')
        <div class="space-y-5 motion-safe:animate-pulse" aria-hidden="true">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-lg">
                @foreach (range(1, 2) as $card)
                    <div class="rounded-lg bg-slate-50 px-4 py-3 space-y-3">
                        <div class="h-3 w-32 rounded bg-slate-200"></div>
                        <div class="h-6 w-24 rounded bg-slate-200"></div>
                    </div>
                @endforeach
            </div>
            <div class="overflow-hidden rounded-lg border border-gray-100">
                <div class="grid grid-cols-3 sm:grid-cols-6 gap-4 bg-gray-50 px-4 py-4">
                    @foreach (range(1, 6) as $column)
                        <div class="h-3 rounded bg-slate-200 {{ $column > 3 ? 'hidden sm:block' : '' }}"></div>
                    @endforeach
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach (range(1, 6) as $row)
                        <div class="grid grid-cols-3 sm:grid-cols-6 items-center gap-4 px-4 py-5">
                            @foreach (range(1, 6) as $column)
                                <div class="h-4 rounded bg-slate-100 {{ $column > 3 ? 'hidden sm:block' : '' }}"></div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @elseif ($variant === 'checkout')
        <div class="space-y-5">
            <div aria-hidden="true" class="grid grid-cols-1 gap-4 md:grid-cols-2 motion-safe:animate-pulse">
                <div class="space-y-3"><div class="h-3 w-24 rounded bg-slate-200"></div><div class="h-11 rounded-lg bg-slate-100"></div></div>
                <div class="space-y-3"><div class="h-3 w-36 rounded bg-slate-200"></div><div class="h-11 rounded-lg bg-slate-100"></div></div>
            </div>
            <div aria-hidden="true" class="rounded-xl border border-slate-200 bg-white p-5 motion-safe:animate-pulse">
                <div class="mb-6 h-3 w-36 rounded bg-slate-200"></div>
                <div class="flex items-center justify-between gap-3">
                    @for($step = 0; $step < 5; $step++)
                        <div class="flex flex-1 flex-col items-center gap-3"><div class="h-8 w-8 rounded-full bg-slate-100"></div><div class="h-2.5 w-12 rounded bg-slate-100"></div></div>
                    @endfor
                </div>
            </div>
            <div aria-hidden="true" class="overflow-hidden rounded-xl border border-slate-200 bg-white motion-safe:animate-pulse">
                <div class="border-b border-slate-100 p-5"><div class="h-4 w-48 rounded bg-slate-200"></div></div>
                <div class="space-y-5 p-5">
                    @for($row = 0; $row < 3; $row++)
                        <div class="flex items-center justify-between gap-6"><div class="h-3 w-40 rounded bg-slate-100"></div><div class="h-3 w-20 rounded bg-slate-100"></div></div>
                    @endfor
                    <div class="flex items-center justify-between border-t border-slate-100 pt-5"><div class="h-4 w-32 rounded bg-slate-200"></div><div class="h-5 w-24 rounded bg-slate-200"></div></div>
                </div>
            </div>
        </div>
    @else
        <div class="space-y-8 motion-safe:animate-pulse" aria-hidden="true">
            <div class="h-7 w-2/3 mx-auto rounded bg-slate-200"></div>
            @foreach (range(1, 4) as $paragraph)
                <div class="space-y-3">
                    <div class="h-4 w-1/3 rounded bg-slate-200"></div>
                    <div class="h-3 w-full rounded bg-slate-100"></div>
                    <div class="h-3 w-full rounded bg-slate-100"></div>
                    <div class="h-3 w-3/4 rounded bg-slate-100"></div>
                </div>
            @endforeach
        </div>
    @endif
</div>
