<section>
    <header class="mb-6">
        <h2 class="text-lg font-semibold text-gray-900">
            {{ __('Account Information') }}
        </h2>
        <p class="mt-1 text-sm text-gray-600">
            View your subscription details.
        </p>
    </header>

    @if($userManagement)
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-gray-200">

                <!-- Subscription Status -->
                <div class="p-6">
                    <p class="block uppercasefont-medium text-sm text-gray-700">
                        Subscription Status
                    </p>

                    @if($userManagement->subscription_status == 'active')
                        <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-700">
                            Active
                        </span>
                    @elseif($userManagement->subscription_status == 'expired')
                        <span class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-sm font-medium text-red-700">
                            Expired
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">
                            {{ ucfirst($userManagement->subscription_status) }}
                        </span>
                    @endif
                </div>

                <div class="p-6">
                    <p class="block uppercasefont-medium text-sm text-gray-700">
                        Start Date
                    </p>

                    <p class="text-lg font-semibold text-gray-900">
                        {{ !empty($userManagement->start_date) ? dateFormat($userManagement->start_date) : '' }}
                    </p>
                </div>

                <div class="p-6">
                    <p class="block uppercasefont-medium text-sm text-gray-700">
                        Expiry Date
                    </p>

                    <p class="text-lg font-semibold text-gray-900">
                        {{ !empty($userManagement->end_date) ? dateFormat($userManagement->end_date) : '' }}
                    </p>
                </div>

            </div>
        </div>
    @else
        <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-6">
            <p class="text-yellow-700 font-medium">
                No subscription information found.
            </p>
        </div>
    @endif
</section>