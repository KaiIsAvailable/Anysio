@props(['file', 'compact' => false])
@php($url = route('maintenance.evidence', $file))
<div @class(['min-w-0 border', 'rounded-xl p-3' => !$compact, 'flex h-full flex-col gap-2 rounded-lg border-gray-200 p-2 text-sm' => $compact])>
    @if($file->mime === 'application/pdf')
        <x-customer-service.pdf-attachment :url="$url" :filename="$file->filename" />
    @elseif(str_starts_with($file->mime, 'image/'))
        <a href="{{ $url }}" target="_blank" rel="noopener"><img src="{{ $url }}" alt="{{ $file->filename }}" loading="lazy" @class(['w-full bg-gray-50 object-contain', 'h-40 rounded-lg' => !$compact, 'h-28 rounded-md' => $compact])></a>
    @elseif(str_starts_with($file->mime, 'video/'))
        <video src="{{ $url }}" controls preload="metadata" class="max-h-48"></video>
    @endif
    <a href="{{ $url }}?download=1" @class(['block break-words text-sm text-indigo-700 underline', 'pr-8' => $compact && !str_starts_with($file->mime, 'image/')])>{{ $file->filename }}</a>
</div>
