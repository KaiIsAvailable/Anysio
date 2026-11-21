<x-app-layout>

    @php

        $currentMonthLabel = \Carbon\Carbon::parse($month.'-01')->format('m/Y');

        $previousMonthLabel = \Carbon\Carbon::parse($month.'-01')->subMonthNoOverflow()->format('m/Y');

        $earlierMonthLabel = \Carbon\Carbon::parse($month.'-01')->subMonthsNoOverflow(2)->format('m/Y');

        $previousYearsLabel = substr($earlierMonthLabel, 3) === substr($previousMonthLabel, 3)
            ? substr($previousMonthLabel, 3)
            : substr($earlierMonthLabel, 3).'/'.substr($previousMonthLabel, 3);

        $oldRows = json_decode(old('readings', 'null'), true);

        $oldSelection = json_decode(old('selected_services', '[]'), true);

        $readingConfig = ['rows' => $readingRows, 'hierarchy' => $readingHierarchy, 'readingDate' => old('reading_date', $readingDate), 'oldRows' => $oldRows, 'selectedIds' => is_array($oldSelection) ? array_values($oldSelection) : [], 'tariffPlan' => $tariffPlan ?? null, 'building' => old('building', request('building', '')), 'draftKey' => 'utility-readings:'.auth()->id().':'.$manager->id.':'.$month, 'draftScope' => auth()->id().':'.$manager->id.':'.$month, 'savedDraft' => session('utility_reading_draft_saved')];

        $propertyOptions = ['' => 'All properties'] + collect($readingHierarchy)->where('level', 0)->mapWithKeys(fn ($node) => [str_replace('property-', '', $node['key']) => $node['label']])->all();

        $needsPreviousDate = collect($readingRows)->contains(fn ($row) => !$row['locked'] && !$row['previousDate']);

    @endphp

    <div class="py-5 sm:py-10 bg-gray-50 min-h-screen" x-data="utilityReadings(@js($readingConfig))"

        @if($invoicePreview ?? null) x-init="openInvoicePreview()" @endif

        @input="persistDraft()" @change="persistDraft()"
        @pagehide.window="persistDraft()"
        @pageshow.window="if ($event.persisted) window.location.reload()"
        @beforeunload.window="if (hasChanges && !submitting && !persistDraft()) { $event.preventDefault(); $event.returnValue = ''; }">

        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 space-y-5">

            <div>

                <a href="{{ route('admin.utilities.index', ['management_user_id' => $manager->id, 'type' => 'water']) }}" class="inline-flex items-center text-indigo-600 hover:text-indigo-900 text-sm font-medium transition-colors">

                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>Back to Utilities

                </a>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">

                    <h1 class="text-3xl font-bold text-slate-900">Water Reading Entry</h1>

                    @canany(['super-admin', 'utilities.tariff tab'])

                        <a href="{{ route('admin.settings.index', ['tab' => 'utility', 'management_user_id' => $manager->id]) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900 hover:underline">Water Tariffs</a>

                    @endcanany

                </div>

                <p class="mt-2 text-sm text-gray-500">{{ $manager->name }} &middot; Choose your property, follow the meter order, and enter current readings. Save regularly as you go.</p>

            </div>



            <div class="rounded-xl border border-gray-200 bg-white p-4 space-y-4">

                <div><x-form.input-label value="Property" class="mb-2" /><x-form.input-select :options="$propertyOptions" :value="old('building', request('building', ''))" x-effect="selectedValues = [selectedProperty || '']" @change="if ($event.detail) selectProperty($event.detail.value || '')" /></div>

                <div class="flex items-center justify-between gap-3 text-sm text-gray-600">

                    <span>All meters: <strong class="text-slate-900" x-text="progress.entered"></strong> of <strong x-text="progress.total"></strong> entered</span>

                    <span x-text="progress.percent + '%'"></span>

                </div>

                <div class="h-2 overflow-hidden rounded-full bg-gray-100" role="progressbar" x-bind:aria-valuenow="progress.percent" aria-valuemin="0" aria-valuemax="100" aria-label="Meter readings entered"><div class="h-full rounded-full bg-indigo-600 transition-all" x-bind:style="{ width: progress.percent + '%' }"></div></div>

                <div x-show="selectedProperty" x-cloak class="space-y-3 border-t border-gray-100 pt-4">

                    <div class="flex items-center justify-between gap-3 text-sm text-gray-600">

                        <span>Selected property: <strong class="text-slate-900" x-text="propertyProgress.entered"></strong> of <strong x-text="propertyProgress.total"></strong> entered</span>

                        <span x-text="propertyProgress.percent + '%'"></span>

                    </div>

                    <div class="h-2 overflow-hidden rounded-full bg-gray-100" role="progressbar" x-bind:aria-valuenow="propertyProgress.percent" aria-valuemin="0" aria-valuemax="100" aria-label="Selected property readings entered"><div class="h-full rounded-full bg-indigo-600 transition-all" x-bind:style="{ width: propertyProgress.percent + '%' }"></div></div>

                </div>

                <p class="text-xs text-gray-500">Entries are kept in this browser tab until saved. Use Save Readings to record them. Counts include pending entries.</p>

            </div>



            <div class="grid grid-cols-2 gap-3 rounded-xl border border-gray-200 bg-white p-4">

                <x-form.form method="GET" action="{{ route('admin.utilities.readings') }}" :autofocus="false" class="min-w-0">

                    <x-form.text-input type="hidden" name="building" x-bind:value="selectedProperty" />

                    <x-form.text-input type="hidden" name="management_user_id" :value="$manager->id" />

                    <x-form.input-label for="reading-month" value="Billing month" class="mb-2" :info="__('The billing cycle is separate from the actual reading date. A reading may be collected in a different month.')" />

                    <x-form.date-input id="reading-month" name="month" mode="month" :value="$month" :data-current-month="$month" required

                        @change="if ($el.form.elements.month.value && $el.form.elements.month.value !== $el.dataset.currentMonth && !loading) $el.form.requestSubmit()" />

                </x-form.form>

                <div class="min-w-0">

                    <x-form.input-label for="bulk-reading-date" value="Reading date" :required="true" class="mb-2" />

                    <x-form.date-input id="bulk-reading-date" name="reading_date" :disable-mobile="true" x-model="readingDate" :value="old('reading_date', $readingDate)" :max-date="today()->toDateString()" required />

                </div>

            </div>



            @canany(['super-admin', 'utilities.generate invoices'])

                <x-form.form id="water-invoice-preview-form" method="GET" action="{{ route('admin.utilities.readings') }}" :autofocus="false">

                    <x-form.text-input type="hidden" name="management_user_id" :value="$manager->id" />

                    <x-form.text-input type="hidden" name="month" :value="$month" />

                <x-form.text-input type="hidden" name="building" x-bind:value="selectedProperty" />

                    <x-form.text-input type="hidden" name="preview_invoices" value="1" />

                </x-form.form>

            @endcanany

            <x-form.form id="water-readings-form" method="POST" action="{{ route('admin.utilities.readings.save') }}" :autofocus="false" @submit.capture="submitting = true" class="space-y-4">

                <x-form.text-input type="hidden" name="management_user_id" :value="$manager->id" />

                <x-form.text-input type="hidden" name="month" :value="$month" />

                <x-form.text-input type="hidden" name="building" x-bind:value="selectedProperty" />

                <x-form.text-input type="hidden" name="readings" x-bind:value="payload" />
                <x-form.text-input type="hidden" name="reading_date" :value="old('reading_date', $readingDate)" x-bind:value="readingDate" />



                @if($needsPreviousDate)

                    <details class="rounded-xl border border-gray-200 bg-white p-4">

                        <summary class="cursor-pointer text-sm font-semibold text-gray-700">Missing previous reading dates</summary>

                        <div class="mt-4 max-w-sm">

                            <x-form.input-label for="bulk-previous-date" value="Previous date (where not set)" class="mb-2" :info="__('Enter the actual date of the previous readings where not set. Existing measured dates remain unchanged.')" />

                            <x-form.date-input id="bulk-previous-date" name="" x-model="previousDate" @input="applyPreviousDate()" :max-date="today()->toDateString()" />

                        </div>

                    </details>

                @endif



                <div class="flex justify-end">

                    @canany(['super-admin', 'utilities.manage meters', 'utilities.reading history', 'utilities.generate invoices'])
                    <x-form.secondary-button @click="manageMeters = !manageMeters" x-bind:aria-expanded="manageMeters"><span x-text="manageMeters ? 'Finish managing' : 'Manage meters'"></span></x-form.secondary-button>
                    @endcanany

                </div>



                <div class="rounded-xl border border-gray-200 bg-white md:border-gray-100 md:shadow-lg">

                    <div class="md:overflow-x-auto" role="region" aria-label="Water meter readings">
                        <div class="min-w-0">

                        <div class="sticky top-0 z-20 grid grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] items-end border-b border-gray-200 bg-gray-50 text-sm font-semibold text-gray-600 md:hidden">
                            <span class="flex items-center gap-2 px-2 py-3">
                                @canany(['super-admin', 'utilities.manage meters'])
                                    <x-form.checkbox x-show="manageMeters" aria-label="Select all water meters" x-bind:checked="allSelected" x-bind:indeterminate="selectedIds.length > 0 && !allSelected" @change="toggleAllMeters($event.target.checked)" />
                                @endcanany
                                <span>{{ $previousYearsLabel }}<span class="mt-1 block font-normal">Previous</span></span>
                            </span>
                            <span class="px-2 py-3 text-indigo-600">{{ $currentMonthLabel }}<span class="mt-1 block font-normal">Current (m³)</span></span>
                        </div>
                        <table class="w-full md:min-w-[760px] text-left md:divide-y md:divide-gray-200">

                            <thead class="hidden rounded-t-xl bg-gray-50 md:table-header-group"><tr class="grid grid-cols-3 md:table-row"><th class="col-span-3 px-4 py-3 md:w-12 md:py-4" x-show="manageMeters">

                                @canany(['super-admin', 'utilities.manage meters'])
