<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class BulkEditRequest extends FormRequest
{
    public bool $validateRowDetails = false;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $json = $this->input('rows_json');
        $rows = is_string($json) && strlen($json) <= 3145728 ? json_decode($json, true) : null;
        if (is_array($rows)) {
            if (count($rows) > 300) {
                throw \Illuminate\Validation\ValidationException::withMessages(['rows' => 'You may save up to 300 rows at a time.']);
            }
            foreach ($rows as &$row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach ($row as &$value) {
                    if (is_string($value)) {
                        $value = trim($value);
                    }
                }
                unset($value);
                if (is_array($row['contacts'] ?? null)) {
                    $row['contacts'] = array_values(array_filter(array_map(function ($contact) {
                        if (! is_array($contact)) {
                            return $contact;
                        }

                        return array_map(fn ($value) => is_string($value) ? trim($value) : $value, $contact);
                    }, $row['contacts']), fn ($contact) => ! is_array($contact) || collect($contact)->contains(fn ($value) => filled($value))));
                    $row['contacts'] = array_slice($row['contacts'], 0, 11);
                }
            }
            unset($row);
        }
        $this->merge(['rows' => $rows]);
    }

    protected function envelopeRules(): array
    {
        return [
            'rows_json' => ['required', 'string', 'max:3145728'],
            'request_key' => ['nullable', 'uuid'],
            'page' => ['required', 'integer', 'min:1'],
            'rows' => ['required', 'array', 'min:1', 'max:300'],
            'rows.*' => ['required', 'array'],
            'rows.*.action' => ['required', 'in:create,update,remove'],
            'rows.*.id' => ['nullable', 'ulid', 'distinct'],
            'rows.*.version' => ['nullable', 'string', 'size:64'],
            'rows.*.rowNumber' => ['required', 'integer', 'min:1'],
            'rows.*.uploadKey' => ['nullable', 'string', 'regex:/^[A-Za-z0-9-]{1,64}$/', 'distinct'],
            'rows.*.fileFields' => ['nullable', 'array', 'max:3'],
            'rows.*.fileFields.*' => ['in:icPhoto,companyLogo,ssmCertificate', 'distinct'],
            'uploads' => ['nullable', 'array', 'max:300'],
            'uploads.*' => ['array:icPhoto,companyLogo,ssmCertificate'],
            'uploads.*.icPhoto' => ['nullable', 'file', 'max:2048', 'mimes:jpeg,png,jpg,gif,pdf', 'mimetypes:image/jpeg,image/png,image/gif,application/pdf'],
            'uploads.*.companyLogo' => ['nullable', 'image', 'max:2048', 'mimes:jpeg,png,webp'],
            'uploads.*.ssmCertificate' => ['nullable', 'file', 'max:2048', 'mimes:jpeg,png,jpg,gif,pdf', 'mimetypes:image/jpeg,image/png,image/gif,application/pdf'],
        ];
    }

    public function attributes(): array
    {
        $attributes = [];
        foreach ((array) $this->input('rows', []) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach (array_keys($row) as $field) {
                $attributes["rows.{$index}.{$field}"] = 'row '.((int) ($row['rowNumber'] ?? $index + 1)).' '.preg_replace('/([a-z])([A-Z])/', '$1 $2', $field);
            }
            if (is_string($row['uploadKey'] ?? null)) {
                foreach (['icPhoto' => 'IC photo', 'companyLogo' => 'company logo', 'ssmCertificate' => 'SSM certificate'] as $field => $label) {
                    $attributes['uploads.'.$row['uploadKey'].'.'.$field] = 'row '.((int) ($row['rowNumber'] ?? $index + 1)).' '.$label;
                }
            }
        }

        return $attributes;
    }
}
