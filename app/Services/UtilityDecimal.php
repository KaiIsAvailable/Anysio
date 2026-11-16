<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class UtilityDecimal
{
    public static function scaled(mixed $value, int $places, bool $signed = false): int
    {
        $text = trim((string) $value);
        if (!preg_match('/^'.($signed ? '-?' : '').'\d+(?:\.\d{1,'.$places.'})?$/D', $text)) {
            throw ValidationException::withMessages(['calculation' => "Enter a valid decimal with up to {$places} decimal places."]);
        }
        $negative = str_starts_with($text, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($text, '-'), 2), 2, '');
        $digits = ltrim($whole.str_pad($fraction, $places, '0'), '0') ?: '0';
        $max = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($max) || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
            throw ValidationException::withMessages(['calculation' => 'The decimal amount is too large.']);
        }
        return ($negative ? -1 : 1) * (int) $digits;
    }

    public static function format(int $value, int $places): string
    {
        $sign = $value < 0 ? '-' : '';
        $value = abs($value); $scale = 10 ** $places;
        return $sign.intdiv($value, $scale).'.'.str_pad((string) ($value % $scale), $places, '0', STR_PAD_LEFT);
    }

    public static function product(int $a, int $b): int
    {
        if ($a < 0 || $b < 0 || ($b && $a > intdiv(PHP_INT_MAX, $b))) {
            throw ValidationException::withMessages(['calculation' => 'Calculated charge is too large.']);
        }
        return $a * $b;
    }

    public static function add(int $a, int $b): int
    {
        if (($b > 0 && $a > PHP_INT_MAX - $b) || ($b < 0 && $a < -PHP_INT_MAX - $b)) {
            throw ValidationException::withMessages(['calculation' => 'Calculated charge is too large.']);
        }
        return $a + $b;
    }

    public static function cents(int $nanoRm): int
    {
        $cents = intdiv($nanoRm, 10000000) + (($nanoRm % 10000000) >= 5000000 ? 1 : 0);
        if ($nanoRm < 0 || $cents > 2147483647) {
            throw ValidationException::withMessages(['calculation' => 'Charge exceeds the supported amount.']);
        }
        return $cents;
    }
}
