@props([
    'id', 
    'name', 
    'value' => '',
    'mode' => 'date' // 'date' or 'month'
])

<input type="text" 
       id="{{ $id }}" 
       name="{{ $name }}" 
       {{ $attributes->whereStartsWith('x-model') }}
       x-init="
           flatpickr($el, {
               dateFormat: 'Y-m-d',
               altInput: true,
               altFormat: @js($mode === 'month' ? 'm/Y' : 'd/m/Y'),
               allowInput: true,
               defaultDate: @js(old($name, $value)),
               onChange: (selectedDates, dateStr) => {
                   // Directly updates your Alpine x-model variable on selection
                   $el.dispatchEvent(new CustomEvent('input', { detail: dateStr }));
               }
           })
       "
       {{ $attributes->except('x-model')->merge(['class' => 'mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500']) }}
       placeholder="{{ $mode === 'month' ? 'MM/YYYY' : 'DD/MM/YYYY' }}">