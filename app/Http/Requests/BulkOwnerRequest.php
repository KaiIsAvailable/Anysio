<?php

namespace App\Http\Requests;

class BulkOwnerRequest extends BulkEditRequest
{
    public function rules(): array
    {
        $rules = $this->envelopeRules();
        $rules['rows.*.fileFields.*'] = ['in:companyLogo,ssmCertificate', 'distinct'];
        $rules['uploads.*'] = ['array:companyLogo,ssmCertificate'];
        foreach ((array) $this->input('rows', []) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $prefix = "rows.{$index}.";
            if (($row['action'] ?? '') !== 'create') {
                $rules[$prefix.'id'] = ['required', 'ulid'];
                $rules[$prefix.'version'] = ['required', 'string', 'size:64'];
            }
            $uploadKey = $row['uploadKey'] ?? '';
            if (is_string($uploadKey) && preg_match('/^[A-Za-z0-9-]{1,64}$/', $uploadKey)) {
                foreach (['companyLogo', 'ssmCertificate'] as $field) {
                    $rules["uploads.{$uploadKey}.{$field}"] = ($row['action'] ?? '') === 'remove' ? ['prohibited']
                        : array_merge(in_array($field, (array) ($row['fileFields'] ?? []), true) ? ['required'] : ['nullable'], $rules["uploads.*.{$field}"]);
                }
            } elseif (! empty($row['fileFields'])) {
                $rules[$prefix.'uploadKey'] = ['required', 'string', 'regex:/^[A-Za-z0-9-]{1,64}$/'];
            }
            if (($row['action'] ?? '') === 'remove' || ! $this->validateRowDetails) {
                continue;
            }
            $rules += [
                $prefix.'name' => ['required', 'string', 'max:255'],
                $prefix.'email' => ['nullable', 'email', 'max:255'],
                $prefix.'phone' => ['required', 'string', 'regex:/^[0-9]{1,20}$/'],
                $prefix.'ic' => ['nullable', 'string', 'max:20'],
                $prefix.'gender' => ['required', 'in:Male,Female'],
                $prefix.'address' => ['nullable', 'string'],
                $prefix.'postcode' => ['nullable', 'string', 'max:10'],
                $prefix.'city' => ['nullable', 'string', 'max:100'],
                $prefix.'state' => ['nullable', 'string', 'max:100'],
                $prefix.'companyName' => ['nullable', 'string', 'max:255'],
                $prefix.'ssmOld' => ['nullable', 'string', 'max:255'],
                $prefix.'ssmNew' => ['nullable', 'string', 'max:50'],
                $prefix.'companyAddress' => ['nullable', 'string', 'max:500'],
                $prefix.'companyPhone' => ['nullable', 'string', 'regex:/^[0-9]{1,20}$/'],
                $prefix.'companyEmail' => ['nullable', 'email', 'max:255'],
                $prefix.'bankName' => ['nullable', 'string', 'max:255'],
                $prefix.'bankCode' => ['nullable', 'string', 'max:100'],
                $prefix.'accountHolder' => ['nullable', 'string', 'max:255'],
                $prefix.'accountNumber' => ['nullable', 'string', 'regex:/^[0-9]{1,50}$/'],
            ];
        }

        return $rules;
    }
}
