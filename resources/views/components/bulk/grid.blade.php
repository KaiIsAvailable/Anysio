                    <table data-bulk-grid class="bulk-sheet w-max min-w-full border-collapse text-left text-xs">
                        <thead class="sticky top-0 z-20 bg-slate-100 text-slate-600">
                            <tr>
                                <th rowspan="2" class="sticky left-0 z-30 bg-slate-100">#</th>
                                <th rowspan="2">Change</th>
                                @foreach($groups as $group)
                                    <th colspan="{{ count($group['fields']) }}" class="text-center">{{ $group['title'] }}</th>
                                @endforeach
                                @if($showContacts)<th colspan="3" class="text-center">Emergency Contacts</th>@endif
                                <th rowspan="2">Action</th>
                            </tr>
                            <tr>
                                @foreach($groups as $group)
                                    @foreach($group['fields'] as $field => $label)
                                        <th class="font-medium" title="{{ $field === 'email' ? 'Leave blank to generate a login email when saving.' : '' }}">
                                            {{ $label }}
                                            @isset($requiredColumns[$field])
                                                <span class="text-red-600" title="{{ $requiredColumns[$field] }}" aria-label="{{ $requiredColumns[$field] }}">*</span>
                                            @endisset
                                        </th>
                                    @endforeach
                                @endforeach
                                @if($showContacts)
                                <th class="font-medium">Contact Name <span class="text-red-600" title="At least one emergency contact is required. Each entered contact needs a name." aria-label="Required for each entered emergency contact">*</span></th>
                                <th class="font-medium">Relationship <span class="text-red-600" title="Required for each entered emergency contact." aria-label="Required for each entered emergency contact">*</span></th>
                                <th class="font-medium">Contact Phone <span class="text-red-600" title="Required for each entered emergency contact. Must differ from the tenant phone." aria-label="Required for each entered emergency contact">*</span></th>
                                @endif
                            </tr>
                        </thead>
                        <template x-for="(row, index) in {{ isset($sheetEntity) ? "visibleRows('".$sheetEntity."')" : 'rows' }}" :key="row.key">
                            <tbody :data-bulk-row-body="row.key" :class="[row.removed ? 'bg-red-50' : (row.isNew ? 'bg-indigo-50' : 'bg-white'), isEntryRow(row) ? 'bulk-entry-row sticky bottom-0 z-10' : '']" @focusin="revealNewRow(row)">
                                <tr data-bulk-grid-row @input="if ($event.target.type !== 'hidden') { clearCellErrors(row, $event.target.closest('[data-error-field]')?.dataset.errorField); markChanged(row, $event.target.dataset.field); }">
                                    <td class="sticky left-0 z-10 bg-slate-50 text-center text-gray-400" style="min-width: 36px;">
                                        <span x-text="{{ $records->firstItem() ?? 1 }} + index"></span>
                                        <button type="button" x-cloak x-show="errorSides[row.key]?.left" @click="focusRowError(row, errorSides[row.key].left)" class="mx-auto block rounded px-1 text-red-600 focus-visible:ring-2 focus-visible:ring-red-500" aria-label="Go to error on the left" title="Error on the left">&#x25CF;&#x2039;</button>
                                    </td>
                                    <td style="min-width: 68px;"><span class="px-2 text-xs" :class="row.removed ? 'text-red-600' : 'text-indigo-600'" x-text="row.removed ? 'Remove' : (row.isNew ? (hasRowData(row) ? 'New' : '') : (row.changed ? 'Edited' : ''))"></span></td>
                                    @foreach($groups as $group)
                                        @foreach($group['fields'] as $field => $label)
                                            <td data-error-field="{{ $field }}" :aria-invalid="fieldHasErrors(row, '{{ $field }}') ? 'true' : null" style="min-width: {{ in_array($field, ['email', 'companyEmail', 'companyAddress']) ? '200' : '140' }}px;">
                                                @if(isset($selectColumns[$field]))
                                                    @php
                                                        $selectDisabled = "isCellDisabled(row, '".$field."')";
                                                        $optionsExpression = isset($selectColumns[$field]['parentEntity'])
                                                            ? "options = parentOptions(row, '".$selectColumns[$field]['parentEntity']."')" : '';
                                                        if (!($hierarchy ?? false) && \Illuminate\Support\Facades\Gate::denies('super-admin') && \Illuminate\Support\Facades\Gate::denies($permissionModule.'.edit')) {
                                                            $selectDisabled .= ' || !row.isNew';
                                                        }
                                                    @endphp
                                                    <x-form.input-select :options="$selectColumns[$field]['options'] ?? []" :placeholder="$selectColumns[$field]['placeholder'] ?? ''" x-model="row.{{ $field }}Selection" :disabledWhen="$selectDisabled" aria-label="{{ $label }}" x-effect="{{ $optionsExpression }}" @change="if ($event.detail && 'value' in $event.detail && row.{{ $field }} !== ($event.detail.value ?? '')) { row.{{ $field }} = $event.detail.value ?? ''; markChanged(row, '{{ $field }}'); }" />
                                                @elseif($field === 'waterOpeningDate')
                                                    <x-form.date-input id="bulk-water-opening-date" name="bulk_water_opening_date" x-bind:id="'water-opening-' + row.key" x-model="row.waterOpeningDate" x-bind:disabled="isCellDisabled(row, 'waterOpeningDate')" :disable-mobile="true" :max-date="today()->toDateString()" aria-label="Opening Reading Date" data-field="waterOpeningDate" />
                                                @elseif($field === 'type')
                                                    <x-form.input-select :options="['individual' => 'Individual', 'corporate' => 'Corporate']" x-model="row.typeSelection" :disabledWhen="'!row.isNew || row.removed'" @change="if ($event.detail?.value && row.type !== $event.detail.value) { row.type = $event.detail.value; markChanged(row, 'type'); }" />
                                                @elseif(in_array($field, ['icPhoto', 'companyLogo', 'ssmCertificate']))
                                                    @php
                                                        $uploadDisabled = "isCellDisabled(row, '".$field."')";
                                                        if (!($hierarchy ?? false) && \Illuminate\Support\Facades\Gate::denies('super-admin') && \Illuminate\Support\Facades\Gate::denies($permissionModule.'.edit')) {
                                                            $uploadDisabled .= ' || !row.isNew';
                                                        }
                                                    @endphp
                                                    <div class="flex items-center" style="min-width: 240px;">
                                                        <x-form.file-input x-bind:name="'uploads[' + row.key + '][{{ $field }}]'" data-upload-field="{{ $field }}" x-bind:data-bulk-row="row.key" accept="{{ $field === 'companyLogo' ? 'image/jpeg,image/png,image/webp' : 'image/jpeg,image/png,image/gif,application/pdf' }}" aria-label="{{ $label }}" title="Optional. Maximum 2 MB. Leave empty to keep the current file." x-bind:disabled="{{ $uploadDisabled }}" x-bind:required="!!row.uploadNames.{{ $field }} && !row.selectedUploads.{{ $field }}" @change="setUpload(row, '{{ $field }}', $event.target)" class="h-[38px] !rounded-md !p-0 file:!rounded-none file:!px-2 file:!py-1.5" />
                                                        <span x-show="row.uploadNames.{{ $field }} && !row.selectedUploads.{{ $field }}" class="px-1 text-xs text-amber-700" x-bind:title="row.uploadNames.{{ $field }}">Reselect</span>
                                                        <span x-show="row.existingFiles.{{ $field }} && !row.uploadNames.{{ $field }}" class="max-w-24 truncate px-1 text-xs text-gray-500" x-bind:title="row.existingFiles.{{ $field }}?.name">Existing file</span>
                                                        <button type="button" x-show="uploadPreview(row, '{{ $field }}')" @click="previewUpload(row, '{{ $field }}')" aria-label="Preview {{ $label }}" title="Preview {{ $label }}" class="flex h-[38px] w-8 shrink-0 items-center justify-center text-indigo-600 hover:bg-indigo-50 focus-visible:ring-2 focus-visible:ring-indigo-500">
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.478 0-8.268-2.943-9.542-7z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            </svg>
                                                        </button>
                                                        <button type="button" x-show="row.uploadNames.{{ $field }}" @click="clearUpload(row, '{{ $field }}', $event.currentTarget.parentElement.querySelector('input[type=file]'))" x-bind:disabled="{{ $uploadDisabled }}" aria-label="Cancel selected upload" class="shrink-0 px-2 text-lg text-red-600">&times;</button>
                                                    </div>
                                                @elseif($field === 'gender')
                                                    @php
                                                        $genderDisabled = "isCellDisabled(row, 'gender')";
                                                        if (\Illuminate\Support\Facades\Gate::denies('super-admin') && \Illuminate\Support\Facades\Gate::denies($permissionModule.'.edit')) {
                                                            $genderDisabled .= ' || !row.isNew';
                                                        }
                                                    @endphp
                                                    <x-form.input-select :options="['Male' => 'Male', 'Female' => 'Female']" placeholder="" x-bind:required="isFieldRequired(row, 'gender')" x-model="row.genderSelection" :disabledWhen="$genderDisabled" @change="if ($event.detail?.value && row.gender !== $event.detail.value) { row.gender = $event.detail.value; markChanged(row, 'gender'); }" />
                                                @else
                                                    @php
                                                        $cellDisabled = "isCellDisabled(row, '".$field."')";
                                                        if (!($hierarchy ?? false) && \Illuminate\Support\Facades\Gate::denies('super-admin') && \Illuminate\Support\Facades\Gate::denies($permissionModule.'.edit')) {
                                                            $cellDisabled .= ' || !row.isNew';
                                                        }
                                                        $cellReadonly = $field === 'nationality' ? "row.type === 'individual' && !!row.ic" : 'false';
                                                        $cellPlaceholder = $field === 'accountNumber' ? "row.accountLastFour ? '****' + row.accountLastFour : ''" : ($field === 'floor' ? "'e.g. G or 2'" : "''");
                                                    @endphp
                                                    <x-form.text-input x-model="row.{{ $field }}" data-field="{{ $field }}" type="{{ $field === 'waterOpeningReading' ? 'number' : (in_array($field, ['email', 'companyEmail']) ? 'email' : 'text') }}" inputmode="{{ $field === 'waterOpeningReading' ? 'decimal' : (in_array($field, ['phone', 'ic', 'companyPhone', 'accountNumber']) ? 'numeric' : 'text') }}" step="{{ $field === 'waterOpeningReading' ? '0.001' : 'any' }}" min="{{ $field === 'waterOpeningReading' ? '0' : '' }}" maxlength="{{ $fieldLengths[$field] ?? 255 }}" aria-label="{{ $label }}" x-bind:disabled="{{ $cellDisabled }}" x-bind:readonly="{{ $cellReadonly }}" x-bind:required="isFieldRequired(row, '{{ $field }}')" x-bind:placeholder="{{ $cellPlaceholder }}" autocomplete="off" />
                                                @endif
                                                @if($field === 'accountNumber')
                                                    <span x-show="row.accountNumberPending && !row.accountNumber" class="px-2 text-xs text-amber-700">Re-enter unsaved number</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    @endforeach
                                    @if($showContacts)
                                    @foreach(['name' => 'Emergency contact name', 'relationship' => 'Emergency contact relationship', 'phone' => 'Emergency contact phone'] as $contactField => $contactLabel)
                                        <td data-error-field="contacts.{{ $contactField }}" :aria-invalid="fieldHasErrors(row, 'contacts.{{ $contactField }}') ? 'true' : null" style="min-width: 170px;">
                                            <div class="flex items-center">
                                                @if($contactField === 'name')
                                                    <div class="flex shrink-0 items-center border-r border-gray-200 bg-slate-50" aria-label="Choose emergency contact">
                                                        <button type="button" @click="selectContact(row, row.activeContactIndex - 1)" :disabled="row.activeContactIndex === 0" aria-label="Previous contact" class="h-[38px] w-6 text-indigo-600 hover:bg-indigo-50 disabled:text-gray-300">&lsaquo;</button>
                                                        <button type="button" class="h-[38px] min-w-10 text-center text-[11px] text-indigo-600 hover:bg-indigo-50 focus-visible:ring-2 focus-visible:ring-indigo-500" aria-label="Show all emergency contacts" :aria-expanded="contactPopover.key === row.key" @mouseenter="showContactList(row, $el)" @mouseleave="queueCloseContactList()" @focus="showContactList(row, $el)" @click="showContactList(row, $el)" x-text="(row.activeContactIndex + 1) + '/' + row.contacts.length"></button>
                                                        <button type="button" @click="selectContact(row, row.activeContactIndex + 1)" :disabled="row.activeContactIndex === row.contacts.length - 1" aria-label="Next contact" title="Next contact or empty contact" class="h-[38px] w-6 text-indigo-600 hover:bg-indigo-50 disabled:text-gray-300">&rsaquo;</button>
                                                    </div>
                                                @endif
                                                <x-form.text-input x-model="row.contacts[row.activeContactIndex].{{ $contactField }}" data-field="{{ $contactField === 'phone' ? 'contactPhone' : '' }}" data-contact-field="{{ $contactField }}" x-bind:required="isContactFieldRequired(row, '{{ $contactField }}')" inputmode="{{ $contactField === 'phone' ? 'numeric' : 'text' }}" maxlength="{{ $contactField === 'phone' ? 20 : 255 }}" x-bind:data-bulk-row="row.key" aria-label="{{ $contactLabel }}" x-bind:disabled="row.removed{{ \Illuminate\Support\Facades\Gate::denies('super-admin') && \Illuminate\Support\Facades\Gate::denies($permissionModule.'.edit') ? ' || !row.isNew' : '' }}" />
                                                @if($contactField === 'phone')
                                                    <button type="button" @click="removeContact(row, row.activeContactIndex)" x-bind:disabled="row.removed{{ \Illuminate\Support\Facades\Gate::denies('super-admin') && \Illuminate\Support\Facades\Gate::denies($permissionModule.'.edit') ? ' || !row.isNew' : '' }}" aria-label="Remove emergency contact" title="Remove contact" class="flex h-[38px] w-8 shrink-0 items-center justify-center text-lg text-red-600 hover:bg-red-50 focus-visible:ring-2 focus-visible:ring-red-500 disabled:cursor-not-allowed disabled:opacity-40">&times;</button>
                                                @endif
                                            </div>
                                        </td>
                                    @endforeach
                                    @endif
                                    <td class="sticky right-0 z-10 bg-slate-50" style="min-width: 75px;">
                                        <button type="button" x-cloak x-show="errorSides[row.key]?.right" @click="focusRowError(row, errorSides[row.key].right)" class="mx-auto block rounded px-1 text-red-600 focus-visible:ring-2 focus-visible:ring-red-500" aria-label="Go to error on the right" title="Error on the right">&#x203A;&#x25CF;</button>
                                        @if($hierarchy ?? false)
                                            <button type="button" @click="remove(row)" :disabled="(!row.isNew && !canRemove(row)) || ancestorRemoved(row)" class="px-3 py-2 text-red-600 hover:underline disabled:cursor-not-allowed disabled:opacity-40" x-text="row.removed ? 'Undo' : 'Remove'"></button>
                                        @else
                                        @canany(['super-admin', $permissionModule.'.delete'])
                                            <button type="button" @click="remove(row)" class="px-3 py-2 text-red-600 hover:underline" x-text="row.removed ? 'Undo' : 'Remove'"></button>
                                        @else
                                            <button type="button" x-show="row.isNew" @click="remove(row)" class="px-3 py-2 text-red-600 hover:underline">Remove</button>
                                        @endcanany
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </template>
                    </table>
