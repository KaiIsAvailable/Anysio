<?php

namespace App\Http\Requests\Owner;

use Illuminate\Validation\Rule;

class UpdateOwnerRequest extends OwnerDetailsRequest
{
    public function rules(): array
    {
        if (!$this->canManageOwner()) {
            return [];
        }

        return $this->ownerDetailsRules() + [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('owner')?->user_id)],
        ];
    }
}
