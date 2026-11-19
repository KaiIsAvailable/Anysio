<nav x-data="serviceRequestNavigation($el.dataset.unreadUrl)" @can('is-tenant') data-unread-url="{{ route('tenants.customerService.unread') }}" @elsecan('service-request-tickets') data-unread-url="{{ route('admin.maintenance.unread') }}" @endcan class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-stretch justify-between gap-3">
            <div class="flex min-w-0">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    @php
                        $dashboardRoute = route(app(\App\Services\LandingPageService::class)->routeFor(Auth::user()));
                    @endphp

                    <a href="{{ $dashboardRoute }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Shared desktop navigation -->
                <div class="hidden xl:-my-px xl:ms-8 xl:flex xl:gap-5" aria-label="{{ __('Main navigation') }}">
                    <x-navigation-menu />
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div x-data="{ 
                openNotifications: false,
                openSetting: false, 
                openBoost: false, 
                openPackage: false, 
                open: false, 
                boostData: { 
                    packageName: '{{ get_effective_user()->user_management->package->name ?? "N/A" }}', 
                    maxLimit: {{ get_effective_user()->user_management->package->max_lease_limit ?? 0 }}, 
                    balanceLimit: {{ 
                        (get_effective_user()->user_management && get_effective_user()->user_management->package) 
                        ? (get_effective_user()->user_management->package->max_lease_limit - get_effective_user()->user_management->extra_lease) 
                        : 0 
                    }},
                    extraPrice: {{ get_effective_user()->user_management->package->extra_lease_price ?? 0 }},

                    paymentMode: '{{ get_effective_user()->user_management->package->price_mode ?? "N/A" }}',
                    price: '{{ get_effective_user()->user_management?->package?->price / 100  ?? 0 }}',
                    TotalAvailableLease: '{{ get_effective_user()->user_management?->package?->base_lease?? 0 }}',
                    status: '{{ get_effective_user()->user_management?->package?->status ?? "N/A" }}'
                } 
            }" class="flex min-w-0 items-center ms-auto gap-1 sm:gap-2">

                <div class="flex items-center">
                    <x-ui.notification-dropdown />
                </div>

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button type="button" :aria-expanded="open" aria-label="{{ __('Profile menu') }}"
                                class="inline-flex items-center gap-1 rounded-md border border-transparent bg-white px-2 py-2 text-sm font-medium text-gray-500 transition hover:text-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600">
                            <span class="hidden max-w-32 truncate sm:block">{{ Auth::user()->name }}</span>
                            <svg class="h-5 w-5 sm:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 21a8 8 0 0116 0" />
                            </svg>
                            <svg class="h-4 w-4 transition-transform" :class="open && 'rotate-180'" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">{{ __('Profile') }}</x-dropdown-link>

                        @canany(['super-admin', 'profile menu.boost limit', 'profile menu.view package', 'document template.create', 'profile menu.setting'])
                            <div class="border-t border-gray-100"></div>

                            {{-- 2. 直接使用父级的 openSetting 控制显示 --}}
                            @canany(['super-admin', 'profile menu.boost limit', 'profile menu.view package', 'document template.create'])
                            <div class="relative"> 
                                <button type="button" @click.stop="openSetting = !openSetting" :aria-expanded="openSetting"
                                    class="flex items-center justify-between w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer">
                                    <span>{{ __('Quick Actions') }}</span>
                                    <svg class="w-4 h-4 ml-1 text-gray-400 transition-transform duration-200"
                                        :class="openSetting ? 'rotate-90' : ''"
                                        fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </button>

                                {{-- 3. 这里不再定义 x-data --}}
                                <div x-show="openSetting" 
                                    x-cloak
                                    x-transition 
                                    class="bg-gray-50 border-l-4 border-indigo-400">

                                    @canany(['super-admin', 'profile menu.view package'])
                                    <button type="button" 
                                            @click.prevent="openPackage = true; openSetting = false;" 
                                            class="block w-full pl-8 py-2 text-xs text-left text-gray-700 hover:bg-gray-100">
                                        {{ __('View Current Package') }}
                                    </button>
                                    @endcanany

                                    {{-- 直接触发父级的 openBoost --}}
                                    @canany(['super-admin', 'profile menu.boost limit'])
                                    <button type="button" 
                                            @click.prevent="openBoost = true; openSetting = false;" 
                                            class="block w-full pl-8 py-2 text-xs text-left text-gray-700 hover:bg-gray-100">
                                        {{ __('+ Boost Lease Capacity') }}
                                    </button>
                                    @endcanany

                                    @canany(['super-admin', 'document template.create'])
                                    <x-dropdown-link :href="route('admin.document-templates.create')" class="pl-8 py-2 text-xs">
                                        {{ __('+ Add Document Template') }}
                                    </x-dropdown-link>
                                    @endcanany
                                </div>
                            </div>
                            @endcanany

                            @canany(['super-admin', 'profile menu.setting'])
                            <x-dropdown-link :href="route('admin.settings.index')">{{ __('Settings') }}</x-dropdown-link>
                            @endcanany
                        @endcanany

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                    this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>

                {{-- 4. 模态框放在这里，它能直接访问到父级的 boostData 和 openBoost --}}
                @include('components.modals.boost-lease-modal')
                @include('components.modals.package-modal')
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex shrink-0 items-center xl:hidden">
                <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="mobile-navigation" aria-label="{{ __('Toggle navigation') }}" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Same navigation groups and permissions on mobile and tablet. -->
    <div id="mobile-navigation" x-show="open" x-cloak @keydown.escape.stop="open = false"
         class="max-h-[calc(100dvh-4rem)] overflow-y-auto border-t border-gray-100 xl:hidden">
        <div class="space-y-1 py-3" aria-label="{{ __('Main navigation') }}">
            <x-navigation-menu :mobile="true" />
        </div>
    </div>
</nav>