<x-form.checkbox aria-label="Select all water meters" x-bind:checked="allSelected" x-bind:indeterminate="selectedIds.length > 0 && !allSelected" @change="toggleAllMeters($event.target.checked)" />
                                            @endcanany

                            </th><th class="hidden px-6 py-4 text-xs font-semibold uppercase text-gray-500 md:table-cell">Property / Unit / Room</th><th class="min-w-0 px-2 py-3 text-xs font-semibold text-gray-500 md:px-6 md:py-4"><span class="mb-1 block text-sm text-slate-700">{{ $earlierMonthLabel }}</span>1st previous reading</th><th class="min-w-0 px-2 py-3 text-xs font-semibold text-gray-500 md:px-6 md:py-4"><span class="mb-1 block text-sm text-slate-700">{{ $previousMonthLabel }}</span>2nd previous reading</th><th class="min-w-0 px-2 py-3 text-xs font-semibold text-gray-500 md:px-6 md:py-4"><span class="mb-1 block text-sm text-slate-700">{{ $currentMonthLabel }}</span>Current reading</th><th x-show="manageMeters" class="col-span-3 px-6 py-4 text-xs font-semibold uppercase text-gray-600">Action</th></tr></thead>

                                <template x-for="node in visibleHierarchy" :key="node.key">

                                    <tbody class="block border-b border-gray-200 last:border-b-0 md:mb-0 md:table-row-group md:rounded-none md:border-0" x-data="{ row: readingRow(node), detailsOpen: false }" x-id="['water-meter-details']" :class="node.level === 0 ? 'bg-indigo-50' : (node.level === 1 ? 'bg-gray-50' : 'bg-white')">

                                    <tr class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] items-center md:table-row">

                                        <td class="hidden px-4 py-2 md:table-cell md:py-4" x-show="manageMeters">

                                            @canany(['super-admin', 'utilities.manage meters'])
