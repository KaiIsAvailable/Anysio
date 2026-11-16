<?php

namespace Tests\Feature;

use App\Rules\UnitFloor;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UnitFloorTest extends TestCase
{
    public function test_shared_form_renders_server_and_client_error_navigation_without_changing_other_forms(): void
    {
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['floor' => ['Invalid floor']])));
        $html = Blade::render('<x-form.form :focus-errors="true"><x-form.text-input name="floor" value="G" /></x-form.form>');
        $this->assertStringContainsString('@invalid.capture="window.formValidation.focusInvalidForm($event)"', $html);
        $this->assertStringContainsString('window.formValidation.focusFormErrors', $html);
        $this->assertStringContainsString('floor', $html);
        $this->assertStringContainsString('value="G"', $html);
        $plain = Blade::render('<x-form.form><x-form.text-input name="other" /></x-form.form>');
        $this->assertStringNotContainsString('window.formValidation', $plain);
    }

    public static function floors(): array
    {
        return [
            ['G', true], ['0', true], [0, true], ['12', true], ['-2', true], ['+2', true],
            ['2147483647', true], ['-2147483648', true], [null, true], ['', true],
            ['2147483648', false], ['-2147483649', false], ['1.5', false], ['g', false],
            ['Ground', false], [['G'], false], [true, false], ['1e2', false],
        ];
    }

    #[DataProvider('floors')]
    public function test_floor_accepts_ground_or_existing_whole_numbers(mixed $floor, bool $valid): void
    {
        $this->assertSame($valid, Validator::make(['floor' => $floor], ['floor' => ['nullable', new UnitFloor]])->passes());
    }
}
