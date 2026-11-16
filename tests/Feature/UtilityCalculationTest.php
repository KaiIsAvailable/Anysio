<?php
namespace Tests\Feature;

use App\Services\{UtilityCalculationService, UtilityAllocationService, UtilityDecimal};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UtilityCalculationTest extends TestCase
{
    private function sample(): array
    {
        return ['calculation' => 'blocks', 'minimum_policy' => 'monthly', 'minimum_charge' => '7.50',
            'tiers' => [['limit' => '20', 'rate' => '0.75'], ['limit' => '35', 'rate' => '1.08'], ['limit' => null, 'rate' => '1.35']]];
    }

    public function test_sample_blocks_boundaries_minimum_and_fractional_consumption_are_exact(): void
    {
        $calculator = new UtilityCalculationService;
        foreach (['0' => 750, '1' => 750, '20' => 1500, '30' => 2580, '35' => 3120, '40' => 3795, '20.005' => 1501] as $usage => $amount) {
            $this->assertSame($amount, $calculator->calculate($this->sample(), (string) $usage)['amount']);
        }
        $sample = $this->sample(); $sample['minimum_policy'] = 'positive_usage';
        $this->assertSame(0, $calculator->calculate($sample, '0')['amount']);
        $sample['minimum_policy'] = 'none';
        $this->assertSame(75, $calculator->calculate($sample, '1')['amount']);
        $this->assertSame('30.000', $calculator->consumption('120', '150'));
        $flat = ['calculation' => 'flat', 'minimum_policy' => 'none', 'tiers' => [['limit' => null, 'rate' => '5']]];
        $this->assertSame(1, $calculator->calculate($flat, '0.001')['amount']);
        $this->assertSame(0, $calculator->calculate($flat, null)['amount'] ?? 0);
        $this->assertNull($calculator->calculate($flat, null)['amount']);
        $this->assertSame(1234, $calculator->calculate(['calculation' => 'fixed', 'fixed_amount' => '12.34'], null)['amount']);
        $this->assertSame(901, $calculator->calculate(['calculation' => 'actual_bill'], null, ['bill_amount' => '9.01'])['amount']);
    }

    public function test_meter_events_preserve_old_and_new_consumption_segments(): void
    {
        $calculator = new UtilityCalculationService;
        $this->assertSame('8.500', $calculator->consumption('120', '3.5', ['type' => 'replacement', 'old_closing' => '125', 'new_opening' => '0']));
        $this->assertSame('5.000', $calculator->consumption('998', '3', ['type' => 'rollover', 'capacity' => '1000']));
        $this->assertNull($calculator->consumption(null, '0'));
        foreach ([fn () => $calculator->consumption('10', '9'), fn () => UtilityDecimal::scaled('1e3', 3),
            fn () => $calculator->consumption('998', '999', ['type' => 'rollover', 'capacity' => '1000']),
            fn () => $calculator->consumption('120', '3', ['type' => 'reset', 'old_closing' => '119', 'new_opening' => '0']),
            fn () => UtilityDecimal::product(PHP_INT_MAX, 2)] as $invalid) {
            try { $invalid(); $this->fail('Invalid calculation accepted.'); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
    }

    public function test_largest_remainder_rounding_conserves_cents_and_breaks_ties_deterministically(): void
    {
        $allocation = new UtilityAllocationService;
        $shares = $allocation->distribute(100, ['c' => 1, 'a' => 1, 'b' => 1]);
        $this->assertSame(['c' => 33, 'a' => 34, 'b' => 33], $shares);
        $this->assertSame([1073741823, 1], $allocation->ratio(2147483647, 1, 2));
        $shares = $allocation->distribute(2147483647, ['a' => 1000000000000000, 'b' => 1000000000000000]);
        $this->assertSame(2147483647, array_sum($shares));
        $this->assertSame(1073741824, $shares['a']);
    }
}
