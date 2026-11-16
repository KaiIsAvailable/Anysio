@props(['disabled' => false])
<textarea @disabled($disabled) {{ $attributes->merge(['class' => 'w-full text-sm px-3 py-2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm disabled:bg-gray-50 disabled:text-gray-500']) }}>{{ $slot }}</textarea>
