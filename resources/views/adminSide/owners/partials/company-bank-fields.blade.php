@php
    $company = $company ?? null;
    $bankDetail = $bankDetail ?? null;
@endphp

<section class="pt-6 border-t border-gray-100" aria-labelledby="company-heading">
    <h2 id="company-heading" class="text-lg font-semibold text-slate-900">Company Information <span class="text-sm font-normal text-gray-500">(Optional)</span></h2>
    <p class="mt-1 mb-4 text-sm text-gray-500">Leave empty if this owner has no company. Enter a company name when adding company details.</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <x-form.input-label for="company_name" value="Company Name" class="mb-1" />
            <x-form.text-input type="text" name="company_name" id="company_name" :value="old('company_name', $company?->name)" class="w-full" />
            <x-form.input-error :messages="$errors->get('company_name')" class="mt-1" />
        </div>
        <div>
            <x-form.input-label for="ssm_old_number" value="SSM Number (Old)" class="mb-1" />
            <x-form.text-input type="text" name="ssm_old_number" id="ssm_old_number" :value="old('ssm_old_number', $company?->ssm_old_number)" class="w-full" />
            <x-form.input-error :messages="$errors->get('ssm_old_number')" class="mt-1" />
        </div>
        <div>
            <x-form.input-label for="ssm_new_number" value="SSM Number (New)" class="mb-1" />
            <x-form.text-input type="text" name="ssm_new_number" id="ssm_new_number" :value="old('ssm_new_number', $company?->ssm_new_number)" class="w-full" />
            <x-form.input-error :messages="$errors->get('ssm_new_number')" class="mt-1" />
        </div>
        <div>
            <x-form.input-label for="company_address" value="Company Address" class="mb-1" />
            <x-form.text-input type="text" name="company_address" id="company_address" :value="old('company_address', $company?->address)" class="w-full" />
            <x-form.input-error :messages="$errors->get('company_address')" class="mt-1" />
        </div>
        <div>
            <x-form.input-label for="company_phone" value="Company Phone" class="mb-1" />
            <x-form.text-input type="tel" name="company_phone" id="company_phone" :value="old('company_phone', $company?->phone)" class="w-full" />
            <x-form.input-error :messages="$errors->get('company_phone')" class="mt-1" />
        </div>
        <div>
            <x-form.input-label for="company_email" value="Company Email" class="mb-1" />
            <x-form.text-input type="email" name="company_email" id="company_email" :value="old('company_email', $company?->email)" class="w-full" />
            <x-form.input-error :messages="$errors->get('company_email')" class="mt-1" />
        </div>
        <div>
            <x-form.input-label for="company_logo" value="Company Logo" class="mb-1" />
            <x-form.file-input name="company_logo" id="company_logo" accept="image/jpeg,image/png,image/webp" />
            <p class="mt-1 text-xs text-gray-500">JPEG, PNG or WebP, up to 2 MB. Leave empty to keep the current logo.</p>
            <x-form.input-error :messages="$errors->get('company_logo')" class="mt-1" />
            @if($company?->logo_path)
                <img src="{{ asset('storage/'.$company->logo_path) }}" alt="Current company logo" class="mt-2 h-20 w-20 object-contain" />
            @endif
        </div>
        <div>
            <x-form.input-label for="ssm_cert_path" value="SSM Certificate" class="mb-1" />
            <x-form.file-input name="ssm_cert_path" id="ssm_cert_path" accept="image/jpeg,image/png,image/gif,application/pdf" />
            <p class="mt-1 text-xs text-gray-500">JPEG, PNG, GIF or PDF, up to 2 MB. Leave empty to keep the current certificate.</p>
            <x-form.input-error :messages="$errors->get('ssm_cert_path')" class="mt-1" />
            @if($company?->ssm_cert_path && isset($owner))
                <a href="{{ route('admin.owners.company-certificate', $owner) }}" target="_blank" rel="noopener" class="mt-2 inline-block text-sm text-indigo-600 underline">View current SSM certificate</a>
            @endif
        </div>
    </div>
</section>

<section class="pt-6 border-t border-gray-100" aria-labelledby="bank-heading">
    <h2 id="bank-heading" class="text-lg font-semibold text-slate-900">Bank Account <span class="text-sm font-normal text-gray-500">(Optional)</span></h2>
    <p class="mt-1 mb-4 text-sm text-gray-500">When adding an account, enter the bank name, account holder and account number.</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <x-form.input-label for="bank_name" value="Bank Name" class="mb-1" />
            <x-form.text-input type="text" name="bank_name" id="bank_name" :value="old('bank_name', $bankDetail?->bank_name)" class="w-full" />
            <x-form.input-error :messages="$errors->get('bank_name')" class="mt-1" />
        </div>
        <div>
            <x-form.input-label for="bank_code" value="Bank Code" class="mb-1" />
            <x-form.text-input type="text" name="bank_code" id="bank_code" :value="old('bank_code', $bankDetail?->bank_code)" class="w-full" />
            <x-form.input-error :messages="$errors->get('bank_code')" class="mt-1" />
        </div>
        <div>
            <x-form.input-label for="account_holder_name" value="Account Holder Name" class="mb-1" />
            <x-form.text-input type="text" name="account_holder_name" id="account_holder_name" :value="old('account_holder_name', $bankDetail?->account_holder_name)" class="w-full" />
            <x-form.input-error :messages="$errors->get('account_holder_name')" class="mt-1" />
        </div>
        <div>
            <x-form.input-label for="account_number" value="Account Number" class="mb-1" />
            <x-form.text-input type="text" name="account_number" id="account_number" :value="old('account_number')" class="w-full" autocomplete="off" inputmode="numeric" pattern="[0-9]*" maxlength="50" />
            <x-form.input-error :messages="$errors->get('account_number')" class="mt-1" />
            @if($bankDetail?->account_number)
                <p class="mt-1 text-xs text-gray-500">Current account: ******{{ substr($bankDetail->account_number, -4) }}. Leave empty to keep this number.</p>
            @endif
        </div>
    </div>
</section>
