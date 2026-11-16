@props([
    'id', 
    'name', 
    'value' => '',
    'mode' => 'date', // 'date' or 'month'
    'maxDate' => null,
    'disableMobile' => false,
])

<input type="text" 
       id="{{ $id }}" 
       name="{{ $name }}" 
       x-data="{ destroyPicker: null, disabledObserver: null, destroy() { this.disabledObserver?.disconnect(); this.destroyPicker?.(); } }"
       {{ $attributes->whereStartsWith('x-model') }}
       x-init="
           let fp = flatpickr($el, {
               dateFormat: @js($mode === 'month' ? 'Y-m' : 'Y-m-d'),
               @if($mode === 'month')
               plugins: [new monthSelectPlugin({ shorthand: true, dateFormat: 'Y-m', altFormat: 'm/Y' })],
               @endif
               disableMobile: @js($mode === 'month' || $disableMobile),
               altInput: true,
               altFormat: @js($mode === 'month' ? 'm/Y' : 'd/m/Y'),
               altInputClass: 'h-[38px] w-full text-sm px-3 py-1.5 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm disabled:bg-gray-50 disabled:text-gray-500 disabled:cursor-not-allowed',
               allowInput: true,
               maxDate: @js($maxDate),
               defaultDate: @js(request($name, old($name, $value))),
               onChange: (selectedDates, dateStr) => {
                   $el.value = dateStr;
                   $el.dispatchEvent(new Event('input', { bubbles: true }));
               }
           });
           destroyPicker = () => fp.destroy();
           const syncDisabled = () => {
               const disabled = $el.matches(':disabled');
               if (fp.altInput) fp.altInput.disabled = disabled;
               if (disabled) fp.close();
           };
           disabledObserver = new MutationObserver(syncDisabled);
           disabledObserver.observe($el, { attributes: true, attributeFilter: ['disabled'] });
           for (let parent = $el.parentElement; parent; parent = parent.parentElement) {
               if (parent.tagName === 'FIELDSET') disabledObserver.observe(parent, { attributes: true, attributeFilter: ['disabled'] });
           }
           $nextTick(syncDisabled);
           @if($attributes->has('x-model'))
           $nextTick(() => fp.setDate($el.value || null, false));
           $watch(@js($attributes->get('x-model')), value => {
               if (fp.input.value !== (value || '')) fp.setDate(value || null, false);
           });
           @endif
       "
       {{ $attributes->except('x-model')->merge(['class' => 'h-[38px] w-full text-sm px-3 py-1.5 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm']) }}
       placeholder="{{ $mode === 'month' ? 'MM/YYYY' : 'DD/MM/YYYY' }}">
