<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class UtilityCalculationService
{
    public function consumption(mixed $previous, mixed $current, ?array $event = null): ?string
    {
        if ($previous === null || $previous === '' || $current === null || $current === '') return null;
        $previous = UtilityDecimal::scaled($previous, 3); $current = UtilityDecimal::scaled($current, 3);
        if ($event) {
            if ($event['type'] === 'rollover') {
                $capacity = UtilityDecimal::scaled($event['capacity'], 3);
                if ($capacity <= $previous || $capacity <= $current || $current >= $previous) {
                    throw ValidationException::withMessages(['meter_event' => 'Rollover readings must cross the recorded meter capacity.']);
                }
                return UtilityDecimal::format($capacity - $previous + $current, 3);
            }
            $closing = UtilityDecimal::scaled($event['old_closing'], 3);
            $opening = UtilityDecimal::scaled($event['new_opening'], 3);
            if ($closing < $previous || $current < $opening) {
                throw ValidationException::withMessages(['meter_event' => 'Replacement/reset readings do not match the old closing and new opening values.']);
            }
            return UtilityDecimal::format(UtilityDecimal::add($closing - $previous, $current - $opening), 3);
        }
        if ($current < $previous) throw ValidationException::withMessages(['readings' => 'Current reading cannot be lower than previous reading without a recorded meter event.']);
        return UtilityDecimal::format($current - $previous, 3);
    }

    public function calculate(array $definition, ?string $usage, array $options = []): array
    {
        $method = $definition['calculation'];
        $result = ['method' => $method, 'usage' => $usage, 'blocks' => [], 'adjustments' => [],
            'minimum_charge' => $definition['minimum_charge'] ?? '0', 'minimum_applied' => false,
            'rounding' => 'half_up_once_at_total', 'amount' => null];
        if ($method === 'fixed' || $method === 'actual_bill') {
            $amount = $method === 'fixed' ? ($definition['fixed_amount'] ?? null) : ($options['bill_amount'] ?? null);
            if ($amount === null || $amount === '') return $result;
            $total = UtilityDecimal::product(UtilityDecimal::scaled($amount, 2), 10000000);
        } else {
            if ($usage === null) return $result;
            $used = UtilityDecimal::scaled($usage, 3); $total = 0; $lower = 0;
            $tiers = $definition['tiers'] ?? [];
            if (!$tiers || !in_array($method, ['blocks', 'flat', 'whole_usage'], true)) {
                throw ValidationException::withMessages(['tariff' => 'The tariff has no valid rate schedule.']);
            }
            foreach ($tiers as $index => $tier) {
                $upper = $tier['limit'] === null || $tier['limit'] === '' ? PHP_INT_MAX : UtilityDecimal::scaled($tier['limit'], 3);
                if ($upper <= $lower || ($index === count($tiers) - 1 && $upper !== PHP_INT_MAX)
                    || ($index < count($tiers) - 1 && $upper === PHP_INT_MAX)) {
                    throw ValidationException::withMessages(['tariff' => 'Tariff block limits must increase and end with an unlimited block.']);
                }
                $quantity = $method === 'blocks' ? max(0, min($used, $upper) - $lower) : ($used <= $upper ? $used : 0);
                $rate = UtilityDecimal::scaled($tier['rate'], 6);
                $charge = UtilityDecimal::product($quantity, $rate);
                $total = UtilityDecimal::add($total, $charge);
                if ($quantity > 0) $result['blocks'][] = ['quantity' => UtilityDecimal::format($quantity, 3),
                    'rate' => UtilityDecimal::format($rate, 6), 'amount_nano_rm' => (string) $charge,
                    'amount' => UtilityDecimal::cents($charge)];
                if ($method !== 'blocks' && $used <= $upper) break;
                $lower = $upper;
            }
            foreach (['capacity_rate', 'network_rate'] as $field) {
                $total = UtilityDecimal::add($total, UtilityDecimal::product($used, UtilityDecimal::scaled($definition[$field] ?? '0', 6)));
            }
            $waiver = $definition['fixed_waiver_up_to'] ?? null;
            if ($waiver === null || $waiver === '' || $used > UtilityDecimal::scaled($waiver, 3)) {
                $total = UtilityDecimal::add($total, UtilityDecimal::product(1000, UtilityDecimal::scaled($definition['fixed_charge'] ?? '0', 6)));
            }
        }
        $policy = $definition['minimum_policy'] ?? ($method === 'blocks' || $method === 'whole_usage' ? 'monthly' : 'none');
        $minimum = UtilityDecimal::product(1000, UtilityDecimal::scaled($definition['minimum_charge'] ?? '0', 6));
        if (($policy === 'monthly' || ($policy === 'positive_usage' && $usage !== null && UtilityDecimal::scaled($usage, 3) > 0)) && $total < $minimum) {
            $total = $minimum; $result['minimum_applied'] = true;
        }
        $cents = UtilityDecimal::cents($total);
        foreach ($options['adjustments'] ?? [] as $adjustment) {
            if (trim($adjustment['reason'] ?? '') === '') throw ValidationException::withMessages(['adjustments' => 'An adjustment reason is required.']);
            $amount = UtilityDecimal::scaled($adjustment['amount'], 2, true);
            $cents = UtilityDecimal::add($cents, $amount);
            $result['adjustments'][] = ['reason' => $adjustment['reason'], 'amount' => $amount];
        }
        if ($cents < 0 || $cents > 2147483647) throw ValidationException::withMessages(['amount' => 'The final charge must be within the supported non-negative range.']);
        $result['amount'] = $cents;
        return $result;
    }
}
