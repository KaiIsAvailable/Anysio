@props([
    'isAdvanceSearch' => false,
    'withDate' => false,
    'showTenantField' => false,
    'showOwnerField' => false,
    'showleaseableField' => false,
    'showStatusField' => false,
    'showStampingStatusField' => false,
    'showChargeField' => false,
    'tenants' => [],
    'properties' => [],
])

<div x-data="{ 
        advancedOpen: false,
        leaseable: '{{ request('leaseable') }}',
        owner: '{{ request('owner') }}',
        tenant: '{{ request('tenant') }}',
        start_date: '{{ request('start_date') }}',
        end_date: '{{ request('end_date') }}',
        min_amount: '{{ request('min_amount') }}',
        max_amount: '{{ request('max_amount') }}',
        status: '{{ request('status') }}',
        stampingStatus: '{{ request('stampingStatus') }}',

        resetFilters() {
            this.leaseable = '';
            this.owner = '';
            this.tenant = '';
            this.start_date = '';
            this.end_date = '';
            this.min_amount = '';
            this.max_amount = '';
            this.status = '';
            this.stampingStatus = '';

            // Clear all Flatpickr instances found in the container
            this.$el.querySelectorAll('input').forEach(el => {
                if (el._flatpickr) {
                    el._flatpickr.clear();
                }
            });
        }
    }" class="relative w-full">
    <!-- Main Search Bar Row -->
    <div class="flex items-center justify-between gap-3 w-full">
        <!-- Search Input & Submit Button Group -->
        <div class="flex items-center w-full sm:w-[460px]" x-data="{ loadings: false, search: '{{ request('search') }}' }">
            <!-- Search Input with Icon -->
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 z-20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input 
                    type="text" 
                    name="search" 
                    x-model="search" 
                    placeholder="{{ $attributes->get('placeholder', 'Search...') }}"
                    @keydown.enter.prevent="
                        loadings = true; 
                        $el.closest('form').submit();
                    "
                    class="w-full h-10 pl-9 pr-8 text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-l-xl rounded-r-none shadow-sm focus:z-10 relative"
                >

                <!-- Clear "X" Button -->
                <button 
                    type="button"
                    x-show="search.length > 0"
                    @click="
                        search = ''; 
                        $el.previousElementSibling.value = '';
                        loadings = true; 
                        $nextTick(() => {
                            $el.closest('form').submit();
                        });
                    "
                    class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400 hover:text-gray-600 z-20 cursor-pointer"
                    style="display: none;"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Search Button -->
            <x-form.primary-button 
                type="button"
                loading="loadings"
                @click="
                    loadings = true; 
                    $el.closest('form').submit();
                "
                class="!rounded-l-none !rounded-r-xl shadow-sm focus:z-10 relative h-10 px-5 !py-0 flex items-center justify-center whitespace-nowrap"
            >
                Search
            </x-form.primary-button>
        </div>
        
        <!-- Advanced Search Filter Button -->
        @if($isAdvanceSearch)
            <button 
                type="button" 
                @click="advancedOpen = !advancedOpen" 
                class="inline-flex items-center h-10 px-4 bg-white border border-gray-300 rounded-xl text-xs font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-sm transition-all whitespace-nowrap"
            >
                <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                Filters
                <!-- Animated Arrow Icon -->
                <svg 
                    class="w-3.5 h-3.5 ml-1.5 text-gray-500 transition-transform duration-200" 
                    :class="advancedOpen ? 'rotate-180' : ''" 
                    fill="none" 
                    stroke="currentColor" 
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
        @endif
    </div>

    <!-- Advanced Filter Floating Dropdown Panel with Animation -->
    @if($isAdvanceSearch)
        <div 
            x-show="advancedOpen" 
            x-cloak 
            @click.away="advancedOpen = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-1 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-1 scale-95"
            class="absolute right-0 mt-2 p-6 bg-white rounded-2xl border border-gray-200 shadow-2xl z-50 w-[800px] max-w-[95vw]"
        >
            <!-- Form Header -->
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Advanced Filters</h3>
                <!--<button type="button" @click="advancedOpen = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>-->
            </div>

            <!-- Form Body Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Property Field -->
                @if($showleaseableField)
                    <div>
                        <x-form.input-label value="Property / Unit / Room" class="mb-1.5 text-sm font-bold uppercase" />
                        <x-form.text-input 
                            type="text" 
                            name="leaseable"
                            x-model="leaseable"
                            placeholder="Search property / unit / room name..." 
                            class="w-full text-sm h-10" 
                        />
                    </div>
                @endif

                <!-- Owner Field -->
                @if($showOwnerField)
                    <div>
                        <x-form.input-label value="Owner" class="mb-1.5 text-sm font-bold uppercase" />
                        <x-form.text-input 
                            type="text" 
                            name="owner"
                            x-model="owner"
                            placeholder="Search owner name..." 
                            class="w-full text-sm h-10" 
                        />
                    </div>
                @endif

                <!-- Tenant Field -->
                @if($showTenantField)
                    <div>
                        <x-form.input-label value="Tenant" class="mb-1.5 text-sm font-bold uppercase" />
                        <x-form.text-input 
                            type="text" 
                            name="tenant" 
                            x-model="tenant"
                            placeholder="Search tenant name..." 
                            class="w-full text-sm h-10" 
                        />
                    </div>
                @endif
                
                <!-- Duration / Date Range -->
                @if($withDate)
                    <div class="sm:col-span-2">
                        <x-form.input-label value="Duration" class="mb-1.5 text-sm font-bold uppercase text-gray-700" />
                        <div class="grid grid-cols-1 sm:grid-cols-[1fr,auto,1fr] gap-2 items-center">
                            <div>
                                <x-form.date-input id="start-date" name="start_date" x-model="start_date"/>
                                <x-form.input-error :messages="$errors->get('start_date')" class="mt-1" />
                            </div>
                            <span class="text-sm font-medium text-gray-400 text-center">to</span>
                            <div>
                                <x-form.date-input id="end-date" name="end_date" x-model="end_date"/>
                                <x-form.input-error :messages="$errors->get('end_date')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Status Field -->
                @if($showStatusField)
                    <div>
                        <x-form.input-label value="Status" class="mb-1.5 text-sm font-bold uppercase" />
                        <x-form.input-select
                            name="status"
                            id="lease-status"
                            x-model="status"
                            :options="[
                                'New' => 'New',
                                'Renew' => 'Renew',
                                'Pending Renewal' => 'Pending Renewal',
                                'End' => 'End',
                                'Check Out' => 'Check Out',
                                'Cancelled' => 'Cancelled',
                            ]"
                            ::value="old('status', request('status', ''))"
                        />
                    </div>
                @endif

                <!-- Amount Range Field -->
                @if($showChargeField)
                    <div class="sm:col-span-2">
                        <x-form.input-label value="Charges (RM)" class="mb-1.5 text-sm font-bold uppercase text-gray-700" />
                        <div class="grid grid-cols-1 sm:grid-cols-[1fr,auto,1fr] gap-2 items-center">
                            <div>
                                <x-form.text-input 
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')" 
                                    inputmode="numeric"
                                    @wheel="$event.preventDefault()"
                                    name="min_amount"
                                    x-model="min_amount"
                                    placeholder="Min (e.g. 0.00)" 
                                    class="w-full text-sm h-[38px]" 
                                />
                                <x-form.input-error :messages="$errors->get('min_amount')" class="mt-1" />
                            </div>
                            <span class="text-sm font-medium text-gray-400 text-center">to</span>
                            <div>
                                <x-form.text-input 
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')" 
                                    inputmode="numeric"
                                    @wheel="$event.preventDefault()"
                                    name="max_amount"
                                    x-model="max_amount"
                                    placeholder="Max (e.g. 1000.00)" 
                                    class="w-full text-sm h-[38px]" 
                                />
                                <x-form.input-error :messages="$errors->get('max_amount')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Stamping Status -->
                @if($showStampingStatusField)
                    <div>
                        <x-form.input-label value="Stamping Status" class="mb-1.5 text-sm font-bold uppercase" />
                        <x-form.input-select
                            name="stampingStatus"
                            id="stamping-status"
                            x-model="stampingStatus"
                            :options="[
                                'Stamped' => 'Stamped',
                                'Pending Stamping' => 'Pending Stamping',
                                'No Need Stamping' => 'No Need Stamping',
                            ]"
                            ::value="old('stampingStatus', request('stampingStatus', ''))"
                        />
                    </div>
                @endif
            </div>

            <!-- Form Footer Actions -->
            <div class="flex items-center justify-end gap-2 pt-4 mt-5 border-t border-gray-100">
                <button 
                    type="button" 
                    @click="resetFilters()" 
                    class="px-4 py-2 text-sm font-semibold text-gray-600 hover:text-gray-800 transition-colors cursor-pointer"
                >
                    Reset
                </button>
                <x-form.primary-button type="submit" class="h-9 px-4 text-sm !py-0 flex items-center justify-center">
                    Apply Filters
                </x-form.primary-button>
            </div>
        </div>
    @endif
</div>