<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Access\Gate as AuthorizationGate;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NavigationMenuTest extends TestCase
{
    public static function menus(): array
    {
        return [
            'manager' => [
                ['dashboard.tab', 'property.tab', 'tenant.tab', 'owner.tab', 'staff.tab', 'leases.tab', 'invoice.tab', 'utilities.tab', 'service-request-tickets', 'maintenance.tab', 'maintenance.view'],
                ['Dashboard', 'Properties', 'Tenants', 'Owners', 'Staff', 'Leases', 'Invoices', 'Utilities', 'Service Requests', 'Maintenance'],
                ['People', 'Billing', 'Operations'],
            ],
            'platform admin' => [['super-admin'], ['Dashboard', 'Properties', 'Tenants', 'Owners', 'Staff', 'Leases', 'Invoices', 'Utilities', 'Users', 'Packages', 'Audit Log'], ['People', 'Billing', 'Administration']],
            'restricted staff' => [['staff.tab', 'utilities.tab'], ['Staff', 'Utilities'], ['People', 'Billing']],
            'maintenance without view' => [['maintenance.tab'], [], []],
            'maintenance only' => [['maintenance.tab', 'maintenance.view'], ['Maintenance'], ['Operations']],
            'tenant' => [['is-tenant', 'utilities.tab'], ['Dashboard', 'Help & Support'], []],
            'owner' => [['is-owner', 'utilities.tab'], ['Dashboard'], []],
            'no access' => [[], [], []],
        ];
    }

    #[DataProvider('menus')]
    public function test_desktop_and_phone_share_order_permissions_and_destinations(array $permissions, array $labels, array $groups): void
    {
        $this->allow($permissions);
        $desktop = $this->menu(false);
        $mobile = $this->menu(true);

        $this->assertSame($labels, array_column($desktop['links'], 'label'));
        $this->assertSame($groups, $desktop['groups']);
        $this->assertSame($desktop, $mobile);
    }

    public function test_work_orders_highlight_operations_and_only_the_maintenance_link(): void
    {
        $this->allow(['service-request-tickets', 'maintenance.tab', 'maintenance.view']);
        request()->setRouteResolver(fn () => (new Route('GET', '/', fn () => null))->name('admin.maintenance.work-orders.index'));

        foreach ([false, true] as $mobile) {
            $menu = $this->menu($mobile);
            $this->assertSame([false, true], array_column($menu['links'], 'active'));
            $this->assertSame(['Operations'], $menu['activeGroups']);
            $this->assertSame(2, $menu['badges']);
        }
    }

    private function allow(array $permissions): void
    {
        Gate::swap(new AuthorizationGate(app(), fn () => new User(['role' => 'staff'])));
        Gate::before(fn ($user, $ability) => in_array($ability, $permissions, true));
    }

    private function menu(bool $mobile): array
    {
        $html = Blade::render('<x-navigation-menu :mobile="$mobile" />', compact('mobile'));
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new \DOMXPath($document);
        $links = [];
        foreach ($xpath->query('//a') as $link) {
            $label = '';
            foreach ($link->childNodes as $node) {
                if ($node instanceof \DOMText) {
                    $label .= $node->textContent;
                }
            }
            $links[] = ['label' => trim($label), 'href' => $link->getAttribute('href'), 'active' => $link->getAttribute('aria-current') === 'page'];
        }
        $groups = [];
        $activeGroups = [];
        foreach ($xpath->query('//button') as $button) {
            $label = trim($xpath->query('./span', $button)->item(0)->textContent);
            $groups[] = $label;
            if (str_contains($button->getAttribute('class'), 'border-indigo-400')) {
                $activeGroups[] = $label;
            }
        }

        $badges = substr_count($html, 'role="status"');

        return compact('links', 'groups', 'activeGroups', 'badges');
    }
}
