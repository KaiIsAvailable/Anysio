<?php

namespace App\Http\Requests\Lease;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use App\Models\Lease;

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
                Rule::in(['New', 'Renew', 'Check Out']),
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
                'exists:properties,id',
            ],

            'unit_id' => [
                'required_if:lease_selection,unit',
                'nullable',
                'exists:units,id',
            ],

            'room_id' => [
                'required_if:lease_selection,room',
                'nullable',
                'exists:rooms,id',
            ],

            'tenant_id' => [
                'required_if:status,New',
                'nullable',
                'exists:tenants,id',
            ],

            'start_date' => [
                Rule::requiredIf(in_array($this->input('status'), ['New', 'Renew'])),
                'nullable',
                'date',
                function ($attribute, $value, $fail) {
                    if ($this->input('status') === 'Renew' && $this->input('lease_id')) {
                        $previousLease = Lease::find($this->input('lease_id'));
                        
                        if ($previousLease && $previousLease->end_date) {
                            // Use $value (the actual submitted date string, e.g., "2026-10-01")
                            $startDate = Carbon::parse($value);
                            $endDate = Carbon::parse($previousLease->end_date);

                            if ($startDate->lessThanOrEqualTo($endDate)) {
                                $fail("The start date must be after the previous lease's end date ({$previousLease->end_date}).");
                            }
                        }
                    }
                },
            ],

            'end_date' => [
                Rule::requiredIf(in_array($this->input('status'), ['New', 'Renew'])),
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

            // Dynamic Charges Validation Rules
            'charges' => [
                Rule::requiredIf(in_array($this->input('status'), ['New', 'Renew'])),
                'nullable',
                'array',
                'min:1',
            ],
            'charges.*.fee_type_id' => [
                'required_with:charges',
                'exists:fee_types,id',
            ],
            'charges.*.amount' => [
                'required_with:charges',
                'numeric',
                'min:0',
            ],

            'document_id' => [
                Rule::requiredIf(in_array($this->input('status'), ['New', 'Renew'])),
                'nullable',
                'exists:document_templates,id',
            ],
        ];
    }
}