@props([
    'disabled' => false,
    'options' => [],
    'placeholder' => null,
    'name' => null, // 明确接收 name
])

<select name="{{ $name }}" {{ $disabled ? 'disabled' : '' }} 
    {!! $attributes->merge([
        'class' => 'w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm uppercase'
    ]) !!}
>
    @if($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endif
    
    @foreach($options as $value => $label)
        {{-- 直接取 $value 为 value，判断逻辑更清晰 --}}
        <option value="{{ $value }}" @selected(old($name) == $value)>
            {{ $label }}
        </option>
    @endforeach
</select>