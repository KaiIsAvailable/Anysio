<?php

namespace App\Http\Requests\Owner;

class StoreOwnerRequest extends OwnerDetailsRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Converts checkbox presence to true, and absence to false
        $this->merge([
            'random_email' => $this->has('random_email'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        if (!$this->canManageOwner()) {
            return [];
        }

        $isRandomEmail = $this->boolean('random_email');

        return $this->ownerDetailsRules() + [
            'email' => $isRandomEmail ? ['nullable'] : ['required', 'email', 'max:255', 'unique:users,email'],
            'random_email' => ['required', 'boolean'],
        ];
    }
}
