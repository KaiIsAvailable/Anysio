<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('System Settings') }}
        </h2>
    </x-slot>

    <div class="py-12" x-data="{ activeTab: new URLSearchParams(window.location.search).get('tab') || 'payment' }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Navigation Tabs Header -->
            <div class="bg-white shadow sm:rounded-lg p-4 flex space-x-4 border-b border-gray-200">
                @canany(['owner-admin', 'settings.payment tab'])
                <button type="button" 
                        @click="activeTab = 'payment'" 
                        :class="activeTab === 'payment' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="py-2 px-1 border-b-2 font-medium text-sm transition">
                    {{ __('Payment') }}
                </button>
                @endcanany

                @canany(['owner-admin', 'settings.lease tab'])
                <button type="button" 
                        @click="activeTab = 'lease'" 
                        :class="activeTab === 'lease' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="py-2 px-1 border-b-2 font-medium text-sm transition">
                    {{ __('Lease') }}
                </button>
                @endcanany

                @canany(['owner-admin', 'settings.user role tab'])
                <button type="button" 
                        @click="activeTab = 'permissions'" 
                        :class="activeTab === 'permissions' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-750 hover:border-gray-300'"
                        class="py-2 px-1 border-b-2 font-medium text-sm transition">
                    {{ __('User Role Permissions') }}
                </button>
                @endcanany

                <!--<button type="button" 
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
            <div x-show="activeTab === 'payment'" x-cloak>
                <x-form.form method="POST" 
                            action="{{ route('admin.settings.update') }}" 
                            class="space-y-6" 
                            x-data="{ 
                                editing: false, 
                                loading: false, 
                                enabled: {{ ($settings['late_penalty_config']['is_active'] ?? false) ? 'true' : 'false' }}
                            }"
                            @submit="loading = true">
                    @csrf
                    @method('PATCH')

                    <input type="hidden" name="tab" :value="activeTab">
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
                                    @canany(['owner-admin', 'settings.payment edit'])
                                    <x-form.section-edit-button state="editing" />
                                    @endcanany
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
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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
                                                    :value="data_get($settings, 'late_penalty_config.value.calculation_type')"
                                                    :options="[
                                                        ['value' => 'fixed', 'label' => __('Fixed Amount')],
                                                        //['value' => 'percentage', 'label' => __('Percentage (%)')]
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
                                                    :value="data_get($settings, 'late_penalty_config.value.frequency')"
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

                                            <!-- Applicable Fee Type Categories (Custom Multi-Select Component) -->
                                            <div>
                                                <x-form.input-label for="applicable_categories" :value="__('Fee Type Categories')" info="{{ __('Select which fee type categories this late penalty applies to.') }}"/>
                                                
                                                <div>
                                                    @php
                                                        $selectedCategories = $settings['late_penalty_config']['value']['applicable_categories'];
                                                    @endphp

                                                    <x-form.input-select 
                                                        id="applicable_categories"
                                                        name="settings[late_penalty_config][applicable_categories]"
                                                        :multiple="true"
                                                        :options="$feeTypeCategoryOptions"
                                                        :value="$selectedCategories"
                                                        x-bind:disabled="!editing"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <!-- Save Button (Only shown or enabled when editing) -->
                            @canany(['owner-admin', 'settings.payment edit'])
                            <div class="mt-6 flex items-center gap-4" x-show="editing" x-cloak>
                                <x-form.primary-button x-bind:disabled="!editing" loading="loading">{{ __('Save Lease Settings') }}</x-form.primary-button>
                            </div>
                            @endcanany
                        </div>
                    </div>
                </x-form.form>
            </div>

            <!-- TAB 2: LEASE SETTINGS -->
            <div x-show="activeTab === 'lease'" x-cloak class="space-y-6">

                <!-- 1. RECURRING INVOICE & DUE DATE FORM -->
                @canany(['owner-admin', 'settings.recurring invoice setting'])
                <x-form.form 
                    method="POST" 
                    action="{{ route('admin.settings.update') }}" 
                    class="space-y-6" 
                    x-data="{ loading: false, editingDueDate: false }" 
                    @submit="loading = true"
                >
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="tab" value="lease">

                    <div class="bg-white border border-gray-200 rounded-lg p-4 sm:p-6 shadow-sm space-y-6">
                        <header class="flex items-center justify-between">
                            <div>
                                <h3 class="text-md font-medium text-gray-950">{{ __('Recurring Invoice Settings') }}</h3>
                                <p class="mt-0.5 text-sm text-gray-600">{{ __('Configure automated recurring invoice generation and default payment terms.') }}</p>
                            </div>
                            @canany(['owner-admin', 'settings.edit recurring invoice'])
                            <x-form.section-edit-button state="editingDueDate" />
                            @endcanany
                        </header>

                        <div class="grid grid-cols-1 md:grid-cols-1 gap-6 pt-2">
                            <div class="p-4 border border-gray-100 bg-gray-50 rounded-lg space-y-2">
                                <x-form.input-label for="due_date_config[days]" :value="__('Due Date Days')" info="{!! __('Number of days given to tenants to settle invoices after issuance. <br><br> Example: The invoice will always be issued on the 1st of the month, if day = 7 mean that 1 + 7 = 8 so the due date will be the 8th of the month.') !!}"/>
                                <div class="flex items-center gap-2">
                                    <x-form.text-input type="number" 
                                        name="due_date_config[days]" 
                                        value="{{ data_get($settings, 'due_date_config.value.days') }}" 
                                        min="0" 
                                        max="365"
                                        class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-500"
                                        x-bind:disabled="!editingDueDate">
                                    </x-form.text-input>
                                    <span class="text-xs font-medium text-gray-600 shrink-0">Days</span>
                                </div>
                            </div>
                        </div>

                        @canany(['owner-admin', 'settings.edit recurring invoice'])
                        <div class="flex items-center gap-4 pt-2" x-show="editingDueDate" x-cloak>
                            <x-form.primary-button x-bind:disabled="!editingDueDate" loading="loading">
                                {{ __('Save Due Date Settings') }}
                            </x-form.primary-button>
                        </div>
                        @endcanany
                    </div>
                </x-form.form>
                @endcanany

                <!-- 2. PENDING RENEWAL SETTINGS FORM -->
                @canany(['owner-admin', 'settings.pending renewal setting'])
                <x-form.form 
                    method="POST" 
                    action="{{ route('admin.settings.update') }}" 
                    class="space-y-6" 
                    x-data="{ loading: false, editingPendingRenewal: false }" 
                    @submit="loading = true"
                >
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="tab" value="lease">

                    <div class="bg-white border border-gray-200 rounded-lg p-4 sm:p-6 shadow-sm space-y-6"
                        x-data="{
                            config: @js(
                                data_get($settings, 'pending_renewal_config.value') 
                                ?? data_get($settings, 'pending_renewal_config') 
                            ),
                            init() {
                                if (this.config.days === undefined) {
                                    this.config.days = this.calculateDays();
                                }
                            },
                            get number() { return this.config.number ?? 2; },
                            set number(val) { 
                                this.config.number = val; 
                                this.config.days = this.calculateDays(); 
                            },
                            get mode() { return this.config.mode ?? 'months'; },
                            set mode(val) { 
                                this.config.mode = val; 
                                this.config.days = this.calculateDays(); 
                            },
                            get days() { return this.config.days ?? this.calculateDays(); },
                            set days(val) { this.config.days = val; },
                            updateMode(newMode) {
                                this.mode = newMode;
                            },
                            calculateDays() {
                                let val = parseInt(this.config.number) || 0;
                                if (this.config.mode === 'months') return val * 30;
                                if (this.config.mode === 'years') return val * 365;
                                return val;
                            }
                        }">
                        
                        <header class="flex items-center justify-between">
                            <div>
                                <h3 class="text-md font-medium text-gray-950">{{ __('Pending Renewal Settings') }}</h3>
                                <p class="mt-0.5 text-sm text-gray-600">{{ __('Configure the notice period before lease expiry to flag leases as pending renewal.') }}</p>
                            </div>
                            @canany(['owner-admin', 'settings.edit pending renewal'])
                            <x-form.section-edit-button state="editingPendingRenewal" />
                            @endcanany
                        </header>

                        <div class="grid grid-cols-1 md:grid-cols-1 gap-6 pt-2">
                            <div class="p-4 border border-gray-100 bg-gray-50 rounded-lg space-y-4">
                                <x-form.input-label :value="__('Pending Renewal Notice Period')" info="{!! __('Period prior to lease end date when the system automatically shifts the lease status to pending renewal. <br><br> Example: <br> 3 months mean 90 days before lease end date, lease will be flagged as pending renewal.') !!}"/>
                                
                                <input type="hidden" name="pending_renewal_config[number]" x-bind:value="number">
                                <input type="hidden" name="pending_renewal_config[mode]" x-bind:value="mode">

                                <div class="flex items-center gap-2">
                                    <x-form.text-input type="number" 
                                        x-model="number" 
                                        x-bind:min="1" 
                                        x-bind:max="mode === 'days' ? 365 : (mode === 'months' ? 12 : null)"
                                        class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-500"
                                        x-bind:disabled="!editingPendingRenewal">
                                    </x-form.text-input>

                                    <div class="w-48 shrink-0">
                                        <x-form.input-select 
                                            id="pending_renewal_unit"
                                            name="pending_renewal_config[mode]"
                                            :value="data_get($settings, 'pending_renewal_config.mode', 'months')"
                                            @change="updateMode($event.detail ? $event.detail.value : $event.target.value)"
                                            :options="[
                                                ['value' => 'days', 'label' => __('Days (Max 365)')],
                                                ['value' => 'months', 'label' => __('Months (Max 12)')],
                                                ['value' => 'years', 'label' => __('Years')],
                                            ]"
                                            class="block w-full"
                                            x-bind:disabled="!editingPendingRenewal"
                                        />
                                    </div>
                                </div>
                                
                                <div class="space-y-1">
                                    <div class="flex items-center px-1">
                                        <p class="text-xs font-medium text-gray-900">
                                            <span x-text="days || 0"></span> Days
                                        </p>
                                        <input type="hidden" name="pending_renewal_config[days]" x-model="days">
                                    </div>
                                </div>
                            </div>
                        </div>

                        @canany(['owner-admin', 'settings.edit pending renewal'])
                        <div class="flex items-center gap-4 pt-2" x-show="editingPendingRenewal" x-cloak>
                            <x-form.primary-button x-bind:disabled="!editingPendingRenewal" loading="loading">
                                {{ __('Save Pending Renewal Settings') }}
                            </x-form.primary-button>
                        </div>
                        @endcanany
                    </div>
                </x-form.form>
                @endcanany

                <!-- 3. FEE TYPES SETTINGS FORM -->
                @canany(['owner-admin', 'settings.lease configuration setting'])
                <x-form.form 
                    method="POST" 
                    action="{{ route('admin.settings.update') }}" 
                    class="space-y-6" 
                    x-data="{ loading: false, editingFeeType: false }" 
                    @submit="loading = true"
                >
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="tab" value="lease">

                    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg space-y-6">
                         <header class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-medium text-gray-900">{{ __('Lease Configuration') }}</h2>
                                <p class="mt-1 text-sm text-gray-600">{{ __('Manage default lease charges and policies.') }}</p>
                            </div>
                            @canany(['owner-admin', 'settings.edit lease configuration'])
                            <x-form.section-edit-button state="editingFeeType" />
                            @endcanany
                        </header>

                        <div class="space-y-6">
                            <h3 class="text-md font-medium text-gray-900">{{ __('Adjust System Fee Types') }}</h3>
                            <p class="text-sm text-gray-600">{{ __('Toggle which fee types are active and allowed to be selected when creating leases or invoices.') }}</p>

                            <div class="space-y-6">
                                @php
                                    $allFeeTypesGroups = [
                                        'Rent' => $rentFeeTypes,
                                        'Deposit' => $depositFeeTypes,
                                        'Utility' => $utilityFeeTypes,
                                        'Service' => $serviceFeeTypes,
                                        'Penalty' => $penaltyFeeTypes,
                                        'Management' => $managementFeeTypes,
                                    ];
                                @endphp

                                @foreach($allFeeTypesGroups as $groupName => $feeTypes)
                                    @if($feeTypes->isNotEmpty())
                                        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">{{ $groupName }} Fees</h4>
                                            
                                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                                @foreach($feeTypes as $feeType)
                                                    @php
                                                        $slug = Str::slug(strtolower($feeType->category->value . '_' . $feeType->name), '_');
                                                        $isEnabled = filter_var(
                                                            data_get($settings, "fee_types_config.value.{$slug}.is_active", true), 
                                                            FILTER_VALIDATE_BOOLEAN
                                                        );
                                                        $isLatePenaltyFee = Str::contains($slug, 'late_payment_penalty');
                                                        $info = 'Controlled by the <b>Late Payment Penalty</b> setting.';
                                                    @endphp
                                                    <div class="flex items-center justify-between p-3 border border-gray-100 bg-gray-50 rounded-lg">
                                                        <div class="flex items-center justify-between w-full mr-2 min-w-0">
                                                            <div class="flex items-center space-x-1.5 min-w-0">
                                                                <span class="text-xs font-medium text-gray-800 truncate" title="{{ $feeType->name }}">{{ $feeType->name }}</span>
                                                                
                                                                @if($feeType->is_system)
                                                                    <span class="inline-block px-1.5 py-0.2 text-[10px] bg-gray-200 text-gray-600 rounded shrink-0">System</span>
                                                                @endif

                                                                @if($isLatePenaltyFee)
                                                                    <div class="relative flex items-center shrink-0" x-data="{ open: false }">
                                                                        <button type="button" 
                                                                            @mouseenter="open = true" 
                                                                            @mouseleave="open = false" 
                                                                            class="text-gray-400 hover:text-indigo-600 focus:outline-none">
                                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                                            </svg>
                                                                        </button>
                                                                        <div x-show="open" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-56 p-2 bg-gray-900 text-white text-xs rounded-md shadow-xl z-50 pointer-events-auto normal-case font-normal text-left [&>b]:font-bold">
                                                                            {!! $info !!}
                                                                        </div>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        <label class="relative inline-flex items-center shrink-0 transition-opacity"
                                                                :class="(!editingFeeType || @json($isLatePenaltyFee)) ? 'opacity-65 cursor-not-allowed' : 'cursor-pointer'">
                                                            <input type="hidden" name="fee_types_config[{{ $slug }}][is_active]" value="false">
                                                            <input type="checkbox" name="fee_types_config[{{ $slug }}][is_active]" value="true" 
                                                                class="sr-only peer" 
                                                                {{ $isEnabled ? 'checked' : '' }} 
                                                                x-bind:disabled="!editingFeeType @if($isLatePenaltyFee) || true @endif">
                                                            <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        <x-form.input-error :messages="$errors->get('charges')" class="mt-1" />

                        @canany(['owner-admin', 'settings.edit lease configuration'])
                        <div class="flex items-center gap-4" x-show="editingFeeType" x-cloak>
                            <x-form.primary-button x-bind:disabled="!editingFeeType" loading="loading">{{ __('Save Fee Type Settings') }}</x-form.primary-button>
                        </div>
                        @endcanany
                    </div>
                </x-form.form>
                @endcanany
            </div>

            <!-- TAB 3: USER ROLE SETTINGS -->
            @canany(['owner-admin', 'settings.user role tab'])
            <div>
                <div x-show="activeTab === 'permissions'" x-cloak class="space-y-6" 
                    x-data="{ 
                        roleTab: '{{ request('role_tab', $roles->first()?->name ?? '') }}', 
                        editingPermissions: false,
                        showRoleModal: false, 
                        newRoleName: '',
                        selectedPermissions: [],
                        isLoading: false,
                        errorMessage: '',
                        isShaking: false
                    }">              
                    <div class="bg-white border border-gray-200 rounded-lg p-4 sm:p-6 shadow-sm space-y-6">
                        
                        <!-- Header with Section Edit Button AND Create Role Button -->
                        <header class="flex items-center justify-between sticky top-0 z-20 bg-white py-3 border-b border-gray-100">
                            <div>
                                <h3 class="text-md font-medium text-gray-950">{{ __('User Role Permissions') }}</h3>
                                <p class="mt-0.5 text-sm text-gray-600">{{ __('Configure module actions and permissions for each user role.') }}</p>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <!-- Top Right Create Role Button -->
                                @canany(['owner-admin', 'settings.create new role'])
                                <x-form.primary-button type="button" @click="showRoleModal = true">
                                    {{ __('Create New Role') }}
                                </x-form.primary-button>
                                @endcanany
                                
                                <!-- Your existing section edit button -->
                                @canany(['owner-admin', 'settings.edit user role'])
                                <x-form.section-edit-button state="editingPermissions" />
                                @endcanany
                            </div>
                        </header>

                        <x-modals.staff-role-modal/>

                        @if($roles->isEmpty())
                            <!-- Empty State Message -->
                            <div class="border-t border-gray-100 pt-6 text-center space-y-3 py-8">
                                <div class="mx-auto w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900">{{ __('No roles found') }}</h4>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ __('Click "Create New Role" above to set up your first custom role.') }}</p>
                                </div>
                            </div>
                        @else
                            <form action="{{ route('admin.settings.user-role-permissions.update') }}" method="POST" class="space-y-6" x-data="{ loading: false }" @submit="loading = true">
                                @csrf
                                @method('PUT')

                                <input type="hidden" name="active_tab" value="permissions">
                                <input type="hidden" name="role_tab" x-model="roleTab">

                                <!-- Dynamic Role Navigation Tabs -->
                                <div class="border-b border-gray-200 bg-gray-50/70 px-4 pt-2 flex space-x-2 overflow-x-auto rounded-t-lg">
                                    @foreach($roles as $role)
                                        <button type="button"
                                                @click="roleTab = '{{ $role->name }}'"
                                                :class="roleTab === '{{ $role->name }}' ? 'border-indigo-600 text-indigo-600 bg-white shadow-sm' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                                class="whitespace-nowrap py-2.5 px-3 border-b-2 font-semibold text-xs rounded-t-lg transition-all capitalize flex items-center gap-2">
                                            
                                            <!-- Role Name -->
                                            <span>{{ $role->name }}</span>

                                            <!-- Small Red X Delete Button -->
                                            @canany(['owner-admin', 'settings.delete user role'])
                                            <span x-show="editingPermissions" x-cloak
                                                @click.stop="if(confirm('Are you sure you want to delete the \'{{ $role->name }}\' role?')) { 
                                                    document.getElementById('delete-role-form-{{ $role->id }}').submit(); 
                                                }"
                                                class="w-4 h-4 rounded-full bg-red-100 text-red-600 hover:bg-red-200 hover:text-red-700 flex items-center justify-center text-[10px] font-bold transition-colors cursor-pointer"
                                                title="Delete Role">
                                                &times;
                                            </span>
                                            @endcanany
                                        </button>
                                    @endforeach
                                </div>

                                <!-- Role Content Panels -->
                                @foreach($roles as $role)
                                    <div x-show="roleTab === '{{ $role->name }}'" x-cloak class="space-y-6 pt-2">
                                        <!-- Loop Through Modules from Config -->
                                        <div class="space-y-4">
                                            @foreach($modules as $moduleName => $actions)
                                                <div class="p-4 rounded-lg border border-gray-100 bg-gray-50 space-y-3"
                                                    x-data="{
                                                        permissionsState: {
                                                            @foreach($actions as $action)
                                                                @php
                                                                    $permissionName = "{$moduleName}.{$action}";
                                                                    $isChecked = $role->permissions->contains(fn($p) => $p->name === $permissionName && $p->guard_name === 'web');
                                                                @endphp
                                                                '{{ $action }}': {{ $isChecked ? 'true' : 'false' }},
                                                            @endforeach
                                                        },
                                                        get isAllChecked() {
                                                            const values = Object.values(this.permissionsState);
                                                            if (values.length === 0) return false;
                                                            return values.every(val => val === true);
                                                        },
                                                        set isAllChecked(value) {
                                                            if (!editingPermissions) return;
                                                            Object.keys(this.permissionsState).forEach(key => {
                                                                this.permissionsState[key] = value;
                                                            });
                                                        }
                                                    }">
                                                    
                                                    <!-- Module Header with Section Master Toggle -->
                                                    <div class="flex items-center justify-between pb-2 border-b border-gray-200/60">
                                                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider flex items-center gap-2">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                                                            {{ ucfirst($moduleName) }} Module
                                                        </h4>

                                                        <div class="flex items-center gap-2">
                                                            <span class="text-[10px] font-semibold text-gray-500 uppercase">Select All</span>
                                                            <label class="relative inline-flex items-center shrink-0 transition-opacity"
                                                                :class="!editingPermissions ? 'opacity-65 cursor-not-allowed' : 'cursor-pointer'">
                                                                <!-- Changed to x-model so the switch visually turns on/off reactively -->
                                                                <input type="checkbox" 
                                                                    x-model="isAllChecked"
                                                                    class="sr-only peer"
                                                                    x-bind:disabled="!editingPermissions">
                                                                <div class="w-8 h-4 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-indigo-600"></div>
                                                            </label>
                                                        </div>
                                                    </div>

                                                    <!-- Grid of Toggles for Actions -->
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                                                        @foreach($actions as $action)
                                                            @php
                                                                $permissionName = "{$moduleName}.{$action}";
                                                            @endphp
                                                            <div class="flex items-center justify-between p-3 bg-white rounded-lg border border-gray-100 shadow-sm">
                                                                
                                                                <!-- Added title="{{ $action }}" for native hover tooltip -->
                                                                <span class="text-xs font-medium text-gray-800 truncate capitalize mr-2" 
                                                                    title="{{ $action }}">
                                                                    {{ $action }}
                                                                </span>
                                                                
                                                                <!-- Toggle Button -->
                                                                <label class="relative inline-flex items-center shrink-0 transition-opacity"
                                                                    :class="!editingPermissions ? 'opacity-65 cursor-not-allowed' : 'cursor-pointer'">
                                                                    <input type="checkbox" 
                                                                        name="permissions[{{ $role->id }}][{{ $permissionName }}]" 
                                                                        value="1" 
                                                                        class="sr-only peer" 
                                                                        x-model="permissionsState['{{ $action }}']"
                                                                        x-bind:disabled="!editingPermissions">
                                                                    <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                                                </label>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach

                                <!-- Save Changes Sticky Bottom Bar (Visible only when editing) -->
                                @canany(['owner-admin', 'settings.edit user role'])
                                <div x-show="editingPermissions" x-cloak 
                                    class="sticky bottom-0 z-20 -mx-4 -mb-4 sm:-mx-6 sm:-mb-6 p-4 bg-white/90 backdrop-blur-md border-t border-gray-200 flex items-center justify-end shadow-lg transition-all">
                                    <div class="flex items-center gap-4">
                                        <span class="text-xs text-gray-500 font-medium hidden sm:inline">
                                            {{ __('You have unsaved changes.') }}
                                        </span>
                                        <x-form.primary-button x-bind:disabled="!editingPermissions" loading="loading">
                                            {{ __('Save Permission Settings') }}
                                        </x-form.primary-button>
                                    </div>
                                </div>
                                @endcanany
                            </form>

                            <!-- Hidden Delete Form with a unique ID matching the role -->
                            @foreach($roles as $role)
                                <form id="delete-role-form-{{ $role->id }}" action="{{ route('admin.roles.destroy', $role->id) }}" method="POST" class="hidden">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="active_tab" value="permissions">
                                </form>
                            @endforeach
                        @endif

                    </div>
                </div>
            </div>
            @endcanany

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