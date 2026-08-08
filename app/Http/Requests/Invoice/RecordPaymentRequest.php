<?php
namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
{
   public function rules(): array
    {
        return [
            'amount_paid'     => ['required', 'numeric', 'min:0.01'],
            'payment_method'  => ['required', 'string'],
            'payment_date'    => ['required', 'date', 'before_or_equal:today'],
            'transaction_ref' => ['nullable', 'string', 'max:100'],
            'receipt_no'      => ['nullable', 'string', 'max:100'],
            'remark'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}