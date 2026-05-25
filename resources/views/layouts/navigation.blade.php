<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    @php
                        // 根据角色确定跳转的路由
                        $dashboardRoute = route('dashboard'); // 默认路由

                        if (auth()->user()->can('is-owner')) {
                            $dashboardRoute = route('admin.owners.dashboard');
                        } elseif (auth()->user()->can('is-tenant')) {
                            $dashboardRoute = route('admin.tenants.dashboard');
                        } elseif (auth()->user()->can('owner-admin')) {
                            $dashboardRoute = route('dashboard');
                        }
                    @endphp

                    <a href="{{ $dashboardRoute }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <!--Admin, Owner and Tenant dashboard-->
                    @can('owner-admin')
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                            {{ __('Dashboard') }}
                        </x-nav-link>
                    @endcan

                    @can('is-owner')
                        <x-nav-link :href="route('admin.owners.dashboard')" :active="request()->routeIs('admin.owners.dashboard')">
                            {{ __('Dashboard') }}
                        </x-nav-link>
                    @endcan

                    @can('is-tenant')
                        <x-nav-link :href="route('admin.tenants.dashboard')" :active="request()->routeIs('admin.tenants.dashboard')">
                            {{ __('Dashboard') }}
                        </x-nav-link>
                    @endcan

                    @can('agent-admin')
                        <x-nav-link :href="route('admin.owners.index')" :active="request()->routeIs('admin.owners.*') && !request()->routeIs('admin.owners.dashboard')">
                            {{ __('Owners') }}
                        </x-nav-link>
                    @endcan

                    @can('owner-admin')
                        <x-nav-link :href="route('admin.tenants.index')" :active="request()->routeIs('admin.tenants.*') && !request()->routeIs('admin.tenants.dashboard')">
                            {{ __('Tenants') }}
                        </x-nav-link>

                        <x-nav-link :href="route('admin.properties.index')" 
                            :active="request()->routeIs('admin.properties.*') || request()->routeIs('admin.units.*') || request()->routeIs('admin.rooms.*') || request()->routeIs('admin.roomAsset.*')">
                            {{ __('Properties') }}
                        </x-nav-link>
                    @endcan

                    <!--<x-nav-link :href="route('admin.rooms.index')" :active="request()->routeIs('admin.rooms.*')">
                        {{ __('Rooms') }}
                    </x-nav-link>-->

                    <x-nav-link :href="route('admin.leases.index')" :active="request()->routeIs('admin.leases.*', 'admin.agreements.*')">
                        {{ __('Leases') }}
                    </x-nav-link>

                    <x-nav-link :href="route('admin.payments.index')" :active="request()->routeIs('admin.payments.*')">
                        {{ __('Payments') }}
                    </x-nav-link>
                    
                    <!--<x-nav-link :href="route('admin.maintenance.index')" :active="request()->routeIs('admin.maintenance.*')">
                        {{ __('Maintenance') }}
                    </x-nav-link>-->

                    <!--@can('owner-admin')
                        <x-nav-link :href="route('admin.agreements.index')" :active="request()->routeIs('admin.agreements.*')">
                            {{ __('Agreement Templates') }}
                        </x-nav-link>
                    @endcan-->

                    <!--<x-nav-link :href="route('admin.customerService.index')" :active="request()->routeIs('admin.customerService.*')">
                        {{ __('Contact Us') }}
                    </x-nav-link>-->

                    @can('super-admin')
                        <x-nav-link :href="route('admin.packages.index')" :active="request()->routeIs('admin.packages.*')">
                            {{ __('Packages') }}
                        </x-nav-link>
                    @endcan

                    @can('super-admin')
                        <x-nav-link :href="route('admin.userManagement.index')" :active="request()->routeIs('admin.userManagement.*')">
                            {{ __('Users') }}
                        </x-nav-link>
                    @endcan
                    <!--<x-nav-link :href="route('admin.staff.index')" :active="request()->routeIs('admin.staff.*')">
                        {{ __('Staff') }}
                    </x-nav-link>-->
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div x-data="{ 
                openSetting: false, 
                openBoost: false, 
                open: false, 
                boostData: { 
                    packageName: '{{ Auth::user()->user_management->package->name ?? "N/A" }}', 
                    maxLimit: {{ Auth::user()->user_management->package->max_lease_limit ?? 0 }}, 
                    balanceLimit: {{ 
                        (Auth::user()->user_management && Auth::user()->user_management->package) 
                        ? (Auth::user()->user_management->package->max_lease_limit - Auth::user()->user_management->extra_lease) 
                        : 0 
                    }},
                    extraPrice: {{ Auth::user()->user_management->package->extra_lease_price ?? 0 }} 
                } 
            }" class="hidden sm:flex sm:items-center sm:ms-6">

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>
                            
                            {{-- 
                            这里有一个技巧：你可以通过 x-bind 监听组件内部的 open 状态。
                            如果 Dropdown 提供了 Alpine 接口，你可以通过如下方式获取：
                            --}}
                            <div class="ms-1" x-data @click.stop="open = !open">
                                <svg class="fill-current h-4 w-4 transition-transform duration-300 ease-in-out" 
                                    :class="open ? 'rotate-90' : ''"
                                    :class="$refs.dropdownContent.classList.contains('hidden')" 
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">{{ __('Profile') }}</x-dropdown-link>

                        @can('owner-admin')
                            <div class="border-t border-gray-100"></div>

                            {{-- 2. 直接使用父级的 openSetting 控制显示 --}}
                            <div class="relative"> 
                                <div @click.stop="openSetting = !openSetting" 
                                    class="flex items-center justify-between w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer">
                                    <span>{{ __('Settings') }}</span>
                                    <svg class="w-4 h-4 ml-1 text-gray-400 transition-transform duration-200"
                                        :class="openSetting ? 'rotate-90' : ''"
                                        fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>

                                {{-- 3. 这里不再定义 x-data --}}
                                <div x-show="openSetting" 
                                    x-cloak
                                    x-transition 
                                    class="bg-gray-50 border-l-4 border-indigo-400">
                                    
                                    <x-dropdown-link :href="route('admin.agreements.create')" class="pl-8 py-2 text-xs">
                                        {{ __('+ Add Agreement') }}
                                    </x-dropdown-link>

                                    {{-- 直接触发父级的 openBoost --}}
                                    <button type="button" 
                                            @click.prevent="openBoost = true; openSetting = false;" 
                                            class="block w-full pl-8 py-2 text-xs text-left text-gray-700 hover:bg-gray-100">
                                        {{ __('+ Boost Lease Capacity') }}
                                    </button>
                                </div>
                            </div>
                        @endcan

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
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            @can('owner-admin')
                <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                    {{ __('Dashboard') }}
                </x-responsive-nav-link>
            @endcan

            @can('is-owner')
                <x-responsive-nav-link :href="route('admin.owners.dashboard')" :active="request()->routeIs('admin.owners.dashboard')">
                    {{ __('Dashboard') }}
                </x-responsive-nav-link>
            @endcan

            @can('is-tenant')
                <x-responsive-nav-link :href="route('admin.tenants.dashboard')" :active="request()->routeIs('admin.tenants.dashboard')">
                    {{ __('Dashboard') }}
                </x-responsive-nav-link>
            @endcan

            @can('agent-admin')
                <x-responsive-nav-link :href="route('admin.owners.index')" :active="request()->routeIs('admin.owners.*') && !request()->routeIs('admin.owners.dashboard')">
                    {{ __('Owners') }}
                </x-responsive-nav-link>
            @endcan

            @can('owner-admin')
                <x-responsive-nav-link :href="route('admin.tenants.index')" :active="request()->routeIs('admin.tenants.*') && !request()->routeIs('admin.tenants.dashboard')">
                    {{ __('Tenants') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('admin.properties.index')"
                    :active="request()->routeIs('admin.properties.*') || request()->routeIs('admin.units.*') || request()->routeIs('admin.rooms.*') || request()->routeIs('admin.roomAsset.*')">
                    {{ __('Properties') }}
                </x-responsive-nav-link>
            @endcan

            <x-responsive-nav-link :href="route('admin.leases.index')" :active="request()->routeIs('admin.leases.*') || request()->routeIs('admin.agreements.*')">
                {{ __('Leases') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('admin.payments.index')" :active="request()->routeIs('admin.payments.*')">
                {{ __('Payments') }}
            </x-responsive-nav-link>

            @can('super-admin')
                <x-responsive-nav-link :href="route('admin.packages.index')" :active="request()->routeIs('admin.packages.*')">
                    {{ __('Packages') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('admin.userManagement.index')" :active="request()->routeIs('admin.userManagement.*')">
                    {{ __('Users') }}
                </x-responsive-nav-link>
            @endcan
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
