@props([
    'disabled' => false,
    'options' => [],
    'placeholder' => null,
    'name' => null,
    'value' => null, // 增加这一行
])

<select name="{{ $name }}" {{ $disabled ? 'disabled' : '' }} 
    {!! $attributes->merge([
        'class' => 'w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm'
    ]) !!}
>
    @if($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endif
    
    @foreach($options as $optionValue => $label)
        <option value="{{ $optionValue }}" 
            @selected((string)old($name, $value) === (string)$optionValue)>
            {{ $label }}
        </option>
    @endforeach
</select>