<template x-if="row"><x-form.checkbox x-model="selectedIds" x-bind:value="row.id" x-bind:aria-label="'Select ' + row.label" /></template>
                                            @endcanany

                                        </td>

                                        <td class="col-span-2 min-w-0 pb-0 pt-2 pr-2 max-md:!pl-2 md:table-cell md:py-4 md:pr-4" x-bind:colspan="row ? 1 : (manageMeters ? 5 : 4)" :style="{ paddingLeft: (16 + node.level * 12) + 'px' }">

                                            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                                @canany(['super-admin', 'utilities.manage meters'])
                                                    <template x-if="manageMeters && row"><x-form.checkbox class="self-center md:hidden" x-model="selectedIds" x-bind:value="row.id" x-bind:aria-label="'Select ' + row.label" /></template>
                                                @endcanany
                                                <span class="hidden text-sm text-slate-900 md:inline" :class="node.level === 0 ? 'font-bold' : 'font-semibold'" x-text="node.label"></span>
                                                <button type="button" class="min-w-0 break-words text-left text-sm font-semibold text-indigo-600 underline decoration-dotted underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 md:hidden"
                                                    x-show="row" @click="detailsOpen = !detailsOpen" :aria-expanded="detailsOpen" :aria-controls="$id('water-meter-details')" :aria-label="'Reading details for ' + node.label" x-text="node.label"></button>
                                                <span x-show="!row" class="break-words text-sm font-semibold text-slate-900 md:hidden" x-text="node.label"></span>

                                                <span x-show="manageMeters || !row" class="text-xs" :class="[!row ? 'text-gray-400' : (row.setupStatus === 'unconfirmed' ? 'text-amber-700' : 'text-green-700'), row ? 'hidden md:inline' : '']" x-text="!row ? '- No meter configured' : (row.setupStatus === 'unconfirmed' ? '- Unconfirmed' : '- Active')"></span>

                                            </div>


                                            <template x-if="row?.meter"><p class="mt-1 hidden text-xs text-gray-500 md:block" x-text="'Meter: ' + row.meter"></p></template>

                                        </td>

                                        <td class="hidden min-w-0 px-1 py-2 align-top md:table-cell md:px-6 md:py-4 md:w-48" x-show="row">

                                            <template x-if="row"><div>

                                                <p class="hidden min-h-[38px] items-center text-sm tabular-nums text-slate-700 md:flex" x-text="(row.earlierPrevious ?? '') === '' ? '—' : row.earlierPrevious" x-bind:aria-label="'First previous reading for ' + row.location"></p>
                                                <p class="mt-1 hidden text-xs text-gray-500 md:block" x-show="row.earlierPreviousDate" x-text="formatReadingDate(row.earlierPreviousDate)"></p>

                                            </div></template>

                                        </td>

                                        <td class="min-w-0 px-2 pb-2 pt-1 align-top md:table-cell md:px-6 md:py-4 md:w-56" x-show="row">

                                            <template x-if="row"><div>

                                                <div class="space-y-1 whitespace-nowrap text-sm tabular-nums text-slate-700 md:hidden">
                                                    <p><span class="text-gray-500">{{ substr($earlierMonthLabel, 0, 2) }} - </span><span x-text="(row.earlierPrevious ?? '') === '' ? '—' : row.earlierPrevious"></span></p>
                                                    <p><span class="text-gray-500">{{ substr($previousMonthLabel, 0, 2) }} - </span><span x-text="(row.previous ?? '') === '' ? '—' : row.previous"></span></p>
                                                </div>
                                                <p class="hidden min-h-[38px] items-center text-sm tabular-nums text-slate-700 md:flex" x-text="(row.previous ?? '') === '' ? '—' : row.previous" x-bind:aria-label="'Second previous reading for ' + row.location"></p>
                                                <p class="mt-1 hidden text-xs text-gray-500 md:block" x-show="row.previousDate" x-text="formatReadingDate(row.previousDate)"></p>

                                                <template x-if="row.events?.length"><div class="mt-2"><x-form.input-select :options="['' => 'No meter event']" disabled-when="row.locked" x-effect="options = [{ value: '', label: 'No meter event' }, ...row.events.map(e => ({ value: e.id, label: e.type + ' - ' + e.event_date }))]; selectedValues = [row.eventId || '']" @change="if ($event.detail) row.eventId = $event.detail.value || ''" /></div></template>



                                            </div></template>

                                            <span x-show="!row" class="text-gray-400">—</span>

                                        </td>

                                        <td class="min-w-0 px-2 pb-2 pt-1 align-top md:table-cell md:px-6 md:py-4 md:w-72" x-show="row">

                                            <template x-if="row"><div>

                                                <div class="flex items-center gap-2">

                                                    <x-form.text-input type="number" inputmode="decimal" enterkeyhint="next" min="0" step="0.001" class="min-w-0 md:max-w-40" x-model="row.current" x-bind:disabled="row.locked" x-bind:aria-label="'Current reading for ' + row.location" placeholder="Enter reading" data-reading-field @keydown.enter.prevent.stop="nextField($event)" />

                                                    <x-utilities.reading-save-status />

                                                </div>

                                                <template x-if="profile(row)?.calculation === 'actual_bill'"><div class="mt-2 space-y-2"><x-form.text-input type="number" min="0" step="0.01" x-model="row.billAmount" x-bind:disabled="row.locked" placeholder="Provider bill amount (RM)" aria-label="Provider bill amount" /><x-form.text-input x-model="row.billReference" x-bind:disabled="row.locked" placeholder="Provider bill reference" aria-label="Provider bill reference" /></div></template>

                                                <div x-show="manageMeters" x-cloak>

                                                <label x-show="needsRecalculation(row)" x-cloak class="mt-2 flex gap-2 text-xs text-gray-500"><x-form.checkbox x-model="row.reprice" @change="if (row.reprice) row.rate = ''" />Recalculate using the assigned effective tariff</label>

                                                </div>

                                                <div class="mt-1 hidden items-center gap-2 md:flex">
                                                    <p class="text-xs text-gray-500" x-show="calculation(row).usage !== null"><span x-text="calculation(row).usage"></span> m³ used <span x-show="calculation(row).amount !== null" x-text="'· ' + money(calculation(row).amount ?? 0)"></span></p>
                                                    <x-form.input-label x-show="manageMeters" class="shrink-0 [&_[role=tooltip]]:left-auto [&_[role=tooltip]]:right-0 [&_[role=tooltip]]:translate-x-0">
                                                        <x-slot:info>
                                                            <span class="block" x-text="profile(row)?.label || 'No tariff assigned'"></span>
                                                            <span class="mt-1 block" x-text="profile(row)?.kind === 'custom' ? 'Landlord-configured charges' : 'Official reference: ' + (profile(row)?.verification_status || 'unverified')"></span>
                                                        </x-slot:info>
                                                    </x-form.input-label>
                                                </div>

                                                <p class="mt-1 hidden flex-wrap items-center gap-1 text-xs font-semibold text-indigo-600 md:flex" x-show="row.invoiced">
                                                    <span x-text="row.invoiceNo ? 'Invoice generated:' : 'Invoice generated'"></span>
                                                    @canany(['super-admin', 'invoice.view invoice'])
                                                        <button type="button" x-show="row.invoiceDocumentUrl" @click="window.openLeaseInvoiceDocument(row.invoiceDocumentUrl, 'Invoice: ' + row.invoiceNo)"
                                                            class="font-semibold text-indigo-600 hover:text-indigo-900 hover:underline" x-text="row.invoiceNo"></button>
                                                    @endcanany
                                                    <span x-show="!row.invoiceDocumentUrl" x-text="row.invoiceNo"></span>
                                                </p>

                                            </div></template>

                                            <span x-show="!row" class="text-gray-400">—</span>

                                        </td>

                                        <td class="col-span-2 px-4 py-3 md:table-cell md:px-6 md:py-4 md:w-36" x-show="manageMeters && row">

                                            <template x-if="row"><div class="flex flex-wrap items-center gap-2">

                                                @canany(['super-admin', 'utilities.reading history'])
                                                <a x-show="row.chargeUrl" x-bind:href="row.chargeUrl" class="text-xs text-indigo-600 hover:underline">Charge details</a>
                                                @endcanany

                                                @canany(['super-admin', 'utilities.manage meters'])
                                                <a x-bind:href="row.editUrl" class="inline-flex shrink-0 p-2 text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors" title="Edit water meter setup" x-bind:aria-label="'Edit ' + row.label">

                                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>

                                                </a>

                                                <button type="button" class="inline-flex shrink-0 p-2 text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 rounded-lg transition-colors" title="Delete water meter setup" x-bind:aria-label="'Delete ' + row.label"

                                                    @click="$dispatch('open-reading-meter-delete', { actionUrl: row.deleteUrl, label: row.label })">

                                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>

                                                </button>
                                                @endcanany

                                            </div></template>

                                        </td>

                                    </tr>

                                    <tr x-show="row && detailsOpen" x-cloak class="block md:hidden">
                                        <td class="block border-t border-gray-200 px-3 py-3 text-xs text-gray-600" :id="$id('water-meter-details')" colspan="2">
                                            <template x-if="row"><div class="space-y-2">
                                                <p class="font-semibold text-slate-900" x-text="row.location"></p>
                                                <p x-show="row.meter" x-text="'Physical meter: ' + (row.meter || '')"></p>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <p><span class="block text-gray-500">1st previous reading date</span><span x-text="formatReadingDate(row.earlierPreviousDate)"></span></p>
                                                    <p><span class="block text-gray-500">2nd previous reading date</span><span x-text="formatReadingDate(row.previousDate)"></span></p>
                                                </div>
                                                <p x-show="calculation(row).usage !== null"><span x-text="calculation(row).usage"></span> m³ used <span x-show="calculation(row).amount !== null" x-text="'· ' + money(calculation(row).amount ?? 0)"></span></p>
                                                <p x-show="row.invoiced" class="font-semibold text-indigo-600">
                                                    <span>Invoice generated:</span>
                                                    @canany(['super-admin', 'invoice.view invoice'])
                                                        <button type="button" x-show="row.invoiceDocumentUrl" @click="window.openLeaseInvoiceDocument(row.invoiceDocumentUrl, 'Invoice: ' + row.invoiceNo)" class="underline" x-text="row.invoiceNo"></button>
                                                    @endcanany
                                                    <span x-show="!row.invoiceDocumentUrl" x-text="row.invoiceNo"></span>
                                                </p>
                                            </div></template>
                                        </td>
                                    </tr>

                                    <tr x-show="row && !row.locked && entryError(row)" x-cloak class="block md:table-row">

                                        <td x-bind:colspan="manageMeters ? 6 : 4" class="block px-4 pb-3 text-sm text-red-600 md:table-cell md:px-6" role="alert">

                                            <p class="break-words" x-text="row ? entryError(row) : ''"></p>

                                        </td>

                                    </tr>

                                    </tbody>

                                </template>

                            <tbody x-show="visibleHierarchy.length === 0"><tr><td x-bind:colspan="manageMeters ? 6 : 4" class="px-6 py-12 text-center text-sm text-gray-500">No managed properties or units found.</td></tr></tbody>

                        </table>
                        </div>

                    </div>

                </div>

                <div class="sticky bottom-0 z-20 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-lg pb-[max(0.75rem,env(safe-area-inset-bottom))]">

                        <div class="text-sm text-gray-600"><strong x-text="changedCount"></strong> pending readings <span class="ml-2 text-xs text-gray-500" x-show="draftRetained">Draft kept in this tab</span></div>

                        <div class="flex flex-wrap items-center gap-2">

                            <span class="text-sm text-gray-500" x-show="selectedIds.length" x-text="selectedIds.length + ' selected'"></span>

                            @canany(['super-admin', 'utilities.manage meters'])
                            <x-form.secondary-button x-show="manageMeters" type="button" x-bind:disabled="selectedIds.length === 0" @click="$dispatch('open-reading-meters-delete')" class="text-red-600 hover:text-red-700">Delete Selected</x-form.secondary-button>
                            @endcanany

                            @canany(['super-admin', 'utilities.generate invoices'])

                                <x-form.primary-button type="submit" form="water-invoice-preview-form" loading="loading" x-show="manageMeters" disabled-when="hasChanges" title="Save readings first. The preview uses saved readings for this billing month.">Preview Invoices</x-form.primary-button>

                            @endcanany

                            <x-form.secondary-button x-show="hasChanges || draftRetained" @click="if (window.confirm('Discard unsaved readings for this month and restore saved values?')) discardDraft()">Discard draft</x-form.secondary-button>
                            <x-form.primary-button type="submit" loading="loading">Save Readings</x-form.primary-button>

                        </div>

                </div>

                <p class="text-xs text-gray-500">Save whenever you have entered some readings; you do not need to finish every meter. Blank current readings remain available to complete later. Previously saved readings keep their recorded dates unless you edit them. Invoice generation is available under Manage meters.</p>

            </x-form.form>

            @if($invoicePreview ?? null)

                <x-utilities.invoice-preview :preview="$invoicePreview" :token="$invoicePreviewToken" :manager="$manager" />

            @endif

            @canany(['super-admin', 'utilities.manage meters'])
            <x-modals.confirmation-modal id="reading-meters-delete" title="Delete Selected Water Meter Setups">

                <x-form.form method="DELETE" action="{{ route('admin.utilities.readings.meters.delete') }}" :autofocus="false" @submit.capture="submitting = true" class="p-6">

                    <x-form.text-input type="hidden" name="management_user_id" :value="$manager->id" />

                    <x-form.text-input type="hidden" name="month" :value="$month" />

                <x-form.text-input type="hidden" name="building" x-bind:value="selectedProperty" />

                    <x-form.text-input type="hidden" name="selected_services" x-bind:value="JSON.stringify(selectedIds)" />

                    <x-form.text-input type="hidden" name="readings" x-bind:value="payload" />

                    <x-form.text-input type="hidden" name="reading_date" x-bind:value="readingDate" />

                    <p class="text-sm text-gray-700">Delete <strong x-text="selectedIds.length"></strong> selected water meter setups?</p>

                    <ul class="mt-3 max-h-48 overflow-y-auto space-y-1 text-sm text-gray-600">

                        <template x-for="meter in selectedMeters" :key="meter.id"><li x-text="meter.location + ' ? ' + meter.label"></li></template>

                    </ul>

                    <p class="mt-3 text-sm text-gray-500">Properties, units, rooms and reading history will be kept. Unsaved readings for other meters will be retained. Connected child services must also be selected or unlinked first.</p>

                    <div class="mt-6 flex justify-end gap-2">

                        <x-form.secondary-button type="button" @click="$dispatch('close-reading-meters-delete')">Cancel</x-form.secondary-button>

                        <x-form.primary-button type="submit" loading="loading" class="bg-red-600 hover:bg-red-700 focus:bg-red-700 focus:ring-red-500">Delete Selected</x-form.primary-button>

                    </div>

                </x-form.form>

            </x-modals.confirmation-modal>

            <x-modals.confirmation-modal id="reading-meter-delete" title="Delete Water Meter Setup">

                <div x-data="{ targetAction: '', serviceLabel: '' }" @open-reading-meter-delete.window="targetAction = $event.detail.actionUrl; serviceLabel = $event.detail.label">

                    <x-form.form method="DELETE" x-bind:action="targetAction" :autofocus="false" @submit.capture="submitting = true" class="p-6">

                        <x-form.text-input type="hidden" name="management_user_id" :value="$manager->id" />

                        <x-form.text-input type="hidden" name="return_to" value="readings" />

                        <x-form.text-input type="hidden" name="month" :value="$month" />

                <x-form.text-input type="hidden" name="building" x-bind:value="selectedProperty" />

                        <x-form.text-input type="hidden" name="readings" x-bind:value="payload" />

                        <x-form.text-input type="hidden" name="reading_date" x-bind:value="readingDate" />

                        <p class="text-sm text-gray-700">Delete <strong x-text="serviceLabel"></strong> from water meter setup?</p>

                        <p class="mt-2 text-sm text-gray-500">The property, unit or room and existing history will be kept. Unsaved readings for other meters will be retained. Remove child meter connections before deleting a parent meter.</p>

                        <div class="mt-6 flex justify-end gap-2">

                            <x-form.secondary-button type="button" @click="$dispatch('close-reading-meter-delete')">Cancel</x-form.secondary-button>

                            <x-form.primary-button type="submit" loading="loading" class="bg-red-600 hover:bg-red-700 focus:bg-red-700 focus:ring-red-500">Delete Meter Setup</x-form.primary-button>

                        </div>

                    </x-form.form>

                </div>

            </x-modals.confirmation-modal>
            @endcanany

        </div>

    </div>

</x-app-layout>

