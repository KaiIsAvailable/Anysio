@props(['preview', 'token', 'manager'])
<x-modals.confirmation-modal id="water-invoice-preview" title="Preview Water Invoices" max-width="sm:max-w-6xl">
    <x-form.form method="POST" action="{{ route('admin.utilities.readings.invoices.generate') }}" :autofocus="false" class="p-6">
        <x-form.text-input type="hidden" name="management_user_id" :value="$manager->id" />
        <x-form.text-input type="hidden" name="preview_token" :value="$token" />
        <div class="max-h-[65vh] overflow-y-auto space-y-4">
            <p class="text-sm text-gray-600">Review these charges before generating invoices. Charges for the same lease are combined into one invoice.</p>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div class="rounded-lg border border-gray-200 p-4"><p class="text-sm text-gray-500">Invoices to generate</p><p class="mt-1 text-xl font-semibold text-slate-900">{{ $preview['lease_count'] }}</p></div>
                <div class="rounded-lg border border-gray-200 p-4"><p class="text-sm text-gray-500">Meters included</p><p class="mt-1 text-xl font-semibold text-slate-900">{{ $preview['meter_count'] }}</p></div>
                <div class="col-span-2 rounded-lg border border-indigo-100 bg-indigo-50 p-4 sm:col-span-1"><p class="text-sm text-indigo-700">Total amount</p><p class="mt-1 text-2xl font-bold text-indigo-700">RM {{ number_format($preview['total'] / 100, 2) }}</p></div>
            </div>
            <p class="text-sm text-gray-500">Billing month: {{ \Carbon\Carbon::parse($preview['month'].'-01')->format('m/Y') }} · Invoice date: {{ \Carbon\Carbon::parse($preview['issue_date'])->format('d/m/Y') }} · Payment due: {{ \Carbon\Carbon::parse($preview['due_date'])->format('d/m/Y') }}</p>
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <span class="font-medium text-indigo-700">{{ $preview['meter_count'] }} {{ \Illuminate\Support\Str::plural('meter', $preview['meter_count']) }} ready</span>
            </div>
            @if($preview['wallet_enabled'])<p class="rounded-lg bg-indigo-50 p-3 text-sm text-indigo-800"><strong>Wallet auto-payment: On.</strong> Available wallet funds will be applied after invoice creation. Amounts shown are before wallet payments.</p>@endif
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="w-full min-w-[640px] text-left divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr><x-table.th name="Tenant" /><x-table.th name="Property / Unit / Room" /><x-table.th name="Reading period" /><x-table.th name="Amount" /></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($preview['entries'] as $entry)
                            <tr @class(['hover:bg-indigo-50 focus-within:bg-indigo-50', 'cursor-pointer' => Gate::any(['super-admin', 'leases.show'])])
                                @canany(['super-admin', 'leases.show'])
                                    data-lease-url="{{ route('admin.leases.show', $entry['lease_id']) }}"
                                    @click="if (!window.getSelection()?.toString() && !$event.target.closest('a, button, input, select, textarea, summary')) window.open($el.dataset.leaseUrl, '_blank', 'noopener,noreferrer')"
                                @endcanany>
                                <td class="px-6 py-3 text-sm align-top" @click.stop>
                                    @canany(['super-admin', 'tenant.show'])
                                        <a href="{{ route('admin.tenants.show', $entry['tenant_id']) }}" target="_blank" rel="noopener noreferrer" @click.stop @keydown.enter.stop class="font-medium text-indigo-600 hover:underline" aria-label="Open tenant details for {{ $entry['tenant'] }} in a new tab">{{ $entry['tenant'] }}</a>
                                    @else
                                        {{ $entry['tenant'] }}
                                    @endcanany
                                </td>
                                <td class="px-6 py-3 text-sm align-top">
                                    <div class="flex items-center gap-2">
                                        <span>{{ $entry['location_name'] }} - {{ $entry['location_type'] }}</span>
                                        @if($entry['location_type'] !== 'Property' && $entry['property_name'])
                                            <x-form.input-label :floating-info="true" class="shrink-0"><x-slot:info>Property: {{ $entry['property_name'] }}</x-slot:info></x-form.input-label>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-sm whitespace-nowrap">{{ \Carbon\Carbon::parse($entry['from'])->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($entry['to'])->format('d/m/Y') }}</td>
                                <td class="px-6 py-3 align-top">
                                    <div class="flex items-center gap-2 whitespace-nowrap">
                                    <span class="text-sm font-semibold">RM {{ number_format($entry['amount'] / 100, 2) }}</span>
                                    <x-form.input-label :floating-info="true" class="shrink-0">
                                    <x-slot:info>
                                    <p class="mb-2 font-semibold text-white">{{ data_get($entry, 'calculation_details.tariff.label') }} · v{{ $entry['tariff_version'] }}</p>
                                    <x-utilities.calculation-details :snapshot="$entry['calculation_details']" :previous="$entry['previous']" :current="$entry['current']" :consumption="$entry['consumption']" :amount="$entry['source_amount']" :amount-label="$entry['allocation_method'] ? 'Shared meter total' : 'Calculated charge'" :inverse="true" />
                                    @if($entry['allocation_method'])
                                        @php
                                            $shareLabels = ['equal' => 'Equal shares', 'occupants' => 'Occupant-days', 'percentage' => 'Percentage', 'submeter' => 'Submeter usage', 'manual' => 'Manual allocation'];
                                            $numerator = (int) $entry['allocation_weight'];
                                            $denominator = (int) $entry['allocation_total_weight'];
                                            $a = $numerator; $b = $denominator;
                                            while ($b) { [$a, $b] = [$b, $a % $b]; }
                                            if ($a) { $numerator = intdiv($numerator, $a); $denominator = intdiv($denominator, $a); }
                                        @endphp
                                        <div class="mt-2 border-t border-gray-600 pt-2 text-xs text-gray-200">
                                            <p>{{ $shareLabels[$entry['allocation_method']] ?? 'Allocation' }}</p>
                                            @if($entry['allocation_method'] !== 'manual' && $denominator > 0)
                                                <p>RM {{ number_format($entry['source_amount'] / 100, 2) }} × {{ $numerator }} / {{ $denominator }} = RM {{ number_format($entry['amount'] / 100, 2) }}</p>
                                                <p class="text-gray-400">Shares rounded to total the meter bill.</p>
                                            @else
                                                <p>Assigned share: RM {{ number_format($entry['amount'] / 100, 2) }}</p>
                                            @endif
                                        </div>
                                    @endif
                                    </x-slot:info>
                                    </x-form.input-label>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">No saved readings are eligible for invoice generation.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($preview['skipped'])
                <details id="water-preview-exclusions" @if(!$preview['entries']) open @endif class="group rounded-lg border border-gray-200 p-3">
                    <summary class="flex cursor-pointer list-none items-center gap-2 text-sm text-gray-600 [&::-webkit-details-marker]:hidden" @keydown.enter.stop>
                        <svg class="h-4 w-4 shrink-0 fill-current transition-transform duration-200 group-open:rotate-90" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span>{{ count($preview['skipped']) }} reading(s) excluded — view reasons</span>
                    </summary>
                    <p class="mt-2 text-xs text-gray-500">Excluded readings will not be billed in this batch.</p>
                    <ul class="mt-3 space-y-2 text-xs text-gray-500">
                        @foreach($preview['skipped'] as $excluded)<li><strong>{{ $excluded['location'] }}</strong> — {{ $excluded['reason'] }}</li>@endforeach
                    </ul>
                </details>
            @endif
        </div>
        <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
            <x-form.secondary-button type="button" @click="$dispatch('close-water-invoice-preview')">Cancel</x-form.secondary-button>
            @if($token && $preview['entries'])<x-form.primary-button type="submit" loading="loading">Generate {{ $preview['lease_count'] }} Invoice(s)</x-form.primary-button>@endif
        </div>
    </x-form.form>
</x-modals.confirmation-modal>
