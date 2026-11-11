<?php
namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $message = $validator->errors()->first();
        session()->forget(['success', 'status']);
        session()->flash('error', $message);
        if ($this->expectsJson()) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
                'success' => false, 'message' => $message, 'errors' => $validator->errors()->messages(),
                'session_status' => ['type' => 'error', 'message' => session('error')],
            ], 422));
        }
        parent::failedValidation($validator);
    }

   public function rules(): array
    {
        $walletOnly = is_string($this->input('payment_method')) && strtolower(trim($this->input('payment_method'))) === 'wallet';

        return [
            'amount_paid'     => ['required', 'numeric', 'min:0', Rule::when($walletOnly, ['in:0'], ['gt:0'])],
            'use_wallet'      => ['nullable', 'boolean', Rule::when($walletOnly, ['required', 'accepted'])],
            'payment_method'  => ['required', 'string'],
            'processing_fee_amount' => ['sometimes', 'required', 'numeric', 'min:0', 'decimal:0,2'],
            'processing_fee_cancelled' => ['sometimes', 'required', 'boolean'],
            'payment_date'    => ['required', 'date', 'before_or_equal:today'],
            'transaction_ref' => ['nullable', 'string', 'max:100', Rule::prohibitedIf($walletOnly)],
            'receipt_no'      => ['nullable', 'string', 'max:100'],
            'remark'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
