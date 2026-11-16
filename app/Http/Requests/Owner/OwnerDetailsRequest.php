<?php

namespace App\Http\Requests\Owner;

use App\Services\BankAccountService;
use App\Services\OwnerIdentityValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

abstract class OwnerDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function canManageOwner(): bool
    {
        $owner = $this->route('owner');
        $action = $owner ? 'owner.edit' : 'owner.create';

        return Gate::allows('super-admin') || (Gate::allows($action)
            && (!$owner || ($owner->agent_id !== null && $owner->agent_id === get_effective_user()?->id)));
    }

    protected function ownerDetailsRules(): array
    {
        $owner = $this->route('owner');
        $hasCompanyInput = $this->anyFilled([
            'company_name', 'ssm_old_number', 'ssm_new_number',
            'company_address', 'company_phone', 'company_email',
        ]) || $this->hasFile('company_logo') || $this->hasFile('ssm_cert_path');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', 'in:Male,Female'],
            'address' => ['nullable', 'string'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'company_name' => [$hasCompanyInput ? 'required' : 'nullable', 'string', 'max:255'],
            'ssm_old_number' => ['nullable', 'string', 'max:255'],
            'ssm_new_number' => ['nullable', 'string', 'max:50', Rule::unique('companies', 'ssm_new_number')->ignore($owner?->user?->companies()->first()?->id)],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_phone' => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_logo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'ssm_cert_path' => ['nullable', 'file', 'mimes:jpeg,png,gif,pdf', 'max:2048'],
        ];

        $hasBankInput = $this->anyFilled(['bank_name', 'bank_code', 'account_holder_name', 'account_number']);
        $bank = $owner?->user ? app(BankAccountService::class)->getDefaultForUser($owner->user) : null;
        $rules += [
            'bank_name' => [$hasBankInput ? 'required' : 'nullable', 'string', 'max:255'],
            'bank_code' => ['nullable', 'string', 'max:100'],
            'account_holder_name' => [$hasBankInput ? 'required' : 'nullable', 'string', 'max:255'],
            'account_number' => [$hasBankInput && !$bank ? 'required' : 'nullable', 'string', 'regex:/^[0-9]+$/', 'max:50'],
        ];

        return $rules + app(OwnerIdentityValidator::class)->rules($owner, get_effective_user()?->id);
    }
}
