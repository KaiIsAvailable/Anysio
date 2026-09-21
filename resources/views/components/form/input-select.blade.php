@props([
    'disabled' => false,
    'options' => [],
    'placeholder' => null,
    'name' => null,
    'value' => null,
    'valueField' => null,
    'labelField' => null,
    'maxHeight' => 'max-h-60',
    'multiple' => false,
])

@php
    $parsedOptions = [];
    foreach ($options as $key => $option) {
        if (is_object($option)) {
            if (!$valueField || !$labelField) {
                throw new Exception('valueField and labelField are required when options is an object or Eloquent Collection.');
            }
            $parsedOptions[] = [
                'value' => (string) data_get($option, $valueField),
                'label' => data_get($option, $labelField),
            ];
        } elseif (is_array($option) && isset($option['value'], $option['label'])) {
            $parsedOptions[] = [
                'value' => (string) $option['value'],
                'label' => $option['label'],
            ];
        } else {
            $parsedOptions[] = [
                'value' => (string) $key,
                'label' => $option,
            ];
        }
    }

    $rawVal = old($name, $value);

    // Always normalize $selectedValues as an array for internal Alpine compatibility
    if (is_array($rawVal)) {
        $selectedValues = array_map('strval', $rawVal);
    } elseif (is_string($rawVal) && !empty($rawVal)) {
        $selectedValues = [(string)$rawVal];
    } else {
        $selectedValues = [];
    }

    // Pre-compute initial label for single-select display
    $selectedLabel = '';
    $matchedOpt = collect($parsedOptions)->first(fn($opt) => $opt['value'] === ($selectedValues[0] ?? ''));
    if ($matchedOpt) {
        $selectedLabel = $matchedOpt['label'];
    }
    
    $initialLabels = [];
    foreach ($parsedOptions as $opt) {
        if (in_array($opt['value'], $selectedValues)) {
            $initialLabels[$opt['value']] = $opt['label'];
        }
    }
@endphp

