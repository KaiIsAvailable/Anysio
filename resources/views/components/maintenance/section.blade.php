@props(['title'])
<section {{ $attributes->class(['overflow-hidden rounded-xl border border-gray-100 bg-white shadow-lg']) }}>
    <div class="border-b border-gray-100 px-5 py-4"><h2 class="text-lg font-semibold text-slate-900">{{ $title }}</h2></div>
    <div class="space-y-3 p-5 text-sm text-gray-700">{{ $slot }}</div>
</section>
