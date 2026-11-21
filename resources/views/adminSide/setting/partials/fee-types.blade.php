@php
    $previousFee = $feeTypes->firstWhere('id', old('fee_type_id'));
    $oldFee = [
        'id' => $previousFee?->id,
        'name' => old('name', ''), 'category' => old('category', isset($categoryOptions['invoice']) ? 'invoice' : (array_key_first($categoryOptions) ?? '')),
        'original_price' => is_numeric(old('original_price')) ? old('original_price') / 100 : '',
        'sell_price' => is_numeric(old('sell_price')) ? old('sell_price') / 100 : '',
        'used' => $previousFee && !$previousFee->is_system && ($previousFee->invoice_items_exists || $previousFee->lease_charges_exists || $previousFee->refund_items_exists || $previousFee->maintenance_items_exists),
    ];
    $feeTypeSettings = $feeTypeSettings ?? $settings;
    $feeRows = $feeTypes->mapWithKeys(function ($feeType) use ($feeTypeSettings) {
        return [$feeType->id => [
            'name' => $feeType->name, 'category' => $feeType->category->value,
            'original_price' => $feeType->original_price === null ? '' : number_format($feeType->original_price / 100, 2, '.', ''),
            'sell_price' => $feeType->sell_price === null ? '' : number_format($feeType->sell_price / 100, 2, '.', ''),
            'is_active' => app(\App\Services\SettingService::class)->isFeeTypeActive($feeType, $feeTypeSettings),
            'used' => !$feeType->is_system && ($feeType->invoice_items_exists || $feeType->lease_charges_exists || $feeType->refund_items_exists || $feeType->maintenance_items_exists),
            'editable' => !$feeType->is_system && Gate::any(['super-admin', 'fee types.edit']),
            'can_toggle' => Gate::any(['super-admin', 'fee types.change status']) && !($feeType->is_system && (!$feeType->is_active || $feeType->settingKey() === 'service_late_payment_penalty')),
            'delete_url' => !$feeType->is_system && Gate::any(['super-admin', 'fee types.delete']) ? route('admin.fee-types.destroy', ['fee_type' => $feeType, 'account_id' => request()->attributes->get('settings_account')?->id]) : null,
            'deleted' => false,
        ]];
    })->all();
    $feeConfig = ['rows' => $feeRows, 'fee' => $oldFee, 'defaultCategory' => isset($categoryOptions['invoice']) ? 'invoice' : (array_key_first($categoryOptions) ?? ''), 'editing' => (bool) session('fee_types_editing', false),
        'saveUrl' => route('admin.fee-types.batch', ['account_id' => request()->attributes->get('settings_account')?->id]), 'saveError' => __('Unable to save fee types. Your edits are retained. Please try again.'),
        'deleteError' => __('Unable to delete this fee type. Please try again.')];
