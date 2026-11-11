<?php
namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'period'              => ['required', 'date'],
            'due_date'            => ['required', 'date', 'after_or_equal:period'],
            'remarks'             => ['nullable', 'string', 'max:500'],
            'post_refund_adjustment' => ['sometimes', 'boolean'],
            'adjustment_reason' => ['required_if:post_refund_adjustment,1', 'nullable', 'string', 'max:1000'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.fee_type_id' => ['required', 'ulid', 'exists:fee_types,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.amount'      => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
