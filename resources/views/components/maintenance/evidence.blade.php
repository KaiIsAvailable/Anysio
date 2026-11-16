@props(['file'])
@php($url = route('maintenance.evidence', $file))
<div class="rounded-xl border p-3">
    @if($file->mime === 'application/pdf')
        <x-customer-service.pdf-attachment :url="$url" :filename="$file->filename" />
    @elseif(str_starts_with($file->mime, 'image/'))
        <a href="{{ $url }}" target="_blank" rel="noopener"><img src="{{ $url }}" alt="Maintenance evidence" loading="lazy" class="max-h-48 rounded-lg"></a>
    @elseif(str_starts_with($file->mime, 'video/'))
        <video src="{{ $url }}" controls preload="metadata" class="max-h-48"></video>
    @endif
    <a href="{{ $url }}?download=1" class="block break-words text-sm text-indigo-700 underline">{{ $file->filename }}</a>
</div>
