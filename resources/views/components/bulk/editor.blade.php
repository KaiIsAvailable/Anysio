    <style>
        .bulk-editor-container { height: calc(100vh - 65px); height: calc(100dvh - 65px); }
        .bulk-sheet input:focus { background: #eef2ff; outline: 2px solid #6366f1; outline-offset: -2px; box-shadow: none; }
        .bulk-sheet input:disabled { background: #f8fafc; }
        .bulk-sheet td { border: 1px solid #e5e7eb; padding: 0; }
        .bulk-sheet th { border: 1px solid #e5e7eb; padding: 6px 8px; white-space: nowrap; }
        .bulk-sheet td[aria-invalid="true"] { background: #fff1f2; box-shadow: inset 0 0 0 2px #ef4444; }
        .bulk-sheet td[aria-invalid="true"] input { border-color: #ef4444; }
        .bulk-sheet .bulk-entry-row td { border-top: 2px solid #c7d2fe; background: #eef2ff; }
        .bulk-sheet td { scroll-margin-top: 5rem; }
    </style>
    <div x-ref="bulkSheet" class="bulk-editor-container flex min-h-0 flex-col overflow-hidden px-3 py-5 sm:px-5" x-data="{{ $editorFactory }}(@js($rows), @js(auth()->user()->canAny(['super-admin', $permissionModule.'.create'])), @js($draftKey ?? null), @js((bool) session($permissionModule.'_bulk_saved')), @js($records->firstItem() ?? 1), @js($operationKey ?? null), @js($progressConfig ?? []), @js(isset($errors) ? $errors->getMessages() : []), @js($gridConfig ?? []))" @pageshow.window="resumeFromHistory($event)" @pagehide.window="persistDraft()" @beforeunload.window="persistDraft(); if (hasChanges() && !draftStored) { $event.preventDefault(); $event.returnValue = ''; }">
        <div class="mb-4 flex shrink-0 flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route($routePrefix.'.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to List
                </a>
                <h1 class="mt-2 text-2xl font-bold text-slate-900">Bulk Edit {{ $recordTitle }}</h1>
                @if($hierarchy ?? false)
                    <p class="mt-1 text-sm text-slate-500">Add properties, link their units, then add rooms. Save all three together.</p>
                @endif
            </div>
        </div>
        <x-form.form action="{{ route($routePrefix.'.bulk-save') }}" method="POST" enctype="multipart/form-data" :async="true" :autofocus="false" novalidate class="flex min-h-0 flex-1 flex-col" @submit="prepareSubmission($event)" @pageshow.window="if (!operationKey) saving = false">
            <x-form.text-input type="hidden" name="rows_json" x-ref="bulkPayload" />
            <x-form.text-input type="hidden" name="page" value="{{ $records->currentPage() }}" />
            <x-form.text-input type="hidden" name="request_key" value="{{ $saveRequestKey ?? (string) \Illuminate\Support\Str::uuid() }}" />
            <fieldset class="m-0 flex min-h-0 min-w-0 w-full flex-1 flex-col border-0 p-0" :disabled="!!operationKey">
            <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-gray-200 bg-white">
                <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-3 py-2 text-xs text-gray-500">
                    <span>Use arrow keys to move between cells.</span>
                    <span x-show="loadingRows || restoringDraft" x-cloak role="status">Loading {{ $recordPlural }}...</span>
                    <button type="button" x-show="rowsLoadFailed" x-cloak @click="retryLoadingRows()" class="text-indigo-600 hover:underline">Retry loading {{ $recordPlural }}</button>
                    <span><span x-text="counts().created"></span> new, <span x-text="counts().updated"></span> edited, <span x-text="counts().removed"></span> marked for removal</span>
                </div>
                    @if($hierarchy ?? false)
                        <nav class="flex shrink-0 gap-1 overflow-x-auto border-b border-slate-200 bg-white px-3 py-2" aria-label="Property hierarchy" role="tablist">
                            @foreach($sheets as $entity => $sheet)
                                <button type="button" role="tab" :aria-selected="activeEntity === '{{ $entity }}'" :class="activeEntity === '{{ $entity }}' ? 'bg-indigo-100 text-indigo-700' : 'text-slate-600 hover:bg-slate-100'" class="flex items-center gap-1 whitespace-nowrap rounded-md px-4 py-2 text-sm font-semibold" :disabled="saving || loadingRows || restoringDraft" @click="switchEntity('{{ $entity }}')">{{ $sheet['label'] }} <span class="ml-1 text-xs" x-text="visibleRows('{{ $entity }}').filter(row => !isEntryRow(row)).length"></span><span x-show="entityHasErrors('{{ $entity }}')" x-cloak class="ml-1 h-2 w-2 rounded-full bg-red-600" role="img" aria-label="Validation errors"></span></button>
                            @endforeach
                        </nav>
                    @endif
                <div x-ref="gridViewport" class="min-h-0 flex-1 overflow-auto" @wheel.capture="scrollHorizontally($event)" @scroll="handleGridScroll()" @resize.window="updateErrorVisibility()" @invalid.capture.prevent="showInvalidField($event)">
                    @if($hierarchy ?? false)
                        @foreach($sheets as $entity => $sheet)
                            <div x-show="activeEntity === '{{ $entity }}'" x-cloak role="tabpanel" aria-label="{{ $sheet['label'] }}">
                                @include('components.bulk.grid', array_merge($sheet, ['sheetEntity' => $entity]))
                            </div>
                        @endforeach
                    @else
                        @include('components.bulk.grid')
                    @endif
                    <p x-show="rows.length === 0" class="p-8 text-center text-sm text-gray-500" x-cloak>No {{ $recordPlural }} to display.</p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-3 py-3">
                    <x-form.secondary-button @click="$dispatch('open-{{ $modalPrefix }}-discard')" x-bind:disabled="saving || !hasChanges()">Discard Changes</x-form.secondary-button>
                    <x-form.primary-button type="submit" loading="saving" :disabledWhen="'!hasChanges() || loadingRows || restoringDraft || pendingDraftRows.length > 0'">{{ ($hierarchy ?? false) ? 'Save All Changes' : 'Save Changes' }}</x-form.primary-button>
                </div>
            </div>
            </fieldset>
        </x-form.form>
        <x-modals.confirmation-modal id="{{ $modalPrefix }}-saving" title="Saving {{ $recordTitle }}" :closable="false" :show="filled($operationKey ?? null)" maxWidth="sm:max-w-lg">
            <div class="space-y-5 p-6" role="status" aria-live="polite">
                <div class="flex items-end justify-between gap-3">
                    <p class="text-sm font-medium text-slate-600"><span x-text="displayedProcessed"></span> of <span x-text="progress.total"></span> processed</p>
                    <p class="text-3xl font-bold text-indigo-600" x-text="displayedPercent + '%'">0%</p>
                </div>
                <div class="h-4 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="{{ $recordSingular }} saving progress" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="displayedPercent">
                    <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-600 transition-all duration-150 motion-reduce:transition-none" :style="{ width: displayedPercent + '%' }"></div>
                </div>
                <p class="text-sm text-gray-500" x-text="progress.status === 'reconnecting' ? 'Reconnecting...' : (progress.status === 'queued' ? 'Waiting to start...' : (progress.total && progress.processed === progress.total ? 'Finishing...' : (progress.status === 'processing' && progress.processed === 0 ? 'Checking {{ $recordPlural }} details...' : 'Saving your {{ $recordPlural }}...')))"></p>
                <div class="text-right"><a href="{{ route($routePrefix.'.index') }}" class="text-xs font-medium text-indigo-600 hover:underline">Continue in background</a></div>
            </div>
        </x-modals.confirmation-modal>
        <x-modals.confirmation-modal id="{{ $modalPrefix }}-discard" title="Discard Changes">
            <x-form.form :async="true" :autofocus="false" class="p-6" @submit.prevent="discardDraft()">
                <p class="text-sm text-gray-700">Discard all unsaved changes on this page?</p>
                <div class="mt-6 flex justify-end gap-2">
                    <x-form.secondary-button @click="$dispatch('close-{{ $modalPrefix }}-discard')" x-bind:disabled="discarding">Cancel</x-form.secondary-button>
                    <x-form.primary-button type="submit" loading="discarding" class="bg-red-600 hover:bg-red-700 focus:bg-red-700 focus:ring-red-500">Discard Changes</x-form.primary-button>
                </div>
            </x-form.form>
        </x-modals.confirmation-modal>
        <x-modals.receipt-preview-modal title="Document Preview" imageAlt="Uploaded document" />
        @if($showContacts)
        <template x-teleport="body">
            <div x-show="contactPopover.key !== null" x-cloak @mouseenter="clearTimeout(contactHideTimer)" @mouseleave="queueCloseContactList()" @click.outside="closeContactList()" @keydown.escape.window="closeContactList()" @resize.window="closeContactList()" class="fixed z-50 max-h-64 overflow-y-auto rounded-lg border border-gray-200 bg-white p-2 shadow-lg" :style="{ left: contactPopover.left + 'px', top: contactPopover.top + 'px', width: contactPopover.width + 'px' }" role="region" aria-label="All emergency contacts">
                <p class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Emergency Contacts · Click to edit</p>
                <template x-for="(contact, contactIndex) in (contactPopoverRow()?.contacts || [])" :key="contactIndex">
                    <button type="button" @click="editContact(contactIndex)" class="block w-full rounded-md px-3 py-2 text-left hover:bg-indigo-50 focus-visible:ring-2 focus-visible:ring-indigo-500" :class="contactPopoverRow()?.activeContactIndex === contactIndex ? 'bg-indigo-50' : ''">
                        <span class="block break-words text-sm font-semibold text-slate-900" x-text="contact.name || (contact.phone || contact.relationship ? 'Unnamed contact' : 'New contact')"></span>
                        <span x-show="contact.relationship" class="block break-words text-xs text-gray-500" x-text="contact.relationship"></span>
                        <span x-show="contact.phone" class="block break-words text-sm text-slate-700" x-text="contact.phone"></span>
                    </button>
                </template>
            </div>
        </template>
        @endif
    </div>
