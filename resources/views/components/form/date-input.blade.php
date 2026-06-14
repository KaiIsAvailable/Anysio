@props(['id', 'name', 'value' => ''])

<input type="text" 
       id="{{ $id }}" 
       name="{{ $name }}" 
       value="{{ old($name, $value) }}"
       {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500']) }}
       placeholder="DD/MM/YYYY">

<script>
    flatpickr("#{{ $id }}", {
        dateFormat: "Y-m-d", 
        altInput: true,      
        altFormat: "d/m/Y",  
        allowInput: true,
    });
</script>