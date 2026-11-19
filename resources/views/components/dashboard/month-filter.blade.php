@props(['prefix', 'section'])
@php
    $monthName = $prefix.'_month';
    $yearName = $prefix.'_year';
    $otherFilters = request()->except([$monthName, $yearName, $prefix.'_page']);
    $months = ['' => 'All Months'];
    for ($month = 1; $month <= 12; $month++) $months[str_pad($month, 2, '0', STR_PAD_LEFT)] = now()->startOfYear()->addMonths($month - 1)->format('F');
@endphp
<x-form.form method="GET" :autofocus="false" :action="request()->url().'#'.$section" class="flex items-center gap-2">
    @foreach($otherFilters as $name => $value)
        @foreach(is_array($value) ? $value : [$value] as $entry)
            @if(is_scalar($entry))<input type="hidden" name="{{ $name }}{{ is_array($value) ? '[]' : '' }}" value="{{ $entry }}">@endif
        @endforeach
    @endforeach
    <input type="hidden" name="{{ $yearName }}" value="{{ request($yearName, now()->year) }}">
    <div class="w-44">
        <x-form.input-select :id="$monthName" :name="$monthName" :openOnFocus="false" :options="$months" :value="request($monthName, '')" placeholder="All Months"
            x-on:change="$el.closest('form').submit()" />
    </div>
</x-form.form>
