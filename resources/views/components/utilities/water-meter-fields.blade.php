@props(['location' => null])
@canany(['super-admin', 'utilities.manage meters'])
    @php
        $meter = $location ? app(\App\Services\UtilitySetupService::class)->waterMeterData($location) : [];
        $values = old('water_meter', $meter);
        $sharedService = $location?->utilityServices->firstWhere('type', 'water');
        $advanced = $sharedService && $sharedService->billing_method !== 'metered';
    @endphp
    @if($advanced)
        <p class="text-sm text-gray-500">This location has a shared or fixed water service. Manage its setup in Utilities.</p>
    @else
    <section class="rounded-lg border border-gray-200 p-4" x-data="{ hasWaterMeter: @js((string) ($values['has_meter'] ?? '0')) }">
        <h3 class="mb-4 text-base font-semibold text-slate-900">Water Meter</h3>
        <div class="space-y-4">
            <div>
                <x-form.input-label for="water-has-meter" value="Has physical water meter?" />
                <x-form.input-select id="water-has-meter" name="water_meter[has_meter]" :options="['0' => 'No', '1' => 'Yes']" :value="$values['has_meter'] ?? '0'"
                    @change="if ($event.detail?.value !== undefined) hasWaterMeter = String($event.detail.value)" />
                <x-form.input-error :messages="$errors->get('water_meter.has_meter')" />
            </div>
            <div x-cloak x-show="hasWaterMeter === '1'" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <x-form.input-label for="water-meter-number" value="Physical Meter Number" info="The number on the meter device, not the water supplier account number. Leave blank if unknown." />
                    <x-form.text-input id="water-meter-number" name="water_meter[meter_number]" :value="$values['meter_number'] ?? ''" maxlength="255" />
                    <x-form.input-error :messages="$errors->get('water_meter.meter_number')" />
                </div>
                <div>
                    <x-form.input-label for="water-opening-reading" value="Opening Reading (m³)" info="The initial meter value used as the baseline. Zero is valid. Leave both opening fields blank if unknown." />
                    <x-form.text-input id="water-opening-reading" name="water_meter[opening_reading]" type="number" inputmode="decimal" min="0" step="0.001" :value="$values['opening_reading'] ?? ''" />
                    <x-form.input-error :messages="$errors->get('water_meter.opening_reading')" />
                </div>
                <div>
                    <x-form.input-label for="water-opening-date" value="Opening Reading Date" />
                    <x-form.date-input id="water-opening-date" name="water_meter[opening_reading_date]" :value="$values['opening_reading_date'] ?? ''" :max-date="today()->toDateString()" :disable-mobile="true" />
                    <x-form.input-error :messages="$errors->get('water_meter.opening_reading_date')" />
                </div>
            </div>
            <x-form.input-error :messages="$errors->get('water_meter')" />
        </div>
    </section>
    @endif
@endcanany
