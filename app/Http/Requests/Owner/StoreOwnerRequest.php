<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class StoreOwnerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

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
        $isRandomEmail = $this->boolean('random_email');

        return [
            'name'         => ['required', 'string', 'max:255'],
            'email'        => $isRandomEmail ? ['nullable'] : ['required', 'email', 'unique:users,email'],
            'random_email' => ['required', 'boolean'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'ic_number'    => ['nullable', 'string', 'max:20'],
            'phone'        => ['required', 'string', 'max:20'],
            'gender'       => ['required', 'string', 'in:Male,Female'],
            'address'      => ['nullable', 'string'],
            'postcode'     => ['nullable', 'string', 'max:10'],
            'city'         => ['nullable', 'string', 'max:100'],
            'state'        => ['nullable', 'string', 'max:100'],
        ];
    }
}