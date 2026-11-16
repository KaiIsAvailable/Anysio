<x-app-layout>
    @php($backToReadings = ($returnTo ?? 'index') === 'readings')
    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <a href="{{ $backToReadings ? route('admin.utilities.readings', ['management_user_id' => $manager->id, 'month' => $returnMonth]) : route('admin.utilities.index', ['management_user_id' => $manager->id, 'type' => $service->type]) }}" class="inline-flex items-center text-indigo-600 hover:text-indigo-900 text-sm font-medium transition-colors">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                {{ $backToReadings ? 'Back to Water Readings' : 'Back to Utilities' }}
            </a>
            <h1 class="mt-5 text-3xl font-bold text-slate-900">Configure Utility Service</h1>
            <p class="mt-2 mb-6 text-sm text-gray-500">{{ $manager->name }} · {{ $service->location_label }} · {{ \Illuminate\Support\Str::headline($service->type) }}</p>
            <x-form.form method="PATCH" action="{{ route('admin.utilities.update', $service) }}" class="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-lg">
                <x-form.text-input type="hidden" name="management_user_id" :value="$manager->id" />
                @if($backToReadings)
                    <x-form.text-input type="hidden" name="return_to" value="readings" />
                    <x-form.text-input type="hidden" name="month" :value="$returnMonth" />
                @endif
                <div>
                    <x-form.input-label for="utility-label" value="Service label" :required="true" class="mb-2" />
                    <x-form.text-input id="utility-label" name="label" required maxlength="255" :value="old('label', $service->label)" />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-form.input-label for="utility-billing-method" value="Billing method" :required="true" class="mb-2" />
                        <x-form.input-select id="utility-billing-method" name="billing_method" :options="collect(\App\Models\UtilityService::METHODS)->mapWithKeys(fn ($method) => [$method => ucfirst($method)])" :value="old('billing_method', $service->billing_method)" />
                    </div>
                    <div>
                        <x-form.input-label for="utility-status" value="Status" :required="true" class="mb-2" />
                        <x-form.input-select id="utility-status" name="status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="old('status', $service->status === 'unconfirmed' ? 'active' : $service->status)" />
                    </div>
                </div>
                <div>
                    <x-form.input-label value="Charge scope" class="mb-2" :info="__('An individual meter bills one lease. A shared source meter requires approved tenant and vacancy allocations before invoicing.')" />
                    <x-form.input-select name="billing_scope" :value="old('billing_scope', $service->billing_scope)" :options="['individual'=>'Individual lease', 'shared'=>'Shared source meter']" />
                </div>
                <div>
                    <x-form.input-label for="utility-meter-number" value="Physical meter number (optional)" class="mb-2" />
                    <x-form.text-input id="utility-meter-number" name="meter_number" maxlength="255" :value="old('meter_number', $service->meter_number)" />
                </div>
                <div>
                    <x-form.input-label for="utility-parent-service" value="Parent / shared service" class="mb-2" />
                    <x-form.input-select id="utility-parent-service" name="parent_service_id" :options="['' => 'None / not configured'] + $parents->mapWithKeys(fn ($parent) => [$parent->id => $parent->label.' — '.$parent->location_label.' ('.$parent->status.')'])->all()" :value="old('parent_service_id', $service->parent_service_id)" placeholder="None / not configured" />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-form.input-label for="utility-opening-reading" value="Opening reading" class="mb-2" />
                        <x-form.text-input id="utility-opening-reading" type="number" name="opening_reading" min="0" max="999999999999.999" step="0.001" :value="old('opening_reading', $service->opening_reading)" />
                    </div>
                    <div>
                        <x-form.input-label for="utility-opening-date" value="Opening reading date" class="mb-2" />
                        <x-form.date-input id="utility-opening-date" name="opening_reading_date" :value="old('opening_reading_date', $service->opening_reading_date?->format('Y-m-d'))" />
                    </div>
                </div>
                @if($backToReadings && $readingRow)
                    <div>
                        <x-form.input-label for="utility-previous-reading-date" :value="'Previous reading date for '.\Illuminate\Support\Carbon::parse($returnMonth.'-01')->format('m/Y')" class="mb-2" />
                        @if($readingRow['locked'] || $readingRow['baselineDate'])
                            <p class="text-sm text-gray-500">{{ $readingRow['previousDate'] ? \Illuminate\Support\Carbon::parse($readingRow['previousDate'])->format('d/m/Y') : 'Not set' }} ? Recorded baseline; preserve this measured date.</p>
                        @else
                            <x-form.text-input type="hidden" name="reading_revision" :value="old('reading_revision', $readingRow['revision'])" />
                            <x-form.date-input id="utility-previous-reading-date" name="previous_reading_date" :value="old('previous_reading_date', $readingRow['previousDate'])" :max-date="today()->toDateString()" />
                            <p class="mt-1 text-xs text-gray-500">Updates this billing month's previous date. Earlier meter readings remain unchanged.</p>
                        @endif
                    </div>
                @endif
                <p class="text-xs text-gray-500">Saving this setup marks the service as active unless you select Inactive. Opening readings and dates can be entered now or during reading entry. Shared services need an active parent meter. Leave readings empty for fixed or shared services. Deactivate services that do not apply; existing history is preserved.</p>
                <div class="flex justify-end border-t border-gray-100 pt-5"><x-form.primary-button loading="loading" type="submit">Save Service</x-form.primary-button></div>
            </x-form.form>
            @if($service->billing_method === 'metered')
                <details class="mt-6 bg-white rounded-xl border p-6"><summary class="font-semibold cursor-pointer">Meter replacement, reset, rollover or correction</summary>
                    <p class="my-3 text-sm text-gray-600">Record the physical event before entering its affected reading. This preserves earlier readings and invoices. A replacement uses old closing − previous + current − new opening. Rollover uses capacity − previous + current.</p>
                    <x-form.form :action="route('admin.utilities.events.store', $service)" :autofocus="false" class="space-y-4">
                        <x-form.text-input type="hidden" name="management_user_id" :value="$manager->id" />
                        <x-form.input-label value="Event type" /><x-form.input-select name="type" :value="old('type', 'replacement')" :options="['replacement'=>'Replacement','reset'=>'Reset','rollover'=>'Rollover','correction'=>'Documented reading correction']" />
                        <x-form.input-label value="Event date" /><x-form.date-input id="meter-event-date" name="event_date" :value="old('event_date')" :max-date="today()->toDateString()" required />
                        <x-form.input-label value="Old meter closing reading (except rollover)" /><x-form.text-input name="old_closing" :value="old('old_closing')" type="number" min="0" step="0.001" />
                        <x-form.input-label value="New meter opening reading (except rollover)" /><x-form.text-input name="new_opening" :value="old('new_opening')" type="number" min="0" step="0.001" />
                        <x-form.input-label value="Meter capacity (rollover only)" /><x-form.text-input name="capacity" :value="old('capacity')" type="number" min="0" step="0.001" />
                        <x-form.input-label value="New physical meter serial (optional)" /><x-form.text-input name="new_meter_number" :value="old('new_meter_number')" />
                        <x-form.input-label value="Reason / evidence" /><x-form.text-input name="reason" :value="old('reason')" required minlength="5" />
                        <div class="flex justify-end"><x-form.primary-button loading="loading">Record Meter Event</x-form.primary-button></div>
                    </x-form.form>
                </details>
            @endif
        </div>
    </div>
</x-app-layout>
