@props(['expiresAt', 'invoiceId'])
@php
    if (!session()->has('subscription_notice_login')) {
        session()->put('subscription_notice_login', (string) \Illuminate\Support\Str::uuid());
    }
    $noticeOptions = [
        'storageKey' => 'anysio.advance-payment.'.auth()->id().'.'.$invoiceId,
        'loginKey' => session('subscription_notice_login'),
    ];
@endphp
<aside x-data="advancePaymentNotice(@js($noticeOptions))" x-show="visible" x-cloak
    @pageshow.window="visible = true; init()"
    x-transition.opacity aria-label="{{ __('Advance payment reminder') }}"
    class="fixed bottom-4 left-4 right-4 z-[60] sm:left-auto sm:w-[26rem]">
    <div class="overflow-hidden rounded-xl border border-indigo-100 bg-white shadow-xl">
        <div x-show="expanded" id="advance-payment-notice-details" class="max-h-[calc(100dvh-2rem)] overflow-y-auto">
            <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-4 py-3">
                <div class="flex items-center gap-2 text-indigo-600">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8 16 2.5 2.5L16 13" />
                    </svg>
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('Advance payment available') }}</h2>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <button type="button" @click="collapse()" aria-label="{{ __('Minimize reminder for this login') }}" aria-controls="advance-payment-notice-details" :aria-expanded="expanded"
                        class="rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                    </button>
                    <button type="button" @click="close()" aria-label="{{ __('Close reminder') }}"
                        class="rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M6 18 18 6" /></svg>
                    </button>
                </div>
            </div>
            <div class="space-y-4 p-4">
                <p class="text-sm leading-6 text-gray-600">{{ __('You can make an advance payment for your subscription before it expires.') }}</p>
                <div role="status" class="flex items-center justify-between gap-3 rounded-lg bg-indigo-50 px-3 py-2.5 text-sm">
                    <span class="text-gray-600">{{ __('Subscription expires on') }}</span>
                    <time datetime="{{ $expiresAt->toDateString() }}" class="font-semibold text-indigo-700">{{ $expiresAt->format('d/m/Y') }}</time>
                </div>
                <x-form.primary-button type="button" class="w-full justify-center" @click="$dispatch('open-subscription-payment')">{{ __('Make Advance Payment') }}</x-form.primary-button>
                <label class="flex cursor-pointer items-start gap-2 text-xs leading-5 text-gray-500">
                    <x-form.checkbox class="mt-0.5 shrink-0" x-bind:checked="remember" @change="savePreference($event.target.checked)" />
                    <span>{{ __('Do not show again during this login') }}</span>
                </label>
            </div>
        </div>
        <button type="button" x-show="!expanded" @click="expand()" :aria-expanded="expanded" aria-controls="advance-payment-notice-details"
            class="flex w-full items-center justify-between gap-3 rounded-xl px-4 py-3 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500 hover:bg-indigo-50">
            <span class="text-xs text-gray-500">{{ __('Subscription expires on') }} <time datetime="{{ $expiresAt->toDateString() }}" class="font-semibold text-indigo-700">{{ $expiresAt->format('d/m/Y') }}</time></span>
            <svg class="h-4 w-4 shrink-0 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 15 6-6 6 6" /></svg>
        </button>
    </div>
</aside>
