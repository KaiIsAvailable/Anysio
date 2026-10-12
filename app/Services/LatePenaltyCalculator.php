<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/** Date-only penalty policy shared by previews, collection and tenant reminders. */
class LatePenaltyCalculator
{
    public const TIMEZONE = 'Asia/Kuala_Lumpur';

    public function day($date = null): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::TIMEZONE)->setTimezone(self::TIMEZONE)->startOfDay();
    }

    public function startDate($dueDate, array $config): CarbonImmutable
    {
        return $this->day($dueDate)->addDays(max(0, (int) ($config['grace_period_days'] ?? 0)));
    }

    public function applicableConfig(array $settings, $invoiceItems): ?array
    {
        if (!filter_var(data_get($settings, 'late_penalty_config.is_active', false), FILTER_VALIDATE_BOOLEAN)) return null;
        $config = (array) data_get($settings, 'late_penalty_config.value', []);
        $categories = $config['applicable_categories'] ?? [];
        if (!$categories) return null;
        $matches = collect($invoiceItems)->contains(function ($item) use ($categories) {
            $category = is_array($item) ? ($item['category'] ?? null) : $item->feeType?->category;
            if ($category instanceof \BackedEnum) $category = $category->value;
            elseif (is_object($category)) $category = $category->value ?? (string) $category;
            return in_array($category, $categories);
        });
        return $matches ? $config : null;
    }

    /** Amounts passed in and returned here are RM; stored fixed amounts/caps are cents. */
    public function calculate(array $settings, $dueDate, $balance, $paymentDate = null, $invoiceItems = []): ?array
    {
        if ((float) $balance <= 0 || !$dueDate) return null;
        $config = $this->applicableConfig($settings, $invoiceItems);
        if (!$config) return null;
        $start = $this->startDate($dueDate, $config);
        $day = $this->day($paymentDate);
        if ($day->lt($start)) return null;

        $frequency = max(1, (int) ($config['frequency'] ?? 1));
        $periods = intdiv((int) $start->diffInDays($day), $frequency) + 1;
        $type = $config['calculation_type'] ?? ($config['type'] ?? 'fixed');
        $amount = 0;
        $description = '';
        if ($type === 'fixed') {
            $unitAmount = (float) ($config['amount'] ?? 0) / 100;
            $amount = $unitAmount * $periods;
            $description = sprintf('RM %.2f every %d day(s), starting %s (%d charge(s))', $unitAmount, $frequency, $start->toDateString(), $periods);
        } elseif ($type === 'percentage') {
            $rate = (float) ($config['rate'] ?? 0);
            $amount = (float) $balance * ($rate / 100) * $periods;
            $description = sprintf('%.2f%% every %d day(s), starting %s (%d charge(s))', $rate, $frequency, $start->toDateString(), $periods);
        }
        $cap = (float) ($config['maximum_amount'] ?? 0) / 100;
        if ($cap > 0 && $amount > $cap) {
            $amount = $cap;
            $description .= sprintf(' (Capped at RM %.2f)', $cap);
        }

        return [
            'amount' => round($amount, 2), 'title' => 'Late Penalty', 'calculation' => $description,
            'start_date' => $start->toDateString(), 'through_date' => $day->toDateString(),
            'periods' => $periods, 'frequency' => $frequency,
        ];
    }
}
