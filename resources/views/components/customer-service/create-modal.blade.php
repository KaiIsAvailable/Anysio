@props(['action', 'id' => 'complaint-create', 'leases' => []])
@php
    $types = config('customer_service.types');
    $media = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov'];
    $accept = fn ($extensions) => implode(',', array_map(fn ($ext) => '.'.$ext, $extensions));
@endphp
<dialog id="{{ $id }}" data-complaint-modal data-limits="{{ json_encode(config('customer_service')) }}" aria-labelledby="{{ $id }}-title" class="w-full max-w-md max-h-[90vh] overflow-y-auto rounded-2xl p-0 shadow-xl backdrop:bg-slate-900/50">
    <x-form.form data-complaint-form :action="$action" :async="true" :preserve-textarea-enter="true" :guard-composition="true" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h2 id="{{ $id }}-title" class="text-xl font-bold text-slate-900">Complaint</h2>
            <button type="button" data-close-complaint aria-label="Close complaint" class="text-2xl px-2 text-gray-600">&times;</button>
        </div>
        @if(count($leases) > 1)
            @php
                $leaseOptions = collect($leases)->map(fn ($lease) => ['value' => $lease->id, 'label' => $lease->leasable_name.' · '.$lease->leasable_type_label.' · '.$lease->start_date->format('d M Y').' – '.$lease->end_date->format('d M Y')])->all();
            @endphp
            <div data-lease-selection x-bind:inert="loading" x-data="{ complaintLease: [] }" @complaint-reset.window="complaintLease = []">
                <p class="block text-sm font-semibold text-gray-700 mb-2">Lease *</p>
                <x-form.input-select name="lease_id" :options="$leaseOptions" placeholder="Select a lease" x-model="complaintLease" x-bind:disabled="loading" required />
            </div>
        @endif
        <div>
            <label for="{{ $id }}-subject" class="block text-sm font-semibold text-gray-700 mb-2">Subject *</label>
            <input id="{{ $id }}-subject" name="subject" required maxlength="255" class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" autofocus>
        </div>
        <div>
            <label for="{{ $id }}-details" class="block text-sm font-semibold text-gray-700 mb-2">Details</label>
            <textarea id="{{ $id }}-details" name="details" rows="4" maxlength="{{ config('customer_service.max_text_length') }}" class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            <p class="text-xs text-gray-500">Enter details or attach at least one file. You can provide both.</p>
        </div>
        <details class="rounded-xl border p-3">
            <summary class="cursor-pointer text-sm font-semibold text-indigo-700">Add attachments</summary>
            <div class="grid gap-2 pt-3">
                <button type="button" data-create-pick="document" class="text-left text-sm p-2 rounded-lg hover:bg-gray-100">Document</button>
                <button type="button" data-create-pick="media" class="text-left text-sm p-2 rounded-lg hover:bg-gray-100">Photos &amp; Videos</button>
                <button type="button" data-create-pick="camera" class="text-left text-sm p-2 rounded-lg hover:bg-gray-100">Camera</button>
            </div>
        </details>
        <input type="file" data-create-files="document" accept="{{ $accept(array_values(array_diff(array_keys($types), $media))) }}" multiple hidden>
        <input type="file" data-create-files="media" accept="{{ $accept($media) }}" multiple hidden>
        <input type="file" data-create-files="camera" accept="image/*" capture="environment" hidden>
        <p class="text-xs text-gray-500">Up to {{ config('customer_service.max_files') }} files, {{ config('customer_service.max_total_kb') / 1024 }} MB total. Images: {{ $types['jpg']['max_kb'] / 1024 }} MB; videos: {{ $types['mp4']['max_kb'] / 1024 }} MB; documents: {{ $types['pdf']['max_kb'] / 1024 }} MB each.</p>
        <div data-create-previews class="flex gap-2 overflow-x-auto" aria-live="polite"></div>
        <p data-create-error role="alert" class="text-sm whitespace-pre-line text-red-700"></p>
        <div class="flex justify-end gap-2">
            <button type="button" data-cancel-complaint class="rounded-xl bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700">Cancel</button>
            <x-form.primary-button loading="loading" data-create-submit>Create</x-form.primary-button>
        </div>
    </x-form.form>
</dialog>
