@props([
    'disabled' => false,
    'options' => [],
    'placeholder' => null,
    'name' => null,
    'value' => null,
    'valueField' => null,
    'labelField' => null,
])

<select
    name="{{ $name }}"
    {{ $disabled ? 'disabled' : '' }}
    {!! $attributes->merge([
        'class' => 'w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm'
    ]) !!}
>
    @if($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endif

    @foreach($options as $key => $option)
        @php
            if (is_object($option)) {
                if (!$valueField || !$labelField) {
                    throw new Exception(
                        'valueField and labelField are required when options is an object or Eloquent Collection.'
                    );
                }
                $optionValue = data_get($option, $valueField);
                $optionLabel = data_get($option, $labelField);
            } elseif (is_array($option) && isset($option['value'], $option['label'])) {
                $optionValue = $option['value'];
                $optionLabel = $option['label'];
            } else {
                $optionValue = $key;
                $optionLabel = $option;
            }
        @endphp

        <option
            value="{{ $optionValue }}"
            @selected((string) old($name, $value) === (string) $optionValue)
        >
            {{ $optionLabel }}
        </option>
    @endforeach
</select>