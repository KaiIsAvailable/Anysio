@props([
    'disabled' => false,
    'options' => [],
    'placeholder' => null,
    'name' => null,
    'value' => null,
    'valueField' => null,
    'labelField' => null,
    'maxHeight' => 'max-h-60', // 👈 1. Add maxHeight prop with a default value
])

@php
    $parsedOptions = [];
    foreach ($options as $key => $option) {
        if (is_object($option)) {
            if (!$valueField || !$labelField) {
                throw new Exception('valueField and labelField are required when options is an object or Eloquent Collection.');
            }
            $parsedOptions[] = [
                'value' => data_get($option, $valueField),
                'label' => data_get($option, $labelField),
            ];
        } elseif (is_array($option) && isset($option['value'], $option['label'])) {
            $parsedOptions[] = [
                'value' => $option['value'],
                'label' => $option['label'],
            ];
        } else {
            $parsedOptions[] = [
                'value' => $key,
                'label' => $option,
            ];
        }
    }

    $selectedValue = old($name, $value);
    
    $selectedLabel = '';
    foreach ($parsedOptions as $opt) {
        if ((string)$opt['value'] === (string)$selectedValue) {
            $selectedLabel = $opt['label'];
            break;
        }
    }
@endphp

<div x-data="{
    open: false,
    search: '',
    selectedLabel: '{{ $selectedLabel }}',
    selectedValue: '{{ $selectedValue }}',
    lastValidLabel: '{{ $selectedLabel }}',
    dropUp: false,
    options: @js($parsedOptions),
    get displayValue() {
        if (this.open) {
            return this.search;
        }
        return this.selectedLabel || this.search;
    },
    get filteredOptions() {
        if (this.search === '') return this.options;
        return this.options.filter(opt => 
            String(opt.label).toLowerCase().includes(this.search.toLowerCase())
        );
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
    selectOption(opt) {
        this.selectedValue = opt.value;
        this.selectedLabel = opt.label;
        this.lastValidLabel = opt.label;
        this.search = '';
        this.open = false;
        this.$nextTick(() => {
            if (this.$refs.hiddenInput) {
                this.$refs.hiddenInput.value = opt.value;
                this.$refs.hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                this.$refs.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            this.$dispatch('change', { value: opt.value });
        });
    },
    validateInput() {
        setTimeout(() => {
            if (this.search !== '') {
                let matched = this.options.find(opt => String(opt.label).toLowerCase() === String(this.search).toLowerCase());
                if (matched) {
                    this.selectOption(matched);
                } else {
                    this.search = '';
                    this.selectedLabel = this.lastValidLabel;
                }
            }
            this.open = false;
        }, 200);
    }
}" @click.away="open = false" class="relative w-full" {!! $attributes->only(['@change', 'x-on:change']) !!}>

    <!-- Hidden native input for form submission with ID support -->
    <input type="hidden" name="{{ $name }}" x-model="selectedValue" x-ref="hiddenInput" {{ $attributes->only('id') }}>

    <!-- Professional Text Input -->
    <div class="relative">
        <input type="text"
            @disabled($disabled)
            :value="displayValue"
            @focus="open = true; search = ''; $nextTick(() => checkPosition())"
            @input="open = true; search = $event.target.value; selectedValue = ''; $nextTick(() => checkPosition())"
            @blur="validateInput()"
            placeholder="{{ $placeholder ?? '' }}"
            class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm bg-white pr-10">

        <!-- Dropdown Arrow Icon -->
        <div @click="toggleDropdown(); if(open) $el.previousElementSibling.focus()" class="absolute inset-y-0 right-0 flex items-center px-2.5 cursor-pointer text-gray-400">
            <svg class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
    </div>

    <!-- Custom Professional Dropdown Menu with Smart Positioning -->
    <div x-show="open" 
         x-transition.origin.duration.150ms
         style="display: none;" 
         :class="dropUp ? 'absolute left-0 bottom-full mb-1 w-full' : 'absolute left-0 top-full mt-1 w-full'"
         class="z-50 bg-white shadow-xl rounded-md border border-gray-200 text-base ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
        
        {{-- 2. Bind the maxHeight prop dynamically to the <ul> element --}}
        <ul class="{{ $maxHeight }} overflow-y-auto py-1 divide-y divide-gray-50">
            <template x-for="opt in filteredOptions" :key="opt.value">
                <li @click="selectOption(opt)"
                    class="cursor-pointer select-none relative py-2 px-3 hover:bg-indigo-600 hover:text-white text-gray-900 text-sm transition-colors"
                    class="{'bg-indigo-50 text-indigo-600 font-semibold': selectedValue == opt.value}">
                    <span x-text="opt.label"></span>
                </li>
            </template>

            <!-- Empty Search State -->
            <li x-show="filteredOptions.length === 0" class="cursor-default select-none relative py-2 px-3 text-gray-400 text-sm text-center">
                No matching options found.
            </li>
        </ul>
    </div>
</div>