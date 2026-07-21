@props([
    'value' => null,
    'required' => false,
])

<label {{ $attributes->merge(['class' => 'block uppercase font-medium text-sm text-gray-700']) }}>
    @if($required)
        <span class="text-red-500 ml-1">*</span>
    @endif
        
    {{ $value ?? $slot }}
</label>