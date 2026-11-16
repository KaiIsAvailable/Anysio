<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <a href="{{ route('admin.utilities.index', ['management_user_id' => $manager?->id]) }}" class="mb-3 inline-flex items-center text-indigo-600 hover:text-indigo-900 text-sm font-medium transition-colors">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Back to Utilities
                    </a>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Utility Reading History</h1>
                    <p class="mt-2 text-sm text-gray-500">View tenant utility records and meter readings.</p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                <div class="p-5 border-b border-gray-100 bg-white">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                        Utility Records: {{ number_format($utilities->total()) }}
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1500px] divide-y divide-gray-200 text-left">
                        <thead class="bg-gray-50">
                            <tr>
                                <x-table.th name="Property / Unit / Room" />
                                <x-table.th name="Owner" />
                                <x-table.th name="Tenant" />
                                <x-table.th name="Utility / Account No." />
                                <x-table.th name="Previous Reading" />
                                <x-table.th name="Current Reading" />
                                <x-table.th name="Usage" />
                                <x-table.th name="Amount" />
                                <x-table.th name="Recorded / Updated" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse($utilities as $utility)
                                @php
                                    $lease = $utility->lease;
                                    $asset = $utility->utilityService?->location ?? $lease?->leasable;
                                    $unit = $asset instanceof \App\Models\Room ? $asset->unit : ($asset instanceof \App\Models\Unit ? $asset : null);
                                    $property = $asset instanceof \App\Models\Property ? $asset : $unit?->property;
                                    $owner = $unit?->owner ?? ($asset instanceof \App\Models\Property ? $asset->owner : null);
                                    $accountNumber = match (strtolower(trim($utility->type))) {
                                        'water' => $unit?->water_acc_no,
                                        'electricity', 'electric' => $unit?->electricity_acc_no,
                                        'indah_water', 'indah water', 'iwk' => $unit?->indah_water_acc_no,
                                        default => null,
                                    };
                                @endphp
                                <tr class="hover:bg-indigo-50 transition-colors duration-150">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-semibold text-slate-900">{{ $property?->name ?? '—' }}</div>
                                        @if($unit)
                                            <div class="mt-1 text-sm text-gray-500">Unit {{ $unit->unit_no }}</div>
                                        @endif
                                        @if($asset instanceof \App\Models\Room)
                                            <div class="mt-1 text-sm text-gray-500">Room {{ $asset->room_no }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-900">{{ $owner?->name ?? '—' }}</td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-semibold text-slate-900">{{ $lease?->tenant?->user?->name ?? '—' }}</div>
                                        <div class="mt-1 text-xs text-gray-400">Lease: {{ $utility->lease_id }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-semibold text-slate-900">{{ \Illuminate\Support\Str::headline($utility->type) }}</div>
                                        <div class="mt-1 text-sm text-gray-500">{{ $accountNumber ?: '—' }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm tabular-nums text-slate-900">{{ $utility->prev_reading !== null ? number_format((float) $utility->prev_reading, 3) : '—' }}</td>
                                    <td class="px-6 py-4 text-sm tabular-nums text-slate-900">{{ $utility->curr_reading !== null ? number_format((float) $utility->curr_reading, 3) : '—' }}</td>
                                    <td class="px-6 py-4 text-sm tabular-nums text-slate-900">{{ $utility->prev_reading !== null && $utility->curr_reading !== null ? number_format((float) ($utility->consumption ?? ($utility->curr_reading - $utility->prev_reading)), 3) : '—' }}</td>
                                    <td class="px-6 py-4 text-sm font-semibold tabular-nums text-indigo-600">{{ $utility->amount !== null ? 'RM '.number_format($utility->amount / 100, 2) : '—' }}<x-utilities.invoice-status :record="$utility" /></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-slate-900">{{ $utility->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
                                        <div class="mt-1 text-xs text-gray-400">Updated: {{ $utility->updated_at?->format('d/m/Y H:i') ?? '—' }}</div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-16 text-center">
                                        <p class="text-sm font-semibold text-slate-900">No utility records found.</p>
                                        <p class="mt-2 text-sm text-gray-500">Existing utility records for leases you can access will appear here.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($utilities->hasPages())
                    <div class="bg-white px-6 py-4 border-t border-gray-100">
                        {{ $utilities->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
