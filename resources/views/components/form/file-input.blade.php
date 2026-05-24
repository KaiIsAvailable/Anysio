@props(['disabled' => false, 'required' => false])

<input 
    type="file" 
    {{ $disabled ? 'disabled' : '' }}
    {{ $required ? 'required' : '' }}
    {!! $attributes->merge([
        'class' => 'block w-full text-sm text-slate-500 
                    file:mr-4 file:py-3 file:px-6 file:rounded-xl file:border-0 
                    file:text-sm file:font-bold file:bg-indigo-50 file:text-indigo-700 
                    hover:file:bg-indigo-100 border border-gray-200 rounded-2xl 
                    p-2 bg-gray-50/50 cursor-pointer transition-all'
    ]) !!}
/>