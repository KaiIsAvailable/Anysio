<?php

namespace App\Services;

use Carbon\Carbon;

class RecurringInvoicePeriod
{
    /** Lease anniversary cycles; clamp short months without losing the original anchor. */
    public function containing(Carbon $anchor, Carbon $date, string $frequency): ?Carbon
    {
        $anchor = $anchor->copy()->startOfDay();
        $date = $date->copy()->startOfDay();
        if ($date->lt($anchor)) return null;

        $period = match ($frequency) {
            'daily' => $date->copy(),
            'weekly' => $anchor->copy()->addWeeks(intdiv((int) $anchor->diffInDays($date), 7)),
            'monthly' => $anchor->copy()->addMonthsNoOverflow(($date->year - $anchor->year) * 12 + $date->month - $anchor->month),
            'yearly' => $anchor->copy()->addYearsNoOverflow($date->year - $anchor->year),
            default => null,
        };
        if ($period?->gt($date)) {
            return $this->containing($anchor, $date->copy()->startOfMonth()->subDay(), $frequency);
        }
        return $period;
    }

    public function next(Carbon $anchor, Carbon $period, string $frequency): Carbon
    {
        return match ($frequency) {
            'daily' => $period->copy()->addDay(),
            'weekly' => $period->copy()->addWeek(),
            'monthly' => $anchor->copy()->addMonthsNoOverflow(($period->year - $anchor->year) * 12 + $period->month - $anchor->month + 1),
            'yearly' => $anchor->copy()->addYearsNoOverflow($period->year - $anchor->year + 1),
        };
    }
}
