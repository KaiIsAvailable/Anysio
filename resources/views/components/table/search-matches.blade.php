@props(['label', 'units' => [], 'rooms' => [], 'colspan' => 7])

@php
    $matchingUnits = collect($units);
    $matchingRooms = collect($rooms);
@endphp

@if($matchingUnits->isNotEmpty() || $matchingRooms->isNotEmpty())
    <tr class="bg-indigo-50/50">
        <td colspan="{{ $colspan }}" class="px-6 py-4">
            <section class="rounded-lg border border-indigo-100 bg-white p-4" aria-label="Search matches in {{ $label }}">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <svg class="h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-6-6m2-5a7 7 0 1 1-14 0 7 7 0 0 1 14 0" />
                    </svg>
                    <h3 class="text-sm font-semibold text-slate-900">Matches in {{ $label }}</h3>
                    <span class="text-xs text-slate-500">
                        @if($matchingUnits->isNotEmpty())
                            {{ $matchingUnits->count() }} {{ str('unit')->plural($matchingUnits->count()) }}
                        @endif
                        @if($matchingUnits->isNotEmpty() && $matchingRooms->isNotEmpty()) &middot; @endif
                        @if($matchingRooms->isNotEmpty())
                            {{ $matchingRooms->count() }} {{ str('room')->plural($matchingRooms->count()) }}
                        @endif
                    </span>
                </div>
                <div class="flex flex-wrap gap-3">
                    @foreach($matchingUnits as $unit)
                        <div class="flex items-center gap-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                            <div>
                                <div class="text-xs text-slate-500">Unit</div>
                                <div class="text-sm font-semibold text-slate-900">{{ $unit->unit_no }}</div>
                            </div>
                            @canany(['super-admin', 'unit.show room'])
                                <a href="{{ route('admin.units.show', $unit->id) }}" class="rounded-md px-2 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="Open unit {{ $unit->unit_no }} in {{ $label }}">Open unit &rarr;</a>
                            @endcanany
                        </div>
                    @endforeach
                    @foreach($matchingRooms as $room)
                        <div class="flex items-center gap-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                            <div>
                                <div class="text-xs text-slate-500">Room &middot; Unit {{ $room->unit?->unit_no ?? '-' }}</div>
                                <div class="text-sm font-semibold text-slate-900">{{ $room->room_no }}</div>
                            </div>
                            @canany(['super-admin', 'room.show'])
                                <a href="{{ route('admin.rooms.show', $room->id) }}" class="rounded-md px-2 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="Open room {{ $room->room_no }} in {{ $label }}">Open room &rarr;</a>
                            @endcanany
                        </div>
                    @endforeach
                </div>
            </section>
        </td>
    </tr>
@endif
