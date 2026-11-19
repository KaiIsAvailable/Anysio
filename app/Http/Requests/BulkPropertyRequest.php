<?php

namespace App\Http\Requests;

use App\Models\Property;
use App\Rules\UnitFloor;
use Illuminate\Validation\Rule;

class BulkPropertyRequest extends BulkEditRequest
{
    public function rules(): array
    {
        $rules = $this->envelopeRules();
        $rules['uploads'] = ['prohibited'];
        $rules['rows.*.fileFields'] = ['prohibited'];
        $rules['rows.*.entity'] = ['sometimes', 'in:property,unit,room'];
        $rules['rows.*.clientKey'] = ['nullable', 'string', 'regex:/^[A-Za-z0-9-]{1,64}$/', 'distinct'];
        foreach ((array) $this->input('rows', []) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $prefix = "rows.{$index}.";
            $entity = $row['entity'] ?? 'property';
            if (($row['action'] ?? '') !== 'create') {
                $rules[$prefix.'id'] = ['required', 'ulid'];
                $rules[$prefix.'version'] = ['required', 'string', 'size:64'];
            } elseif (isset($row['entity'])) {
                $rules[$prefix.'clientKey'] = ['required', 'string', 'regex:/^[A-Za-z0-9-]{1,64}$/'];
            }
            if (($row['action'] ?? '') === 'remove' || ! $this->validateRowDetails) {
                continue;
            }
            $rules += [
                $prefix.'waterHasMeter' => ['sometimes', 'required', 'boolean'],
                $prefix.'waterMeterNumber' => ['exclude_unless:'.$prefix.'waterHasMeter,1', 'nullable', 'string', 'max:255'],
                $prefix.'waterOpeningReading' => ['exclude_unless:'.$prefix.'waterHasMeter,1', 'nullable', 'numeric', 'min:0', 'max:999999999999.999', 'decimal:0,3', 'required_with:'.$prefix.'waterOpeningDate'],
                $prefix.'waterOpeningDate' => ['exclude_unless:'.$prefix.'waterHasMeter,1', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'required_with:'.$prefix.'waterOpeningReading'],
            ];
            if ($entity === 'property') {
                $rules += [
                    $prefix.'name' => ['required', 'string', 'max:255'],
                    $prefix.'propertyType' => ['required', Rule::in(array_keys(Property::propertyTypes()))],
                    $prefix.'address' => ['required', 'string', 'max:255'],
                    $prefix.'postcode' => ['required', 'digits:5'],
                    $prefix.'city' => ['required', 'string', 'max:100'],
                    $prefix.'state' => ['required', 'string', 'max:100'],
                ];
            } else {
                $rules[$prefix.'parentKey'] = ['required', 'string', 'regex:/^[A-Za-z0-9-]{1,64}$/'];
            }
            if ($entity !== 'room') {
                $rules[$prefix.'ownerId'] = ['nullable', 'ulid'];
            }
            if ($entity === 'unit') {
                $rules += [
                    $prefix.'unitNo' => ['required', 'string', 'max:255'],
                    $prefix.'unitType' => ['nullable', 'string', 'max:255'],
                    $prefix.'block' => ['nullable', 'string', 'max:255'],
                    $prefix.'floor' => ['nullable', new UnitFloor],
                    $prefix.'sqft' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
                    $prefix.'electricityNo' => ['nullable', 'digits:12'],
                    $prefix.'waterNo' => ['nullable', 'digits:16'],
                    $prefix.'indahWaterNo' => ['nullable', 'string', 'regex:/^[0-9]{1,50}$/'],
                ];
            } elseif ($entity === 'room') {
                $rules[$prefix.'roomNo'] = ['required', 'string', 'max:255'];
                $rules[$prefix.'roomType'] = ['required', 'string', 'max:255'];
            }
        }

        return $rules;
    }
}
