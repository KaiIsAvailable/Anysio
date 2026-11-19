<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Access\Gate as AuthorizationGate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BulkEditPermissionViewTest extends TestCase
{
    public static function modules(): array
    {
        return [
            ['owner', 'adminSide/owners/index.blade.php'],
            ['tenant', 'adminSide/tenants/index.blade.php'],
            ['property', 'adminSide/rooms/property/index.blade.php'],
        ];
    }

    #[DataProvider('modules')]
    public function test_bulk_button_requires_bulk_and_existing_action_permissions(string $module, string $view): void
    {
        $this->assertContains('bulk edit', config('permissions.modules.'.$module));
        $source = file_get_contents(resource_path('views/'.$view));
        $start = strpos($source, "@canany(['super-admin', '{$module}.bulk edit'])");
        $this->assertNotFalse($start);
        $end = strpos($source, '@endcanany', strpos($source, '@endcanany', $start) + strlen('@endcanany')) + strlen('@endcanany');
        $button = substr($source, $start, $end - $start);

        foreach ([
            [[$module.'.create', $module.'.edit'], false],
            [[$module.'.bulk edit'], false],
            [[$module.'.bulk edit', $module.'.edit'], true],
            [['super-admin'], true],
        ] as [$permissions, $visible]) {
            Gate::swap(new AuthorizationGate(app(), fn () => new User(['role' => 'staff'])));
            Gate::before(fn ($user, $ability) => in_array($ability, $permissions, true));
            $html = Blade::render($button);
            $this->assertSame($visible, str_contains($html, 'Bulk Edit'));
        }
    }
}
