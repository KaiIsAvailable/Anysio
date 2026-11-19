@props(['itemOptions' => [], 'value' => null, 'quantity' => 1, 'recordedTitle' => null, 'idPrefix' => 'maintenance'])
@php
    $selectedItem = old('fee_type_id', $value);
    $selectedItem = in_array($selectedItem, array_column($itemOptions, 'value'), true) ? $selectedItem : null;
@endphp
<div x-data="{
        maintenanceItemOptions: @js($itemOptions),
        selectedMaintenanceItem: @js($selectedItem ? [$selectedItem] : []),
        initialMaintenanceItem: @js($selectedItem ? [$selectedItem] : []),
        addMaintenanceItem(item) {
            if (item?.category !== 'item' || !item.id || !item.name) return;
            const option = { value: String(item.id), label: item.name };
            this.maintenanceItemOptions = [...this.maintenanceItemOptions.filter(existing => String(existing.value) !== option.value), option];
            this.selectedMaintenanceItem = [option.value];
        }
    }"
    x-on:fee-type-added.window="addMaintenanceItem($event.detail)"
    x-on:reset.window="if ($event.target === $el.closest('form')) selectedMaintenanceItem = [...initialMaintenanceItem]">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="sm:col-span-2">
            <div class="flex items-center justify-between gap-2">
                <x-form.input-label :for="$idPrefix.'-item'" value="Item" :required="true" />
                @canany(['super-admin', 'fee types.item'])
                    @canany(['super-admin', 'leases.add fee type', 'fee types.create'])
                        <button type="button" :disabled="loading" class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800 disabled:opacity-50 disabled:cursor-not-allowed"
                            title="Add Item" aria-label="Add Item" x-on:click="$dispatch('open-fee-type-modal', { category: 'item' })">
                            <svg class="w-4 h-4 bg-indigo-50 rounded-full p-0.5 border border-indigo-200" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Item
                        </button>
                    @endcanany
                @endcanany
            </div>
            <div class="mt-1">
                <x-form.input-select :id="$idPrefix.'-item'" name="fee_type_id" :options="$itemOptions"
                    x-model="selectedMaintenanceItem" x-effect="options = maintenanceItemOptions" disabled-when="loading"
                    :value="$selectedItem" :placeholder="$recordedTitle ?: 'Select an item'" :required="!$recordedTitle" />
            </div>
            @if($recordedTitle)
                <input type="hidden" name="title" value="{{ old('title', $recordedTitle) }}">
            @endif
        </div>
        <div>
            <x-form.input-label :for="$idPrefix.'-qty'" value="Quantity" :required="true" />
            <x-form.text-input :id="$idPrefix.'-qty'" type="number" name="qty" :value="old('qty', $quantity)" min="1" max="4294967295" step="1" required />
        </div>
    </div>
    <p x-show="maintenanceItemOptions.length === 0" class="mt-2 text-sm text-gray-500">No active items are available. Add an item in Fee Types.</p>
</div>
