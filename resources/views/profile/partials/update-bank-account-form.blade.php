<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Bank Account Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Add the bank account that should be used for payments or refunds.') }}
        </p>
    </header>

    <x-form.form
        method="patch"
        action="{{ route('profile.bank-account.update') }}"
        class="mt-6 space-y-6"
    >
        <div>
            <x-form.input-label for="bank_name" :value="__('Bank Name')" />
            <x-form.text-input
                id="bank_name"
                name="bank_name"
                type="text"
                class="mt-1 block w-full"
                :value="old('bank_name', $bankDetail?->bank_name)"
                required
                autocomplete="organization"
            />
            <x-form.input-error class="mt-2" :messages="$errors->get('bank_name')" />
        </div>

        <div>
            <x-form.input-label for="bank_code" :value="__('Bank Code')" />
            <x-form.text-input
                id="bank_code"
                name="bank_code"
                type="text"
                class="mt-1 block w-full"
                :value="old('bank_code', $bankDetail?->bank_code)"
            />
            <x-form.input-error class="mt-2" :messages="$errors->get('bank_code')" />
        </div>

        <div>
            <x-form.input-label for="account_holder_name" :value="__('Account Holder Name')" />
            <x-form.text-input
                id="account_holder_name"
                name="account_holder_name"
                type="text"
                class="mt-1 block w-full"
                :value="old('account_holder_name', $bankDetail?->account_holder_name)"
                required
                autocomplete="name"
            />
            <x-form.input-error class="mt-2" :messages="$errors->get('account_holder_name')" />
        </div>

        <div>
            <x-form.input-label for="account_number" :value="__('Account Number')" />
            <x-form.text-input
                id="account_number"
                name="account_number"
                type="text"
                class="mt-1 block w-full"
                value="{{ old('account_number') }}"
                placeholder="{{ $bankDetail ? __('Leave blank to keep the current account number') : '' }}"
                autocomplete="off"
            />
            @if($bankDetail?->account_number)
                <p class="mt-2 text-sm text-gray-600">
                    {{ __('Current account:') }} ******{{ substr($bankDetail->account_number, -4) }}
                </p>
            @endif
            <x-form.input-error class="mt-2" :messages="$errors->get('account_number')" />
        </div>

        <div>
            <label for="is_default" class="inline-flex items-center">
                <input
                    id="is_default"
                    name="is_default"
                    type="checkbox"
                    value="1"
                    @checked(old('is_default', true))
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                >
                <span class="ms-2 text-sm text-gray-600">{{ __('Use as default account') }}</span>
            </label>
            <x-form.input-error class="mt-2" :messages="$errors->get('is_default')" />
        </div>

        <div class="flex items-center gap-4">
            <x-form.primary-button loading="loading">
                {{ __('Save') }}
            </x-form.primary-button>

            @if (session('status') === 'bank-account-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </x-form.form>
</section>
