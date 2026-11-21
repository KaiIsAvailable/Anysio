@canany(['super-admin'])
@php
    $earlyPayment = app(\App\Services\SettingService::class)->getSystemEarlyPaymentSettings();
@endphp
<div x-data="{ editing: @js($errors->has('early_payment_config.*')), enabled: @js(filter_var(old('early_payment_config.is_active', $earlyPayment['is_active']), FILTER_VALIDATE_BOOLEAN)), number: @js(old('early_payment_config.number', $earlyPayment['value']['number'])), mode: @js(old('early_payment_config.mode', $earlyPayment['value']['mode'])) }">
<x-form.form method="PATCH" action="{{ route('admin.settings.update') }}" :autofocus="false"
    @pageshow.window="loading = false"
    class="bg-white border border-gray-200 rounded-lg p-4 sm:p-6 shadow-sm space-y-6">
    <input type="hidden" name="tab" value="system">
    <header class="flex items-center justify-between gap-4">
        <div>
            <h3 class="text-md font-medium text-gray-950">{{ __('Early Payment Notification Settings') }}</h3>
            <p class="mt-0.5 text-sm text-gray-600">{{ __('Set when all accounts are notified that they can make an advance payment for their subscription. Payment remains required at expiry.') }}</p>
        </div>
        <x-form.section-edit-button />
    </header>
    <div class="space-y-4 rounded-lg border border-gray-100 bg-gray-50 p-4">
        <div class="flex items-center justify-end">
            <input type="hidden" name="early_payment_config[is_active]" :value="enabled ? '1' : '0'">
            <button type="button" id="early-payment-enabled" role="switch"
                aria-label="{{ __('Early Payment Notifications') }}" :aria-checked="enabled"
                :disabled="!editing || loading" @click="enabled = !enabled"
                :class="enabled ? 'bg-indigo-600' : 'bg-gray-200'"
                class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
                <span :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
            </button>
        </div>
        <div>
            <x-form.input-label for="early-payment-number" :value="__('Early Payment Notice Period')" />
            <div class="flex items-center gap-2">
                <x-form.text-input id="early-payment-number" name="early_payment_config[number]" type="number"
                    min="1" max="365" required x-model="number" x-bind:disabled="!editing" />
                <div class="w-48 shrink-0">
                    <x-form.input-select id="early-payment-mode" name="early_payment_config[mode]"
                        :value="old('early_payment_config.mode', $earlyPayment['value']['mode'])"
                        :options="[['value' => 'days', 'label' => __('Days')], ['value' => 'months', 'label' => __('Months')], ['value' => 'years', 'label' => __('Years')]]"
                        @change="mode = $event.detail?.value ?? $event.target.value" disabledWhen="!editing" />
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-600"><span x-text="Number(number) * (mode === 'months' ? 30 : (mode === 'years' ? 365 : 1))"></span> {{ __('days before subscription expiry. Months use 30 days; years use 365 days.') }}</p>
        </div>
    </div>
    <div x-show="editing" x-cloak>
        <x-form.primary-button disabledWhen="!editing" loading="loading">{{ __('Save Early Payment Settings') }}</x-form.primary-button>
    </div>
</x-form.form>
</div>
@endcanany
