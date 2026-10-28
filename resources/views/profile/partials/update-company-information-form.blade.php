@php
    $company = $user->companies->first();
@endphp

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Company Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Update the company information connected to your account.') }}
        </p>
    </header>

    @if($company)
        <x-form.form
            method="patch"
            action="{{ url('/profile/company') }}"
            enctype="multipart/form-data"
            class="mt-6 space-y-6"
        >
            <div>
                <x-form.input-label for="company_logo" :value="__('Company Logo')" />
                <x-form.file-input
                    id="company_logo"
                    name="company_logo"
                    accept="image/png,image/jpeg,image/webp,image/svg+xml"
                    class="mt-1 block w-full"
                />
                <x-form.input-error class="mt-2" :messages="$errors->get('company_logo')" />

                @if($company->logo_path)
                    <p class="mt-2 text-sm text-gray-600">
                        {{ __('A company logo is currently uploaded.') }}
                    </p>
                @endif
            </div>

            <div>
                <x-form.input-label for="company_name" :value="__('Company Name')" />
                <x-form.text-input
                    id="company_name"
                    name="company_name"
                    type="text"
                    class="mt-1 block w-full"
                    :value="old('company_name', $company->name)"
                    required
                    autocomplete="organization"
                />
                <x-form.input-error class="mt-2" :messages="$errors->get('company_name')" />
            </div>

            <div>
                <x-form.input-label for="ssm_old_number" :value="__('SSM Old Number')" />
                <x-form.text-input
                    id="ssm_old_number"
                    name="ssm_old_number"
                    type="text"
                    class="mt-1 block w-full"
                    :value="old('ssm_old_number', $company->ssm_old_number)"
                />
                <x-form.input-error class="mt-2" :messages="$errors->get('ssm_old_number')" />
            </div>

            <div>
                <x-form.input-label for="ssm_new_number" :value="__('SSM New Number')" />
                <x-form.text-input
                    id="ssm_new_number"
                    name="ssm_new_number"
                    type="text"
                    class="mt-1 block w-full"
                    :value="old('ssm_new_number', $company->ssm_new_number)"
                />
                <x-form.input-error class="mt-2" :messages="$errors->get('ssm_new_number')" />
            </div>

            <div>
                <x-form.input-label for="company_address" :value="__('Company Address')" />
                <textarea
                    id="company_address"
                    name="company_address"
                    rows="4"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >{{ old('company_address', $company->address) }}</textarea>
                <x-form.input-error class="mt-2" :messages="$errors->get('company_address')" />
            </div>

            <div>
                <x-form.input-label for="company_phone" :value="__('Company Phone Number')" />
                <x-form.text-input
                    id="company_phone"
                    name="company_phone"
                    type="tel"
                    class="mt-1 block w-full"
                    :value="old('company_phone', $company->phone)"
                    autocomplete="tel"
                />
                <x-form.input-error class="mt-2" :messages="$errors->get('company_phone')" />
            </div>

            <div>
                <x-form.input-label for="company_email" :value="__('Company Email')" />
                <x-form.text-input
                    id="company_email"
                    name="company_email"
                    type="email"
                    class="mt-1 block w-full"
                    :value="old('company_email', $company->email)"
                    autocomplete="email"
                />
                <x-form.input-error class="mt-2" :messages="$errors->get('company_email')" />
            </div>

            <div class="flex items-center gap-4">
                <x-form.primary-button loading="loading">
                    {{ __('Save') }}
                </x-form.primary-button>

                @if (session('status') === 'company-updated')
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
    @else
        <div class="mt-6 rounded-xl border border-yellow-200 bg-yellow-50 p-6">
            <p class="font-medium text-yellow-700">
                {{ __('No company is currently linked to your account.') }}
            </p>
        </div>
    @endif
</section>
