@props(['state' => 'editing'])

<button type="button" 
        @click="{{ $state }} = !{{ $state }}"
        :class="{{ $state }} ? 'bg-amber-50 text-amber-700 border-amber-300' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
        class="inline-flex items-center px-3 py-1.5 border text-sm font-medium rounded-md shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500">
    <span x-show="!{{ $state }}">{{ __('Enable Edit') }}</span>
    <span x-show="{{ $state }}" x-cloak>{{ __('Editing') }}</span>
</button>