<div x-data="{
    open: false,
    search: '',
    isMultiple: @js($multiple),
    selectedValues: @js($selectedValues),
    selectedLabels: @js($initialLabels),
    selectedLabel: '{{ $selectedLabel }}',
    selectedValue: '{{ $selectedValues[0] ?? '' }}',
    lastValidLabel: '{{ $selectedLabel }}',
    dropUp: false,
    options: @js($parsedOptions),

    get isDisabled() {
        if (@js($disabled)) return true;
        if (typeof editing !== 'undefined' && !editing) return true;

        // Check if editingPendingRenewal exists in the parent scope and use its inverse
        if (typeof editingPendingRenewal !== 'undefined' && !editingPendingRenewal) return true;

        // Check native HTML attributes as a fallback
        if (this.$el.hasAttribute('disabled') || this.$el.getAttribute('aria-disabled') === 'true') return true;

        return false;
    },

    init() {
        this.$watch('selectedValues', (newValues) => {
            let labels = {};
            newValues.forEach(val => {
                let matched = this.options.find(opt => String(opt.value) === String(val));
                if (matched) {
                    labels[matched.value] = matched.label;
                }
            });
            this.selectedLabels = labels;
            if (!this.isMultiple && newValues.length > 0) {
                let matched = this.options.find(opt => String(opt.value) === String(newValues[0]));
                if (matched) {
                    this.selectedValue = matched.value;
                    this.selectedLabel = matched.label;
                    this.lastValidLabel = matched.label;
                }
            } else if (!this.isMultiple && newValues.length === 0) {
                this.selectedValue = '';
                this.selectedLabel = '';
                this.lastValidLabel = '';
            }
        });
    },
    get displayValue() {
        if (this.open) return this.search;
        if (this.isMultiple) return '';
        return this.selectedLabel || this.search;
    },
    get filteredOptions() {
        if (this.search === '') return this.options;
        return this.options.filter(opt => 
            String(opt.label).toLowerCase().includes(this.search.toLowerCase())
        );
    },
    isSelected(val) {
        return this.selectedValues.includes(String(val));
    },
    toggleOption(opt) {
        let stringVal = String(opt.value);
        if (this.isMultiple) {
            let index = this.selectedValues.indexOf(stringVal);
            if (index > -1) {
                this.selectedValues.splice(index, 1);
            } else {
                this.selectedValues.push(stringVal);
            }
            this.search = '';
            this.$nextTick(() => this.triggerChangeEvents());
        } else {
            this.selectedValues = [stringVal];
            this.selectedLabel = opt.label;
            this.lastValidLabel = opt.label;
            this.search = '';
            this.open = false;
            this.$nextTick(() => this.triggerChangeEvents());
        }
    },
    removeOption(val) {
        if (this.disabled) return;
        let index = this.selectedValues.indexOf(String(val));
        if (index > -1) {
            this.selectedValues.splice(index, 1);
            this.$nextTick(() => this.triggerChangeEvents());
        }
    },
    triggerChangeEvents() {
        if (this.$refs.hiddenInput) {
            this.$refs.hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
            this.$refs.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        this.$dispatch('change', { value: this.isMultiple ? this.selectedValues : (this.selectedValues[0] || null) });
    },
    toggleDropdown() {
        if (this.disabled) return;
        this.open = !this.open;
        if (this.open) {
            this.search = '';
            this.$nextTick(() => this.checkPosition());
        }
    },
    checkPosition() {
        let rect = this.$el.getBoundingClientRect();
        let spaceBelow = window.innerHeight - rect.bottom;
        let dropdownHeight = 260;
        this.dropUp = spaceBelow < dropdownHeight && rect.top > dropdownHeight;
    },
    validateInput() {
        setTimeout(() => {
            if (!this.isMultiple && this.search !== '') {
                let matched = this.options.find(opt => String(opt.label).toLowerCase() === String(this.search).toLowerCase());
                if (matched) {
                    this.toggleOption(matched);
                } else {
                    this.search = '';
                    this.selectedLabel = this.lastValidLabel;
                }
            }
            this.open = false;
        }, 200);
    }
}" x-modelable="selectedValues" @click.away="open = false" class="relative w-full" {!! $attributes->only(['@change', 'x-on:change']) !!}>

    <!-- Hidden native input(s) for form submission -->
    <template x-if="isMultiple">
        <template x-for="val in selectedValues" :key="val">
            <input type="hidden" name="{{ $name }}[]" :value="val" x-ref="hiddenInput" {{ $attributes->only('id') }}>
        </template>
    </template>

    <template x-if="isMultiple && selectedValues.length === 0">
        <input type="hidden" name="{{ $name }}[]" value="" x-ref="hiddenInput" {{ $attributes->only('id') }}>
    </template>

    <template x-if="!isMultiple">
        <input type="hidden" name="{{ $name }}" :value="selectedValues[0] || ''" x-ref="hiddenInput" {{ $attributes->only('id') }}>
    </template>

    <!-- Input Container -->
    <div :class="[
            isMultiple ? 'min-h-[38px] py-1 px-2' : 'h-[38px] py-0 px-0',
            isDisabled ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : 'bg-white text-gray-900'
        ]" 
        class="w-full flex flex-wrap items-center gap-1.5 border border-gray-300 focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500 rounded-md shadow-sm pr-10 relative overflow-hidden">
        
        <!-- Multi-select Badges / Tags -->
        <template x-if="isMultiple">
            <template x-for="val in selectedValues" :key="val">
                <span :class="isDisabled ? 'opacity-60' : ''" 
                    class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 text-xs font-medium px-2 py-0.5 rounded border border-indigo-100">
                    <span x-text="selectedLabels[val] || val"></span>
                    <button type="button" 
                        @click.stop="if (isDisabled) return; removeOption(val)" 
                        :class="isDisabled ? 'pointer-events-none text-indigo-200' : 'text-indigo-400 hover:text-indigo-600'"
                        class="focus:outline-none">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </span>
            </template>
        </template>

        <!-- Search / Text Input -->
        <input type="text"
            @if($disabled) disabled @endif
            {{ $attributes->whereStartsWith('x-bind') }}
            {{ $attributes->whereStartsWith(':disabled') }}
            :disabled="isDisabled"
            :value="displayValue"
            @focus="if(isDisabled) return; open = true; search = ''; $nextTick(() => checkPosition())"
            @input="if(isDisabled) return; open = true; search = $event.target.value; $nextTick(() => checkPosition())"
            @blur="validateInput()"
            placeholder="{{ $placeholder ?? '' }}"
            :class="[
                isMultiple ? 'flex-1 bg-transparent border-none focus:outline-none focus:ring-0 p-0 text-sm min-w-[60px]' : 'w-full h-full border-0 focus:ring-0 bg-transparent text-sm py-0 px-3',
                isDisabled ? 'text-gray-500 cursor-not-allowed' : 'text-gray-900'
            ]">

        <!-- Dropdown Arrow Icon -->
        <div @click="if (isDisabled) return; toggleDropdown(); if(open) $el.previousElementSibling.focus()" 
            :class="isDisabled ? 'pointer-events-none opacity-40 cursor-not-allowed' : 'cursor-pointer text-gray-400'"
            class="absolute inset-y-0 right-0 flex items-center px-2.5">
            <svg class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
    </div>

    <!-- Dropdown Menu -->
    <div x-show="open && !isDisabled" 
        x-transition.origin.duration.150ms
        style="display: none;" 
        :class="dropUp ? 'absolute left-0 bottom-full mb-1 w-full' : 'absolute left-0 top-full mt-1 w-full'"
        {{ $attributes->whereStartsWith('x-bind') }}
        class="z-50 bg-white shadow-xl rounded-md border border-gray-200 text-base ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
        
        <ul class="{{ $maxHeight }} overflow-y-auto py-1 divide-y divide-gray-50">
            <template x-for="opt in filteredOptions" :key="opt.value">
                <li @click="toggleOption(opt)"
                    :class="{
                        'bg-indigo-50 text-indigo-600 font-semibold': isSelected(opt.value),
                        'hover:bg-indigo-600 hover:text-white text-gray-900': !isMultiple || !isSelected(opt.value)
                    }"
                    class="cursor-pointer select-none relative py-2 px-3 text-sm transition-colors flex items-center justify-between">
                    
                    <span x-text="opt.label"></span>
                    
                    <!-- Checkmark appears ONLY when multiple = true and item is selected -->
                    <svg x-show="isMultiple && isSelected(opt.value)" class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </li>
            </template>

            <!-- Empty Search State -->
            <li x-show="filteredOptions.length === 0" class="cursor-default select-none relative py-2 px-3 text-gray-400 text-sm text-center">
                No matching options found.
            </li>
        </ul>
    </div>
</div>