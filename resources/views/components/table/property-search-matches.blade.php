@props(['property', 'colspan' => 7])

<x-table.search-matches
    :label="$property->name"
    :units="$property->relationLoaded('units') ? $property->units : collect()"
    :rooms="$property->relationLoaded('rooms') ? $property->rooms : collect()"
    :colspan="$colspan"
/>
