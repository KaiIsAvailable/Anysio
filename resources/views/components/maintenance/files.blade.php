@props(['existingFiles' => collect()])
<div class="space-y-2">
    <x-form.input-label value="Evidence (optional)" />
    <x-form.file-input multiple data-maintenance-files accept="{{ implode(',', array_map(fn ($ext) => '.'.$ext, array_keys(config('customer_service.types')))) }}" />
    <p class="text-xs text-gray-500">Up to 10 files, 100 MB total. Images 2 MB, videos 20 MB, documents 3 MB each. All selected files must upload successfully.</p>
    @if($existingFiles->isNotEmpty())
        <p class="text-xs text-gray-500">Saved evidence. To replace a file, mark it for removal and upload its replacement. Changes apply when you save.</p>
    @endif
        <div class="grid grid-cols-3 gap-3">
            @foreach($existingFiles as $file)
                <div class="space-y-2" x-data="{ removed: @js(in_array($file->id, old('remove_attachment_ids', []))) }">
                    <input type="hidden" name="remove_attachment_ids[]" value="{{ $file->id }}" :disabled="!removed">
                    <div class="relative">
                        <div :class="{ 'opacity-50': removed }"><x-maintenance.evidence :file="$file" :compact="true" /></div>
                        <button type="button" x-on:click="removed = true" :disabled="loading" x-show="!removed"
                            class="absolute right-3 top-3 z-10 flex h-7 w-7 items-center justify-center rounded-full bg-white text-xl leading-none text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-red-50 hover:text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                            aria-label="Remove {{ $file->filename }}" title="Remove file">&times;</button>
                    </div>
                    <button type="button" x-on:click="removed = false" :disabled="loading" x-show="removed" x-cloak class="text-sm text-red-700 underline disabled:opacity-50">Undo removal</button>
                    <p x-show="removed" x-cloak class="text-xs text-gray-500">Marked for removal when saved.</p>
                </div>
            @endforeach
            <div data-maintenance-files-list class="contents"></div>
        </div>
</div>
