<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UnitFloor implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === 'G') {
            return;
        }

        if ((! is_string($value) && ! is_int($value))
            || ! preg_match('/^[+-]?\d+$/D', (string) $value)
            || (float) $value < -2147483648 || (float) $value > 2147483647) {
            $fail('The :attribute must be G (Ground Floor) or a whole number between -2147483648 and 2147483647.');
        }
    }
}
