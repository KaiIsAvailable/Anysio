<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOwnerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // $this->route('owner') gets the current owner model from the route parameter
        $owner = $this->route('owner');
        $userId = $owner ? $owner->user_id : null;

        return [
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'unique:users,email,' . $userId],
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