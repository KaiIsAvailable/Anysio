<?php

namespace App\Http\Requests;

class BulkTenantRequest extends BulkEditRequest
{
    public function rules(): array
    {
        $rules = $this->envelopeRules();
        foreach ((array) $this->input('rows', []) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $prefix = "rows.{$index}.";
            $uploadKey = $row['uploadKey'] ?? '';
            if (is_string($uploadKey) && preg_match('/^[A-Za-z0-9-]{1,64}$/', $uploadKey)) {
                foreach (['icPhoto', 'companyLogo', 'ssmCertificate'] as $field) {
                    $allowed = ($row['action'] ?? '') !== 'remove' && (($row['type'] ?? '') === 'corporate' ? $field !== 'icPhoto' : $field === 'icPhoto');
                    $rules["uploads.{$uploadKey}.{$field}"] = $allowed
                        ? array_merge(in_array($field, (array) ($row['fileFields'] ?? []), true) ? ['required'] : ['nullable'], $rules["uploads.*.{$field}"])
                        : ['prohibited'];
                }
            } elseif (! empty($row['fileFields'])) {
                $rules[$prefix.'uploadKey'] = ['required', 'string', 'regex:/^[A-Za-z0-9-]{1,64}$/'];
            }
            if (($row['action'] ?? '') !== 'create') {
                $rules[$prefix.'id'] = ['required', 'ulid'];
                $rules[$prefix.'version'] = ['required', 'string', 'size:64'];
            }
            if (($row['action'] ?? '') === 'remove') {
                continue;
            }
            if (! $this->validateRowDetails) {
                continue;
            }
            $rules += [
                $prefix.'name' => ['required', 'string', 'max:255'],
                $prefix.'email' => ['nullable', 'email', 'max:255'],
                $prefix.'type' => ['required', 'in:individual,corporate'],
                $prefix.'phone' => ['required', 'regex:/^[0-9]{1,20}$/'],
                $prefix.'contacts' => ['required', 'array', 'min:1', 'max:10'],
                $prefix.'contacts.*.name' => ['required', 'string', 'max:255'],
                $prefix.'contacts.*.phone' => ['required', 'regex:/^[0-9]{1,20}$/', 'different:'.$prefix.'phone'],
                $prefix.'contacts.*.relationship' => ['required', 'string', 'max:255'],
            ];
            if (($row['type'] ?? '') === 'corporate') {
                $rules += [
                    $prefix.'companyName' => ['required', 'string', 'max:255'],
                    $prefix.'ssmNew' => ['required', 'string', 'max:50'],
                    $prefix.'ssmOld' => ['nullable', 'string', 'max:255'],
                    $prefix.'companyAddress' => ['required', 'string', 'max:500'],
                    $prefix.'companyPhone' => ['nullable', 'regex:/^[0-9]{1,20}$/'],
                    $prefix.'companyEmail' => ['nullable', 'email', 'max:255'],
                ];
            } else {
                $rules += [
                    $prefix.'ic' => ['nullable', 'required_without:'.$prefix.'passport', 'prohibits:'.$prefix.'passport', 'regex:/^[0-9]{12}$/'],
                    $prefix.'passport' => ['nullable', 'required_without:'.$prefix.'ic', 'prohibits:'.$prefix.'ic', 'regex:/^[A-Z0-9]{1,30}$/'],
                    $prefix.'nationality' => filled($row['ic'] ?? null) ? ['required', 'in:MALAYSIAN'] : ['required', 'string', 'max:100'],
                    $prefix.'gender' => ['required', 'in:Male,Female'],
                    $prefix.'occupation' => ['nullable', 'string', 'max:100'],
                ];
            }
            foreach (['bankName' => 255, 'bankCode' => 100, 'accountHolder' => 255] as $field => $length) {
                $rules[$prefix.$field] = ['nullable', 'string', 'max:'.$length];
            }
            $rules[$prefix.'accountNumber'] = ['nullable', 'regex:/^[0-9]{1,50}$/'];
        }

        return $rules;
    }
}
