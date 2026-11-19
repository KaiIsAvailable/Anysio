@props(['mobile' => false])

@php
    // Both layouts use these same permissions, destinations and ordering.
    $can = fn (string $ability) => \Illuminate\Support\Facades\Gate::allows($ability);
    $canAny = fn (string $ability) => \Illuminate\Support\Facades\Gate::any(['super-admin', $ability]);
    $link = fn (string $label, string $route, bool $visible, bool $active, bool $unread = false) => compact('label', 'route', 'visible', 'active', 'unread');
    $group = function (string $label, array $children) {
        $children = array_values(array_filter($children, fn ($item) => $item['visible']));

        return [
            'label' => $label,
            'children' => $children,
            'visible' => count($children) > 0,
            'active' => collect($children)->contains('active', true),
            'unread' => collect($children)->contains('unread', true),
        ];
    };
    $items = [
        $link('Dashboard', 'dashboard', $canAny('dashboard.tab'), request()->routeIs('dashboard')),
        $link('Dashboard', 'admin.owners.dashboard', $can('is-owner'), request()->routeIs('admin.owners.dashboard')),
        $link('Dashboard', 'tenants.dashboard', $can('is-tenant'), request()->routeIs('tenants.dashboard')),
        $link('Help & Support', 'tenants.customerService.index', $can('is-tenant'), request()->routeIs('tenants.customerService.*'), true),
        $link('Properties', 'admin.properties.index', $canAny('property.tab'), request()->routeIs('admin.properties.*', 'admin.units.*', 'admin.rooms.*', 'admin.roomAsset.*')),
        $group('People', [
            $link('Tenants', 'admin.tenants.index', $canAny('tenant.tab'), request()->routeIs('admin.tenants.*') && !request()->routeIs('admin.tenants.dashboard')),
            $link('Owners', 'admin.owners.index', $canAny('owner.tab'), request()->routeIs('admin.owners.*') && !request()->routeIs('admin.owners.dashboard')),
            $link('Staff', 'admin.staff.index', $canAny('staff.tab'), request()->routeIs('admin.staff.*')),
        ]),
        $link('Leases', 'admin.leases.index', $canAny('leases.tab'), request()->routeIs('admin.leases.*', 'admin.document-templates.*')),
        $group('Billing', [
            $link('Invoices', 'admin.invoices.index', $canAny('invoice.tab'), request()->routeIs('admin.invoices.*')),
            $link('Utilities', 'admin.utilities.index', $canAny('utilities.tab') && !$can('is-tenant') && !$can('is-owner'), request()->routeIs('admin.utilities.*')),
        ]),
        $group('Operations', [
            $link('Service Requests', 'admin.maintenance.index', $can('service-request-tickets'), request()->routeIs('admin.maintenance.*') && !request()->routeIs('admin.maintenance.work-orders.*'), true),
            $link('Maintenance', 'admin.maintenance.work-orders.index', $can('maintenance.tab') && $can('maintenance.view'), request()->routeIs('admin.maintenance.work-orders.*')),
        ]),
        $group('Administration', [
            $link('Users', 'admin.userManagement.index', $can('super-admin'), request()->routeIs('admin.userManagement.*')),
            $link('Packages', 'admin.packages.index', $can('super-admin'), request()->routeIs('admin.packages.*')),
            $link('Audit Log', 'admin.audit.index', $can('super-admin'), request()->routeIs('admin.audit.*')),
        ]),
    ];
@endphp

@foreach ($items as $item)
    @if ($item['visible'])
        @if (isset($item['children']))
            <div x-data="{ expanded: {{ $mobile && $item['active'] ? 'true' : 'false' }} }"
                 x-id="['navigation-group']"
                 @click.outside="expanded = false"
                 @keydown.escape.stop="expanded = false; $refs.trigger.focus()"
                 @class(['relative', 'flex' => !$mobile])>
                <button type="button" x-ref="trigger" @click="expanded = !expanded"
                        :aria-expanded="expanded" :aria-controls="$id('navigation-group')"
                        @class([
                            'flex items-center gap-1 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600',
                            'w-full justify-between border-l-4 py-2 ps-3 pe-4 text-base' => $mobile,
                            'border-b-2 px-1 pt-1 whitespace-nowrap' => !$mobile,
                            'border-indigo-400 text-indigo-700' => $item['active'],
                            'bg-indigo-50' => $mobile && $item['active'],
                            'border-transparent text-gray-500 hover:text-indigo-700 hover:border-indigo-400' => !$item['active'],
                        ])>
                    <span class="inline-flex items-center">
                        {{ __($item['label']) }}
                        @if ($item['unread'])
                            <x-ui.unread-message-badge />
                        @endif
                    </span>
                    <svg class="h-4 w-4 shrink-0 transition-transform" :class="expanded && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="expanded" x-cloak :id="$id('navigation-group')"
                     @class([
                         'space-y-1 bg-gray-50 py-1 ps-4' => $mobile,
                         'absolute start-0 top-full z-50 mt-1 w-52 rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5' => !$mobile,
                     ])>
                    @foreach ($item['children'] as $child)
                        <x-responsive-nav-link :href="route($child['route'])" :active="$child['active']"
                                               :aria-current="$child['active'] ? 'page' : null"
                                               class="flex items-center text-sm hover:!bg-indigo-50 hover:!text-indigo-700 hover:!border-indigo-400 focus:!bg-indigo-50 focus:!text-indigo-700 focus:!border-indigo-400">
                            {{ __($child['label']) }}
                            @if ($child['unread'])
                                <x-ui.unread-message-badge />
                            @endif
                        </x-responsive-nav-link>
                    @endforeach
                </div>
            </div>
        @else
            <x-dynamic-component :component="$mobile ? 'responsive-nav-link' : 'nav-link'"
                                 :href="route($item['route'])" :active="$item['active']"
                                 :aria-current="$item['active'] ? 'page' : null" class="whitespace-nowrap">
                {{ __($item['label']) }}
                @if ($item['unread'])
                    <x-ui.unread-message-badge />
                @endif
            </x-dynamic-component>
        @endif
    @endif
@endforeach
