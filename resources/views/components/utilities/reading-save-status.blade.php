<span class="inline-flex h-5 w-5 shrink-0 items-center justify-center"
    x-bind:class="isDirty(row) || row.needsSave ? 'text-amber-600' : 'text-emerald-600'"
    x-bind:style="{ visibility: row.current !== '' && row.current !== null ? 'visible' : 'hidden' }"
    x-bind:title="isDirty(row) || row.needsSave ? 'Not saved yet' : 'Saved'"
    x-bind:aria-label="isDirty(row) || row.needsSave ? 'Not saved yet' : 'Saved'" role="img">
    <svg x-show="isDirty(row) || row.needsSave" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <svg x-show="!(isDirty(row) || row.needsSave)" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
</span>
