<?php
namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class VoidInvoiceRequest extends FormRequest
{
    public function rememberModal(\App\Models\Invoice $invoice): void
    {
        session()->flash('failed_modal', [
            'id' => 'invoice-void',
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_no,
            'lease_id' => $invoice->lease_id,
        ]);
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $invoice = $this->route('invoice');
        if ($invoice instanceof \App\Models\Invoice) $this->rememberModal($invoice);
        session()->flash('error', $validator->errors()->first());
        parent::failedValidation($validator);
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }
}
