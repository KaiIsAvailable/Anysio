@props(['value'])

<label {{ $attributes->merge(['class' => 'block uppercasefont-medium text-sm text-gray-700']) }}>
    {{ $value ?? $slot }}
</label>
