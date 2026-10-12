<?php

namespace Tests\Unit;

use App\Services\LatePenaltyCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LatePenaltyCalculatorTest extends TestCase
{
    private function settings(array $changes = [], bool $active = true): array
    {
        return ['late_penalty_config' => ['is_active' => $active, 'value' => array_merge([
            'grace_period_days' => 0, 'frequency' => 1, 'calculation_type' => 'fixed',
            'amount' => 1000, 'maximum_amount' => 0, 'applicable_categories' => ['rent'],
        ], $changes)]];
    }

    public static function dates(): array
    {
        return [
            [0, 1, '2026-10-19', 0], [0, 1, '2026-10-20', 10], [0, 1, '2026-10-21', 20],
            [1, 1, '2026-10-20', 0], [1, 1, '2026-10-21', 10], [1, 1, '2026-10-22', 20],
            [0, 3, '2026-10-19', 0], [0, 3, '2026-10-20', 10], [0, 3, '2026-10-22', 10],
            [0, 3, '2026-10-23', 20], [0, 3, '2026-10-25', 20], [0, 3, '2026-10-26', 30],
            [1, 3, '2026-10-20', 0], [1, 3, '2026-10-21', 10], [1, 3, '2026-10-23', 10],
            [1, 3, '2026-10-24', 20], [1, 3, '2026-10-27', 30],
            [0, 1, '2026-10-19T15:59:59Z', 0], [0, 1, '2026-10-19T16:00:00Z', 10],
            [0, 1, '2026-10-20T15:59:59Z', 10], [0, 1, '2026-10-20T16:00:00Z', 20],
        ];
    }

    #[DataProvider('dates')]
    public function test_inclusive_start_and_local_calendar_boundaries(int $grace, int $frequency, string $date, int $expected): void
    {
        $result = (new LatePenaltyCalculator())->calculate(
            $this->settings(['grace_period_days' => $grace, 'frequency' => $frequency]),
            '2026-10-20', 100, $date, [['category' => 'rent']]
        );
        $this->assertEquals($expected, $result['amount'] ?? 0);
    }

    public function test_no_charge_when_paid_disabled_or_category_does_not_apply(): void
    {
        $calculator = new LatePenaltyCalculator();
        foreach ([
            [$this->settings(), 0, [['category' => 'rent']]],
            [$this->settings([], false), 100, [['category' => 'rent']]],
            [$this->settings(), 100, [['category' => 'deposit']]],
            [$this->settings(['applicable_categories' => []]), 100, [['category' => 'rent']]],
        ] as [$settings, $balance, $items]) {
            $this->assertNull($calculator->calculate($settings, '2026-10-20', $balance, '2026-10-25', $items));
        }
    }

    public function test_percentage_uses_remaining_balance_and_caps_both_types(): void
    {
        $calculator = new LatePenaltyCalculator();
        foreach (['fixed', 'percentage'] as $type) {
            $settings = $this->settings(['calculation_type' => $type, 'rate' => 10, 'maximum_amount' => 2500]);
            $this->assertEquals(10, $calculator->calculate($settings, '2026-10-20', 100, '2026-10-20', [['category' => 'rent']])['amount']);
            $this->assertEquals(25, $calculator->calculate($settings, '2026-10-20', 100, '2026-10-22', [['category' => 'rent']])['amount']);
        }
        $settings = $this->settings(['calculation_type' => 'percentage', 'rate' => 5]);
        $this->assertEquals(7.5, $calculator->calculate($settings, '2026-10-20', 50, '2026-10-22', [['category' => 'rent']])['amount']);
    }

    public function test_month_and_year_rollover(): void
    {
        $calculator = new LatePenaltyCalculator();
        $settings = $this->settings(['grace_period_days' => 1, 'frequency' => 2]);
        $this->assertEquals(10, $calculator->calculate($settings, '2026-12-31', 100, '2027-01-01', [['category' => 'rent']])['amount']);
        $this->assertEquals(20, $calculator->calculate($settings, '2026-12-31', 100, '2027-01-03', [['category' => 'rent']])['amount']);
        $this->assertEquals(10, $calculator->calculate($settings, '2028-02-28', 100, '2028-02-29', [['category' => 'rent']])['amount']);
    }
}
