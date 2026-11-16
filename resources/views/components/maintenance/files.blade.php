<div class="space-y-2">
    <label class="block text-sm font-medium">Evidence (optional)</label>
    <input type="file" multiple data-maintenance-files class="block w-full text-sm" accept="{{ implode(',', array_map(fn ($ext) => '.'.$ext, array_keys(config('customer_service.types')))) }}">
    <p class="text-xs text-gray-500">Up to 10 files, 100 MB total. Images 2 MB, videos 20 MB, documents 3 MB each. All selected files must upload successfully.</p>
    <div data-maintenance-files-list class="space-y-1"></div>
</div>
