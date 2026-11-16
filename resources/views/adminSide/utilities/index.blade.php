<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap justify-between items-start gap-4 mb-8">
                <div><h1 class="text-3xl font-bold text-slate-900 tracking-tight">Utilities</h1><p class="mt-2 text-sm text-gray-500">Set up services at each property, unit and room. Confirm the actual meter arrangement before billing.</p></div>
                @if($manager)
                    <div class="flex flex-wrap gap-2">
                    @if($type === 'water')
                        @canany(['super-admin', 'utilities.enter readings'])
                            <x-form.primary-button type="button" loading="loading" data-readings-url="{{ route('admin.utilities.readings', ['management_user_id' => $manager->id]) }}"
                                @click="loading = true; $nextTick(() => window.location.assign($el.dataset.readingsUrl))">Enter Readings</x-form.primary-button>
                        @endcanany
                    @endif
                    @canany(['super-admin', 'utilities.reading history'])
                    <x-form.primary-button type="button" loading="loading"
                        data-history-url="{{ route('admin.utilities.history', ['management_user_id' => $manager->id]) }}"
                        @click="loading = true; $nextTick(() => window.location.assign($el.dataset.historyUrl))">
                        Reading History
                    </x-form.primary-button>
                    @endcanany
                    </div>
                @endif
            </div>
            <div class="bg-white rounded-xl shadow-lg border border-gray-100">
                <div class="p-5 border-b border-gray-100 space-y-4">
                    <x-form.form method="GET" action="{{ route('admin.utilities.index') }}" :autofocus="false"
                        @change="if ($event.detail?.value !== undefined && $event.target.closest('[data-utility-auto-filter]') && !loading) $el.requestSubmit()"
                        class="flex flex-wrap items-end gap-4">
                        @can('super-admin')
                            <div class="w-full sm:w-64" data-utility-auto-filter>
                                <x-form.input-label for="utility-management-account" value="Management account" class="mb-1" />
                                <x-form.input-select id="utility-management-account" name="management_user_id" :options="$managers" valueField="id" labelField="name" :value="$manager?->id" placeholder="Select an account" />
                            </div>
                        @else
                            <div class="text-sm font-semibold text-slate-900">{{ $manager?->name }}</div>
                        @endcan
                        <div class="w-full sm:w-48" data-utility-auto-filter>
                            <x-form.input-label for="utility-type" value="Utility type" class="sr-only" />
                            <x-form.input-select id="utility-type" name="type" :options="collect(\App\Models\UtilityService::TYPES)->mapWithKeys(fn ($option) => [$option => \Illuminate\Support\Str::headline($option)])" :value="$type" />
                        </div>
                        <span class="py-2 text-xs text-gray-500">{{ number_format($services->total()) }} services</span>
                        <div class="w-full md:ml-auto md:w-auto">
                            <x-table.search :is-advance-search="true" :show-utility-filters="true" :custom-filter-defaults="$utilityFilterDefaults" placeholder="Search locations, tenants, meters, readings, amounts..." />
                        </div>
                    </x-form.form>
                    @if($manager)
                        @canany(['super-admin', 'utilities.manage meters'])
                            <details class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <summary class="cursor-pointer text-sm font-semibold text-indigo-600">Initialize {{ \Illuminate\Support\Str::headline($type) }} Services</summary>
                                <p class="my-3 text-sm text-gray-600">Create one unconfirmed service for every property, unit and room managed by {{ $manager->name }}. Existing services are skipped, including inactive and deleted ones. Existing readings are preserved and linked where ownership is clear. Parent connections and opening readings remain empty.</p>
                                <x-form.form method="POST" action="{{ route('admin.utilities.initialize') }}" :autofocus="false">
                                    <x-form.text-input type="hidden" name="management_user_id" :value="$manager->id" />
                                    <x-form.text-input type="hidden" name="type" :value="$type" />
                                    <x-form.primary-button loading="loading" type="submit">Initialize Services</x-form.primary-button>
                                </x-form.form>
                            </details>
                        @endcanany
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1500px] divide-y divide-gray-200 text-left">
                        <thead class="bg-gray-50"><tr>
                            <x-table.th name="Property / Unit / Room" /><x-table.th name="Current Tenant" /><x-table.th name="Service / Account" /><x-table.th name="Meter / Parent" /><x-table.th name="Previous Reading" /><x-table.th name="Current Reading" /><x-table.th name="Usage" /><x-table.th name="Amount" /><x-table.th name="Recorded / Updated" /><x-table.th name="Action" />
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse($services as $service)
                                @php
                                    $record = $service->latestRecord;
                                    $previousReading = $record ? $record->prev_reading : ($service->billing_method === 'metered' ? $service->opening_reading : null);
                                    $location = $service->location;
                                    $unit = $location instanceof \App\Models\Room ? $location->unit : ($location instanceof \App\Models\Unit ? $location : null);
                                    $property = $location instanceof \App\Models\Property ? $location : $unit?->property;
                                @endphp
                                <tr class="hover:bg-indigo-50 transition-colors duration-150">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-semibold text-slate-900">
                                            @if($location instanceof \App\Models\Property)
                                                @canany(['super-admin', 'property.show unit'])
                                                    <a href="{{ route('admin.properties.show', $property->id) }}" class="font-semibold text-indigo-600 hover:text-indigo-900 hover:underline">{{ $property->name }}</a>
                                                @else
                                                    <span class="font-semibold text-slate-900">{{ $property->name }}</span>
                                                @endcanany
                                            @elseif($location instanceof \App\Models\Unit)
                                                @canany(['super-admin', 'unit.show room'])
                                                    <a href="{{ route('admin.units.show', $unit->id) }}" class="font-semibold text-indigo-600 hover:text-indigo-900 hover:underline">{{ $unit->unit_no }}</a>
                                                @else
                                                    <span class="font-semibold text-slate-900">{{ $unit->unit_no }}</span>
                                                @endcanany
                                            @elseif($location instanceof \App\Models\Room)
                                                @canany(['super-admin', 'room.show'])
                                                    <a href="{{ route('admin.rooms.show', $location->id) }}" class="font-semibold text-indigo-600 hover:text-indigo-900 hover:underline">{{ $location->room_no }}</a>
                                                @else
                                                    <span class="font-semibold text-slate-900">{{ $location->room_no }}</span>
                                                @endcanany
                                            @else
                                                <span class="text-gray-400">Location unavailable</span>
                                            @endif
                                        </div>
                                        <div class="mt-1 text-xs text-gray-400">{{ class_basename($service->location_type) }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-900">
                                        @forelse($tenants[$service->id] as $tenant)
                                            @unless($loop->first)<span class="text-gray-400">, </span>@endunless
                                            @canany(['super-admin', 'tenant.show'])
                                                <a href="{{ route('admin.tenants.show', $tenant->id) }}" class="font-semibold text-indigo-600 hover:text-indigo-900 hover:underline">{{ $tenant->user->name }}</a>
                                            @else
                                                <span class="font-semibold text-slate-900">{{ $tenant->user->name }}</span>
                                            @endcanany
                                        @empty
                                            <span class="text-gray-400">—</span>
                                        @endforelse
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-semibold text-slate-900">{{ $service->label }}</div>
                                        <div class="mt-1 text-xs text-gray-500">{{ ucfirst($service->billing_method) }} · Account: {{ $service->account_number ?: '—' }}</div>
                                        <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $service->status === 'active' ? 'bg-emerald-100 text-emerald-800' : ($service->status === 'inactive' ? 'bg-gray-100 text-gray-600' : 'bg-amber-100 text-amber-800') }}">{{ ucfirst($service->status) }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-900"><div>{{ $service->meter_number ?: '—' }}</div><div class="mt-1 text-xs text-gray-400">Parent: {{ $service->parentService?->label ?? 'Not configured' }}</div></td>
                                    <td class="px-6 py-4 text-sm tabular-nums">{{ $previousReading !== null ? number_format((float) $previousReading, 3) : '—' }}</td>
                                    <td class="px-6 py-4 text-sm tabular-nums">{{ $record?->curr_reading !== null ? number_format((float) $record->curr_reading, 3) : '—' }}</td>
                                    <td class="px-6 py-4 text-sm tabular-nums">{{ $record?->prev_reading !== null && $record?->curr_reading !== null ? number_format((float) ($record->consumption ?? ($record->curr_reading - $record->prev_reading)), 3) : '—' }}</td>
                                    <td class="px-6 py-4 text-sm font-semibold text-indigo-600">{{ $record?->amount !== null ? 'RM '.number_format($record->amount / 100, 2) : '—' }}<x-utilities.invoice-status :record="$record" :show-charge-details="false" /></td>
                                    <td class="px-6 py-4 text-xs whitespace-nowrap"><div>{{ $record?->created_at?->format('d/m/Y H:i') ?? '—' }}</div><div class="mt-1 text-gray-400">{{ $record?->updated_at?->format('d/m/Y H:i') ?? '—' }}</div></td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap items-center gap-2">
                                        <x-utilities.invoice-status :record="$record" :show-status="false" charge-link-class="inline-flex whitespace-nowrap text-xs font-medium text-indigo-600 hover:text-indigo-900 hover:underline" />
                                        @canany(['super-admin', 'utilities.manage meters'])
                                            <a href="{{ route('admin.utilities.edit', ['utilityService' => $service->id, 'management_user_id' => $manager->id]) }}" class="inline-flex p-2 text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors" title="Edit utility service" aria-label="Edit {{ $service->label }}">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            </a>
                                            <button type="button" class="inline-flex p-2 text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 rounded-lg transition-colors" title="Delete utility service" aria-label="Delete {{ $service->label }}"
                                                data-delete-url="{{ route('admin.utilities.destroy', $service) }}" data-service-label="{{ $service->label }}"
                                                @click="$dispatch('open-utility-delete', { actionUrl: $el.dataset.deleteUrl, label: $el.dataset.serviceLabel })">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        @endcanany
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="px-6 py-16 text-center"><p class="text-sm font-semibold text-slate-900">{{ !$manager ? 'Select a management account first.' : (request()->filled('search') ? 'No services match your search.' : ($hasAdvancedFilters ? 'No services match your filters.' : 'No services configured yet.')) }}</p><p class="mt-2 text-sm text-gray-500">{{ !$manager ? 'Each account’s setup and reading history are kept separate.' : (request()->filled('search') || $hasAdvancedFilters ? 'Try another search or reset the filters to view more services.' : 'Initialize services to list your properties, units and rooms with empty readings.') }}</p></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($services->hasPages())<div class="bg-white px-6 py-4 border-t border-gray-100">{{ $services->links() }}</div>@endif
            </div>
            @if($manager)
                @canany(['super-admin', 'utilities.manage meters'])
                    <x-modals.confirmation-modal id="utility-delete" title="Delete Utility Service">
                        <div x-data="{ targetAction: '', serviceLabel: '' }" @open-utility-delete.window="targetAction = $event.detail.actionUrl; serviceLabel = $event.detail.label">
                            <x-form.form method="DELETE" x-bind:action="targetAction" :autofocus="false" class="p-6">
                                <x-form.text-input type="hidden" name="management_user_id" :value="$manager->id" />
                                <p class="text-sm text-gray-700">Delete <strong x-text="serviceLabel"></strong> from service setup?</p>
                                <p class="mt-2 text-sm text-gray-500">Existing readings and invoices will be kept. Initialization will not recreate this service. Services used as a parent must have their child connections removed first.</p>
                                <div class="mt-6 flex justify-end gap-2">
                                    <x-form.secondary-button type="button" @click="$dispatch('close-utility-delete')">Cancel</x-form.secondary-button>
                                    <x-form.primary-button type="submit" loading="loading" class="bg-red-600 hover:bg-red-700 focus:bg-red-700 focus:ring-red-500">Delete Service</x-form.primary-button>
                                </div>
                            </x-form.form>
                        </div>
                    </x-modals.confirmation-modal>
                @endcanany
            @endif
            <p class="mt-4 text-xs text-gray-500">Reading columns show the latest recorded history. Before the first record, Previous Reading shows the opening reading. Setup does not create readings, calculate charges or issue invoices. Supplier account numbers remain in the existing unit records.</p>
        </div>
    </div>
</x-app-layout>
