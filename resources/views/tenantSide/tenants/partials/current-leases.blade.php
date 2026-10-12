@if($activeLeases->count() === 1)
    <a href="{{ route('tenants.leases.show', $activeLeases->sole()) }}"
       class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Current Leases</a>
@elseif($activeLeases->isNotEmpty())
    <details class="relative w-full sm:w-auto">
        <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 [&::-webkit-details-marker]:hidden">
            Current Leases
            <svg aria-hidden="true" class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
        </summary>
        <div class="mt-2 w-full rounded-xl border border-gray-200 bg-white p-2 shadow-lg sm:absolute sm:left-0 sm:top-full sm:z-30">
            <p class="px-3 py-2 text-sm font-semibold text-gray-600">Choose a lease</p>
            <ul class="space-y-1">
                @foreach($activeLeases as $activeLease)
                    <li>
                        <a href="{{ route('tenants.leases.show', $activeLease) }}" class="block rounded-lg px-3 py-2 text-sm text-indigo-700 hover:bg-indigo-50 focus-visible:bg-indigo-50 [overflow-wrap:anywhere]">
                            <span class="block font-semibold">{{ $activeLease->leasable_name }}</span>
                            <span class="block text-xs text-gray-500">{{ $activeLease->start_date->format('d M Y') }} &ndash; {{ $activeLease->end_date->format('d M Y') }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </details>
@else
    <p class="text-sm text-gray-600">You have no current leases.</p>
@endif
