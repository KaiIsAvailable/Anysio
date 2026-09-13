@props(['disabled' => false])

<input @disabled($disabled) @wheel.prevent {{ $attributes->merge(['class' => 'h-[38px] w-full text-sm px-3 py-1.5 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm disabled:bg-gray-50 disabled:text-gray-500 disabled:cursor-not-allowed']) }}>