<?php

namespace App\Http\Requests\Lease;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(['New', 'Renew', 'Check Out', 'End Agreement']),
            ],

            'lease_id' => [
                'required_unless:status,New',
                'nullable',
                'exists:leases,id',
            ],

            'lease_selection' => [
                'required_if:status,New',
                'nullable',
                Rule::in(['property', 'unit', 'room']),
            ],

            'property_id' => [
                'required_if:lease_selection,property',
                'nullable',
            ],

            'unit_id' => [
                'required_if:lease_selection,unit',
                'nullable',
            ],

            'room_id' => [
                'required_if:lease_selection,room',
                'nullable',
            ],

            'tenant_id' => [
                'required_if:status,New',
                'nullable',
                'exists:tenants,id',
            ],

            'start_date' => [
                'required_if:status,New,Renew',
                'nullable',
                'date',
            ],

            'end_date' => [
                'required_if:status,New,Renew',
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],

            'checked_out_at' => [
                'required_if:status,Check Out',
                'nullable',
                'date',
            ],

            'agreement_ended_at' => [
                'required_if:status,End Agreement',
                'nullable',
                'date',
            ],

            'rent_price' => [
                'required_if:status,New,Renew',
                'nullable',
                'numeric',
                'min:1',
            ],

            'term_type' => [
                'required_if:status,New,Renew',
                'nullable',
                'string',
            ],

            'security_deposit' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'utilities_deposit' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'document_id' => [
                'required_if:status,New,Renew',
                'nullable',
                'exists:document_templates,id',
            ],
        ];
    }
}