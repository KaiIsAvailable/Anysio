<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('System Settings') }}
        </h2>
    </x-slot>

    <div class="py-12" x-data="{ activeTab: 'lease' }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Navigation Tabs Header -->
            <div class="bg-white shadow sm:rounded-lg p-4 flex space-x-4 border-b border-gray-200">
                <button type="button" 
                        @click="activeTab = 'lease'" 
                        :class="activeTab === 'lease' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="py-2 px-1 border-b-2 font-medium text-sm transition">
                    {{ __('Lease Settings') }}
                </button>

                <!--<button type="button" 
                        @click="activeTab = 'invoice'" 
                        :class="activeTab === 'invoice' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="py-2 px-1 border-b-2 font-medium text-sm transition">
                    {{ __('Invoice Settings') }}
                </button>

                <button type="button" 
                        @click="activeTab = 'owner'" 
                        :class="activeTab === 'owner' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="py-2 px-1 border-b-2 font-medium text-sm transition">
                    {{ __('Owner Settings') }}
                </button>

                <button type="button" 
                        @click="activeTab = 'tenant'" 
                        :class="activeTab === 'tenant' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="py-2 px-1 border-b-2 font-medium text-sm transition">
                    {{ __('Tenant Settings') }}
                </button>

                <button type="button" 
                        @click="activeTab = 'other'" 
                        :class="activeTab === 'other' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="py-2 px-1 border-b-2 font-medium text-sm transition">
                    {{ __('Other Settings') }}
                </button>-->
            </div>

            <!-- TAB 1: LEASE SETTINGS -->
            <div x-show="activeTab === 'lease'">
                <x-form.form method="POST" 
                            action="{{ route('admin.settings.update') }}" 
                            class="space-y-6" 
                            x-data="{ 
                                editing: false, 
                                loading: false, 
                                enabled: {{ data_get($settings, 'late_penalty_config.is_active', false) ? 'true' : 'false' }} 
                            }"
                            @submit="loading = true">
                    @csrf
                    @method('PATCH')

                    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                        <div>
                            <section>
                                <header class="flex items-center justify-between">
                                    <div>
                                        <h2 class="text-lg font-medium text-gray-900">
                                            {{ __('Late Payment Penalty') }}
                                        </h2>
                                        <p class="mt-1 text-sm text-gray-600">
                                            {{ __('Configure automated or manual late penalty rules for overdue leases.') }}
                                        </p>
                                    </div>
                                    <!-- Lock/Edit Toggle Button -->
                                    <button type="button" 
                                            @click="editing = !editing"
                                            :class="editing ? 'bg-amber-50 text-amber-700 border-amber-300' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                            class="inline-flex items-center px-3 py-1.5 border text-sm font-medium rounded-md shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <span x-show="!editing">{{ __('Enable Edit') }}</span>
                                        <span x-show="editing" x-cloak>{{ __('Editing') }}</span>
                                    </button>
                                </header>

                                <div class="mt-6 space-y-6">
                                    <!-- Master Toggle -->
                                    <div class="flex items-center justify-between">
                                        <x-form.input-label for="penalty_active" :value="__('Collect Penalty')" info="{{ __('Enable or disable the collection of late payment penalties.') }}" />
                                        
                                        <input type="hidden" name="is_active[late_penalty_config]" value="0">
                                        
                                        <button type="button" 
                                                role="switch" 
                                                :disabled="!editing"
                                                :aria-checked="enabled" 
                                                @click="if(editing) enabled = !enabled"
                                                :class="[
                                                    enabled ? 'bg-indigo-600' : 'bg-gray-200',
                                                    !editing ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'
                                                ]"
                                                class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                            <span :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                                                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                                        </button>

                                        <input type="checkbox" id="penalty_active" name="is_active[late_penalty_config]" value="1" 
                                            x-model="enabled" 
                                            class="hidden">
                                    </div>

                                    <!-- Configuration Fields in a Grid Row Layout -->
                                    <div x-show="enabled" x-cloak class="space-y-4 border-t border-gray-100 pt-4">
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                                            <!-- Grace Period -->
                                            <div>
                                                <x-form.input-label for="grace_period_days" :value="__('Grace Period (Days)')" info="{!! __('Extra days allowed after the due date before late fees start accumulating. <br><br> 0 means start immediately. 3 means allow 3 extra days no penalty') !!}"/>
                                                <x-form.text-input id="grace_period_days" name="settings[late_penalty_config][grace_period_days]" type="number" class="block w-full" value="{{ $settings['late_penalty_config']['value']['grace_period_days'] }}" min="0" x-bind:disabled="!editing" />
                                            </div>

                                            <!-- Calculation Type -->
                                            <div>
                                                <x-form.input-label for="calculation_type" :value="__('Calculation Type')" info="{!! __('<b>Fixed Amount</b>: Every freqency increate fixed amount.<br>Example: Rent = RM100, Amount = RM10, Frequency = 1. Mean each day after due date add RM10. <br> First day after due = RM110, second day = RM120, etc. <br><br><b>Percentage</b>: Every frequency increases the penalty by a certain percentage. <br> Example: Rent = RM100, Percentage = 5%, Frequency = 1. Mean each day after due date add 5% of RM100. First day after due = RM105, second day = RM110, etc.') !!}"/>
                                                <x-form.input-select 
                                                    id="calculation_type" 
                                                    name="settings[late_penalty_config][calculation_type]" 
                                                    value="{{ $settings['late_penalty_config']['value']['calculation_type'] }}"
                                                    :options="[
                                                        ['value' => 'fixed', 'label' => __('Fixed Amount')],
                                                        ['value' => 'percentage', 'label' => __('Percentage (%)')]
                                                    ]"
                                                    class="mt-1 block w-full" 
                                                    x-bind:disabled="!editing"
                                                />
                                            </div>

                                            <!-- Frequency -->
                                            <div>
                                                <x-form.input-label for="frequency" :value="__('Penalty Frequency')" info="{!! __('Select how often the penalty will be applied. <br><br> Example: If Frequency = 1, penalty is applied daily. 2 means applied every 2 days.') !!}"/>
                                                <x-form.input-select 
                                                    id="frequency" 
                                                    name="settings[late_penalty_config][frequency]" 
                                                    value="{{ $settings['late_penalty_config']['value']['frequency'] }}"
                                                    :options="collect(range(1, 10))->map(fn($i) => ['value' => (string)$i, 'label' => (string)$i])->toArray()"
                                                    class="mt-1 block w-full" 
                                                    x-bind:disabled="!editing"
                                                />
                                            </div>

                                            <!-- Amount -->
                                            <div>
                                                <x-form.input-label for="amount" :value="__('Amount')" info="{!! __('Enter the amount of the penalty.') !!}"/>
                                                <div class="relative rounded-md shadow-sm">
                                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                                        <span class="text-gray-500 sm:text-sm">RM</span>
                                                    </div>
                                                    <x-form.text-input id="amount" name="settings[late_penalty_config][amount]" type="number" step="0.01" min="0" class="block w-full pl-12" value="{{ ($settings['late_penalty_config']['value']['amount'] ?? 0) / 100 }}" x-bind:disabled="!editing" />
                                                </div>
                                            </div>

                                            <!-- Maximum Amount -->
                                            <div>
                                                <x-form.input-label for="maximum_amount" :value="__('Maximum Amount')" info="{{ __('Enter the maximum amount for the penalty. 0 means no maximum.') }}"/>
                                                <div class="relative rounded-md shadow-sm">
                                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                                        <span class="text-gray-500 sm:text-sm">RM</span>
                                                    </div>
                                                    <x-form.text-input id="maximum_amount" name="settings[late_penalty_config][maximum_amount]" type="number" step="0.01" min="0" class="block w-full pl-12" value="{{ isset($settings['late_penalty_config']['value']['maximum_amount']) ? $settings['late_penalty_config']['value']['maximum_amount'] / 100 : '' }}" x-bind:disabled="!editing" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <!-- Save Button (Only shown or enabled when editing) -->
                            <div class="mt-6 flex items-center gap-4" x-show="editing" x-cloak>
                                <x-form.primary-button x-bind:disabled="!editing" loading="loading">{{ __('Save Lease Settings') }}</x-form.primary-button>
                            </div>
                        </div>
                    </div>
                </x-form.form>
            </div>

            <!-- TAB 2: INVOICE SETTINGS -->
            <div x-show="activeTab === 'invoice'" x-cloak>
                <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                        <div class="max-w-xl">
                            <h2 class="text-lg font-medium text-gray-900">{{ __('Invoice Configuration') }}</h2>
                            <p class="mt-1 text-sm text-gray-600">{{ __('Manage invoice generation terms, prefixes, and notes.') }}</p>
                            <p class="mt-1 text-sm text-gray-600">{{ __('Coming Soon...') }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <x-form.primary-button>{{ __('Save Invoice Settings') }}</x-form.primary-button>
                    </div>
                </form>
            </div>

            <!-- TAB 3: OWNER SETTINGS -->
            <div x-show="activeTab === 'owner'" x-cloak>
                <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                        <div class="max-w-xl">
                            <h2 class="text-lg font-medium text-gray-900">{{ __('Owner Settings') }}</h2>
                            <p class="mt-1 text-sm text-gray-600">{{ __('Configure default commission rates or payout rules for property owners.') }}</p>
                            <p class="mt-1 text-sm text-gray-600">{{ __('Coming Soon...') }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <x-form.primary-button>{{ __('Save Owner Settings') }}</x-form.primary-button>
                    </div>
                </form>
            </div>

            <!-- TAB 4: TENANT SETTINGS -->
            <div x-show="activeTab === 'tenant'" x-cloak>
                <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                        <div class="max-w-xl">
                            <h2 class="text-lg font-medium text-gray-900">{{ __('Tenant Settings') }}</h2>
                            <p class="mt-1 text-sm text-gray-600">{{ __('Configure portal access options and notification preferences for tenants.') }}</p>
                            <p class="mt-1 text-sm text-gray-600">{{ __('Coming Soon...') }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <x-form.primary-button>{{ __('Save Tenant Settings') }}</x-form.primary-button>
                    </div>
                </form>
            </div>

            <!-- TAB 5: OTHER SETTINGS -->
            <div x-show="activeTab === 'other'" x-cloak>
                <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                        <div class="max-w-xl">
                            <h2 class="text-lg font-medium text-gray-900">{{ __('Other Settings') }}</h2>
                            <p class="mt-1 text-sm text-gray-600">{{ __('Miscellaneous system-wide preferences.') }}</p>
                            <p class="mt-1 text-sm text-gray-600">{{ __('Coming Soon...') }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <x-form.primary-button>{{ __('Save Other Settings') }}</x-form.primary-button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>