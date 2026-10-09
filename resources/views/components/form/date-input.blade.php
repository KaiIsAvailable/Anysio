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
               altInputClass: 'h-[38px] w-full text-sm px-3 py-1.5 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm',
               allowInput: true,
               defaultDate: @js(old($name, $value)),
               onChange: (selectedDates, dateStr) => {
                   $el.dispatchEvent(new CustomEvent('input', { detail: dateStr }));
               }
           })

           $watch('$el.value', (value) => {
               if (!value) {
                   fp.clear();
               }
           });
       "
       {{ $attributes->except('x-model')->merge(['class' => 'h-[38px] w-full text-sm px-3 py-1.5 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm']) }}
       placeholder="{{ $mode === 'month' ? 'MM/YYYY' : 'DD/MM/YYYY' }}">