@endphp
<section class="bg-white shadow-sm rounded-lg border border-gray-200 p-4 sm:p-6 space-y-5"
    x-data="feeTypeSettings(@js($feeConfig))"
    @pageshow.window="resetLoading()"
    x-init="$watch('editingFees', enabled => { if (!enabled) resetFees(); }); @if(old('fee_type_action') === 'save') $nextTick(() => $dispatch('open-fee-type-editor')); @endif">
    <header class="sticky top-0 z-30 flex flex-wrap items-center justify-between gap-3 bg-white py-2">
        <div>
            <h2 class="text-lg font-medium text-gray-900">{{ __('Fee Types') }}</h2>
            <p class="mt-1 text-sm text-gray-500">{{ __('Adjust system fee availability and manage custom fees for this account.') }}</p>
        </div>
        <div class="flex items-center gap-3">
            @canany(['super-admin', 'fee types.edit', 'fee types.change status', 'fee types.delete'])
            <fieldset x-bind:disabled="savingFees || deletingFee">
                <x-form.section-edit-button state="editingFees" />
            </fieldset>
            @endcanany
            @canany(['super-admin', 'fee types.create'])
            @if($categoryOptions)
            <x-form.primary-button type="button" x-bind:disabled="savingFees || deletingFee" @click="openEditor()">{{ __('Add Fee Type') }}</x-form.primary-button>
            @endif
            @endcanany
        </div>
    </header>

    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 md:items-end">
    <div>
        <x-form.input-label for="fee-category-view" :value="__('Category')" />
        <x-form.input-select id="fee-category-view" :options="['all' => __('All Categories')] + $categoryOptions" :value="request('category', 'all')"
            @change="if ($event.detail?.value && $event.detail.value !== @js(request('category', 'all'))) {
                const target = new URL(@js(route('admin.fee-types.index')));
                target.searchParams.set('category', $event.detail.value);
                if (@js(request()->attributes->get('settings_account')?->id)) target.searchParams.set('account_id', @js(request()->attributes->get('settings_account')?->id));
                if (@js(request('search', ''))) target.searchParams.set('search', @js(request('search', '')));
                window.location.assign(target.href);
            }" />
    </div>
    <x-form.form method="GET" action="{{ route('admin.fee-types.index') }}" :autofocus="false">
        <input type="hidden" name="category" value="{{ request('category', 'all') }}">
        <x-table.search :compact="true" placeholder="{{ __('Search fee name or category...') }}" />
    </x-form.form>
    </div>

    <x-form.form method="PUT" action="{{ route('admin.fee-types.batch') }}" :async="true" :autofocus="false" @submit.prevent="saveFees()" class="space-y-4">
    <div class="columns-1 gap-3 lg:columns-2">
    @forelse($feeTypes->groupBy(fn ($row) => $row->category->value) as $category => $categoryFees)
        <div class="mb-3 inline-block w-full break-inside-avoid overflow-hidden rounded-lg border border-gray-200 align-top"
            x-show="@js($categoryFees->pluck('id')->values()).some(id => !rows[id].deleted)">
            <h3 class="bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-800">{{ $categoryOptions[$category] }}</h3>
            <div class="divide-y divide-gray-100">
                @foreach($categoryFees as $feeType)
                    @php
                        $row = $feeRows[$feeType->id];
                        $used = $row['used'];
                        $isItem = $feeType->category === \App\FeeTypeCategory::ITEM;
                        $editable = $row['editable'];
                        $canToggle = !($feeType->is_system && (!$feeType->is_active || $feeType->settingKey() === 'service_late_payment_penalty'));
                    @endphp
                    <div class="flex flex-col gap-2 px-3 py-2 sm:flex-row sm:items-center"
                        x-data="{ rowId: @js($feeType->id) }" x-show="!rows[rowId].deleted">
                        <div class="min-w-0 flex-1">
                            @if($editable)
                                <div class="grid grid-cols-1 gap-3 {{ $isItem ? 'sm:grid-cols-3' : '' }}">
                                    <div>
                                        <div x-show="editingFees" x-cloak><x-form.input-label for="fee-name-{{ $feeType->id }}" :value="__('Name')" /></div>
                                        <p x-show="!editingFees" class="text-sm text-gray-700" x-text="rows[rowId].name">{{ $feeType->name }}</p>
                                        <div x-show="editingFees" x-cloak>
                                            <x-form.text-input id="fee-name-{{ $feeType->id }}" x-model="rows[rowId].name"
                                                x-bind:disabled="!editingFees || savingFees || deletingFee || rows[rowId].deleted" maxlength="255" required />
                                        </div>
                                    </div>
                                    @if($isItem)
                                        @foreach(['original_price' => 'Original Price (RM)', 'sell_price' => 'Sell Price (RM)'] as $field => $label)
                                        <div>
                                            <x-form.input-label for="fee-{{ $field }}-{{ $feeType->id }}" :value="__($label)" />
                                            <p x-show="!editingFees" class="py-2 text-sm text-gray-700" x-text="rows[rowId].{{ $field }} === '' ? '—' : 'RM ' + Number(rows[rowId].{{ $field }}).toFixed(2)">{{ $row[$field] === '' ? '—' : 'RM '.number_format($row[$field], 2) }}</p>
                                            <div x-show="editingFees" x-cloak>
                                                <x-form.text-input id="fee-{{ $field }}-{{ $feeType->id }}" type="number" min="0" max="42949672.95"
                                                    step="0.01" x-model="rows[rowId].{{ $field }}" x-bind:disabled="!editingFees || savingFees || deletingFee || rows[rowId].deleted" />
                                            </div>
                                        </div>
                                        @endforeach
                                    @endif
                                </div>
                            @else
                                <p class="text-sm text-gray-700">{{ $feeType->name }}</p>
                                @if($isItem)
                                <p class="mt-1 text-xs text-gray-500">{{ __('Original Price') }}: {{ $row['original_price'] === '' ? '—' : 'RM '.number_format($row['original_price'], 2) }}
                                    · {{ __('Sell Price') }}: {{ $row['sell_price'] === '' ? '—' : 'RM '.number_format($row['sell_price'], 2) }}</p>
                                @endif
                            @endif
                            <div x-show="editingFees" x-cloak class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                <span>{{ $feeType->is_system ? __('System') : __('Custom') }}</span>
                                @if($used)<span>{{ __('In use — cannot delete') }}</span>@endif
                                @if(!$canToggle)<span>{{ $feeType->is_active ? __('Controlled by Late Payment Penalty settings') : __('Disabled globally') }}</span>@endif
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center justify-end gap-4">
                            @canany(['super-admin', 'fee types.change status'])
                                @if($canToggle)
                                        <button type="button" role="switch" :aria-checked="rows[rowId].is_active"
                                            :disabled="!editingFees || savingFees || deletingFee"
                                            @click="rows[rowId].is_active = !rows[rowId].is_active"
                                            aria-label="{{ __('Active status for :name', ['name' => $feeType->name]) }}"
                                            class="inline-flex rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                            <span class="relative inline-flex h-5 w-9 rounded-full" :class="rows[rowId].is_active ? 'bg-indigo-600' : 'bg-gray-200'">
                                                <span class="absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform" :class="{ 'translate-x-4': rows[rowId].is_active }"></span>
                                            </span>
                                            <span class="sr-only" x-text="rows[rowId].is_active ? @js(__('Active')) : @js(__('Inactive'))"></span>
                                        </button>
                                @else
                                    <span class="text-xs text-gray-500">{{ $row['is_active'] ? __('Active') : __('Inactive') }}</span>
                                @endif
                            @else
                                <span class="text-xs text-gray-500">{{ $row['is_active'] ? __('Active') : __('Inactive') }}</span>
                            @endcanany
                            @canany(['super-admin', 'fee types.delete'])
                                @if(!$feeType->is_system)
                                <button type="button" x-show="editingFees" x-cloak x-bind:disabled="!editingFees || savingFees || deletingFee || @js($used)" @disabled($used)
                                    title="{{ $used ? __('This fee is in use and cannot be deleted.') : __('Delete unused fee type') }}"
                                    aria-label="{{ __('Delete :name', ['name' => $feeType->name]) }}"
                                    @click="openDelete(rowId)"
                                    class="rounded-lg bg-red-50 p-2 text-red-600 transition-colors hover:bg-red-100 hover:text-red-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                                @endif
                            @endcanany
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <p class="py-8 text-center text-sm text-gray-500 [column-span:all]">{{ __('No fee types found.') }}</p>
    @endforelse
    </div>
    @canany(['super-admin', 'fee types.edit', 'fee types.change status'])
    <div x-show="editingFees" x-cloak class="sticky bottom-0 z-30 flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 bg-white py-3">
        <span class="mr-auto text-sm text-gray-500" role="status" x-text="changes.length + ' ' + @js(__('fee types changed'))"></span>
        <x-form.secondary-button type="button" x-bind:disabled="savingFees || deletingFee" @click="resetFees(); editingFees = false">{{ __('Cancel') }}</x-form.secondary-button>
        <x-form.primary-button loading="savingFees" disabled-when="deletingFee || !changes.length">{{ __('Save Changes') }}</x-form.primary-button>
    </div>
    @endcanany
    </x-form.form>

    @canany(['super-admin', 'fee types.create', 'fee types.edit'])
    <x-modals.confirmation-modal id="fee-type-editor" :title="__('Fee Type')" max-width="sm:max-w-lg" :overflow-visible="true">
        <x-form.form method="POST" :autofocus="false"
            x-bind:action="fee.id ? @js(route('admin.fee-types.update', '__FEE__')).replace('__FEE__', fee.id) : @js(route('admin.fee-types.store'))"
            @submit.capture="if (savingFee) $event.preventDefault(); else savingFee = true">
            <input type="hidden" name="_method" :value="fee.id ? 'PUT' : 'POST'">
            <input type="hidden" name="fee_type_action" value="save">
            <input type="hidden" name="tab" value="fee-types">
            <input type="hidden" name="fee_type_id" :value="fee.id || ''">
            <div class="p-6 space-y-4">
                <p x-show="fee.used" class="text-sm text-gray-500">{{ __('This fee is in use. Its category is locked and it cannot be deleted. Item default prices affect future entries only.') }}</p>
                <div>
                    <x-form.input-label for="fee-type-name" :value="__('Name')" :required="true" />
                    <x-form.text-input id="fee-type-name" name="name" x-model="fee.name" maxlength="255" required />
                </div>
                <div>
                    <x-form.input-label for="fee-type-category" :value="__('Category')" :required="true" />
                    <x-form.input-select id="fee-type-category" name="category" :options="$categoryOptions"
                        x-bind:disabled="fee.used" x-model="feeCategories" />
                </div>
                <div x-show="feeCategories[0] === 'item'" x-cloak class="grid grid-cols-2 gap-3">
                    <div>
                        <x-form.input-label for="fee-type-cost" :value="__('Original Price (RM)')" />
                        <x-form.text-input id="fee-type-cost" type="number" step="0.01" min="0" max="42949672.95" x-model="fee.original_price" x-bind:disabled="feeCategories[0] !== 'item'" />
                    </div>
                    <div>
                        <x-form.input-label for="fee-type-price" :value="__('Default Sell Price (RM)')" />
                        <x-form.text-input id="fee-type-price" type="number" step="0.01" min="0" max="42949672.95" x-model="fee.sell_price" x-bind:disabled="feeCategories[0] !== 'item'" />
                    </div>
                </div>
                <input type="hidden" name="original_price" :value="feeCategories[0] === 'item' && fee.original_price !== '' ? Math.round(Number(fee.original_price) * 100) : ''">
                <input type="hidden" name="sell_price" :value="feeCategories[0] === 'item' && fee.sell_price !== '' ? Math.round(Number(fee.sell_price) * 100) : ''">
            </div>
            <div class="flex justify-end gap-3 border-t border-gray-100 rounded-b-[2rem] bg-white p-4">
                <x-form.secondary-button type="button" x-bind:disabled="savingFee" @click="$dispatch('close-fee-type-editor')">{{ __('Cancel') }}</x-form.secondary-button>
                <x-form.primary-button loading="savingFee">{{ __('Save Fee Type') }}</x-form.primary-button>
            </div>
        </x-form.form>
    </x-modals.confirmation-modal>
    @endcanany

    @canany(['super-admin', 'fee types.delete'])
    <x-modals.confirmation-modal id="delete-fee-type" :title="__('Delete Fee Type')">
        <x-form.form method="DELETE" :async="true" :autofocus="false"
            x-bind:action="deleting?.url || ''" @submit.prevent="confirmDeleteFee()">
            <p class="p-6 text-sm text-gray-600">{{ __('Delete the unused fee type') }} <strong x-text="deleting?.name || ''"></strong>? {{ __('This cannot be undone.') }}</p>
            <div class="flex justify-end gap-3 border-t border-gray-100 p-4">
                <x-form.secondary-button type="button" x-bind:disabled="deletingFee" @click="$dispatch('close-delete-fee-type')">{{ __('Cancel') }}</x-form.secondary-button>
                <x-form.primary-button variant="danger" loading="deletingFee">{{ __('Delete Fee Type') }}</x-form.primary-button>
            </div>
        </x-form.form>
    </x-modals.confirmation-modal>
    @endcanany
</section>
