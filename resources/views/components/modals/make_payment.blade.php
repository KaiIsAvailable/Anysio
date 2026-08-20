{{-- Anysio Subscription Payment Modal --}}
<div x-data="{ 
    openPayment: true, 
    shakePayment: false,
    isUploading: false 
}" x-init="$watch('openPayment', value => document.body.style.overflow = value ? 'hidden' : '')" x-cloak>

    @php
        $payment = $invoice->latestPayment;
        $actionUrl = route('admin.payments.store', [
            'invoice' => $invoice,
        ]);
    @endphp

    <template x-teleport="body">
        <div x-show="openPayment" class="fixed inset-0 z-[100] flex items-center justify-center p-4">

            {{-- 背景遮罩：修正变量名映射 --}}
            <div class="absolute inset-0">
                <x-ui.blur-overlay show="openPayment" {{-- 修正：对应上面的 openPayment --}}
                    onClose="shakePayment = true; setTimeout(() => shakePayment = false, 400)" {{-- 修正：对应 shakePayment
                    --}} zIndex="z-[100]" {{-- 确保遮罩在底层 --}} />
            </div>

            {{-- Modal 主体：修正 z-index 确保它在遮罩上面 --}}
            <div class="relative z-[102] bg-white border border-white/40 rounded-[2rem] shadow-[0_35px_100px_-15px_rgba(0,0,0,0.4)] max-w-xl w-full max-h-[80vh] flex flex-col overflow-hidden transition-all duration-300"
                x-show="openPayment" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0" :class="{ 'animate-shake': shakePayment }"
                @click.stop>

                {{-- Header --}}
                <div class="p-6 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-black text-slate-800">Subscription Payment</h3>
                        <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-bold">Invoice: <span
                                class="text-indigo-600">{{ $invoice?->invoice_no }}</span></p>
                    </div>
                </div>

                {{-- 情况 A：已经上传了收据，等待审核 --}}
                @if($payment && $payment->status === 'pending')
                    <div class="flex-1 flex items-center justify-center p-8">
                        <div class="w-full max-w-sm">
                            <div class="mx-auto w-20 h-20 rounded-full bg-amber-50 flex items-center justify-center">
                                <svg class="w-10 h-10 text-amber-500 animate-spin-slow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0"/>
                                </svg>
                            </div>
                            <h3 class="mt-6 text-xl font-bold text-slate-800">
                                Payment Submitted
                            </h3>
                            <p class="mt-3 text-sm text-slate-500 leading-relaxed">
                                Your receipt has been received successfully.
                                Our finance team is currently reviewing your payment.
                            </p>
                            <div class="mt-8 rounded-xl bg-slate-50 border border-slate-100">
                                <div class="flex justify-between px-5 py-4">
                                    <span class="text-sm text-slate-500">Status</span>
                                    <span class="inline-flex items-center gap-2 text-amber-600 font-semibold">
                                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                        Pending Review
                                    </span>
                                </div>
                                <div class="border-t border-slate-100"></div>
                                <div class="flex justify-between px-5 py-4">
                                    <span class="text-sm text-slate-500">
                                        Payment Method
                                    </span>
                                    <span class="text-sm font-semibold text-slate-700">
                                        Manual Transfer
                                    </span>
                                </div>
                            </div>
                            <p class="mt-6 text-xs text-slate-400">
                                Verification usually takes between
                                <span class="font-semibold">
                                    1–2 hours
                                </span>
                            </p>

                            {{-- Logout Button --}}
                            <div class="mt-8">
                                <x-form.form method="POST" action="{{ route('logout') }}">
                                    <x-form.secondary-button
                                        type="submit"
                                        class="w-full justify-center rounded-xl py-3">

                                        Logout
                                    </x-form.secondary-button>
                                </x-form.form>
                            </div>
                        </div>
                    </div>
                    @else
                    <x-form.form action="{{ $actionUrl }}" enctype="multipart/form-data" class="flex flex-col h-full overflow-hidden">
                        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">

                            {{-- Rejected Alert --}}
                            @if($payment && $payment->status === 'rejected')
                                <div class="rounded-2xl border border-rose-200 bg-gradient-to-r from-rose-50 to-red-50 p-4">
                                    <div class="flex gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-rose-100 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-7 4h14a2 2 0 001.73-3L13.73 4a2 2 0 00-3.46 0L3.27 16A2 2 0 005 19z"/> 
                                            </svg>
                                        </div>

                                        <div class="flex-1">
                                            <div class="font-semibold text-rose-700">Payment Verification Failed</div>
                                            <p class="text-sm text-rose-600 mt-1">{{ $payment->remark }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- STEP 1 --}}
                            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm">
                                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                                    <div>
                                        <div class="text-sm font-semibold text-slate-800">Step 1 · Review Invoice</div>
                                        <div class="text-xs text-slate-500">Verify the payment amount before transferring.</div>
                                    </div>

                                    <span class="px-3 py-1 rounded-full bg-indigo-50 text-indigo-600 text-xs font-semibold">
                                        {{ $invoice->items->count() }} Items
                                    </span>
                                </div>

                                <div class="px-5 py-3">
                                    @foreach($invoice->items as $item)
                                        <div class="flex justify-between py-2">
                                            <div>
                                                <div class="text-sm font-medium text-slate-700">
                                                    {{ $item->description }}
                                                </div>

                                                @if($item->feeType)
                                                    <div class="text-xs text-slate-400">
                                                        {{ $item->feeType->name }}
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="text-sm font-semibold text-slate-800">
                                                RM {{ number_format($item->amount / 100,2) }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="px-5 py-4 bg-slate-50 border-t border-slate-100 flex justify-between">
                                    <span class="font-semibold text-slate-700">Total</span>
                                    <span class="text-xl font-bold text-indigo-600">
                                        RM {{ number_format($invoice->total_amount/100,2) }}
                                    </span>
                                </div>
                            </div>

                            {{-- STEP 2 --}}
                            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm">
                                <div class="px-5 py-4 border-b border-slate-100">
                                    <div class="text-sm font-semibold text-slate-800">Step 2 · Scan & Pay</div>
                                    <div class="text-xs text-slate-500">Scan the QR using DuitNow or your banking app.</div>
                                </div>

                                <div class="py-5 flex flex-col items-center">
                                    <div class="rounded-2xl bg-white border border-slate-200 p-3">
                                        <img src="{{ asset('image/AnysioBankQR.jpeg') }}" class="w-40 h-40 object-contain">
                                    </div>

                                    <div class="mt-3 text-sm font-medium text-slate-700">DuitNow QR</div>
                                    <div class="text-xs text-slate-500">Complete the transfer before uploading your receipt.</div>

                                    {{-- Divider --}}
                                    <div class="w-full px-6 my-4">
                                        <div class="border-t border-slate-100"></div>
                                    </div>

                                    {{-- Manual Bank Transfer Details --}}
                                    <div class="w-full px-6 text-center">
                                        <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Or Transfer Via</div>
                                        <div class="bg-slate-50 border border-slate-100 rounded-xl p-3 text-left space-y-1">
                                            <div class="flex justify-between text-xs">
                                                <span class="text-slate-500">Bank:</span>
                                                <span class="font-medium text-slate-800">Maybank</span>
                                            </div>
                                            <div class="flex justify-between text-xs">
                                                <span class="text-slate-500">Account No:</span>
                                                <span class="font-semibold text-slate-800 tracking-wide">5580 4283 0633</span>
                                            </div>
                                            <div class="flex justify-between text-xs">
                                                <span class="text-slate-500">Recipient:</span>
                                                <span class="font-medium text-slate-800">Anysio Technologies</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4 text-xs text-slate-500">Complete the transfer before uploading your receipt.</div>
                                </div>
                            </div>

                            {{-- STEP 3 --}}
                            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm">
                                <div class="px-5 py-4 border-b border-slate-100">
                                    <div class="text-sm font-semibold text-slate-800">Step 3 · Submit Receipt</div>
                                    <div class="text-xs text-slate-500">Upload your payment proof for verification.</div>
                                </div>

                                <div class="p-5 space-y-5">
                                    <div>
                                        <x-form.input-label value="Receipt" class="text-xs text-slate-500 mb-2"/>
                                        <x-form.file-input name="attachment" :required="$invoice->total_amount > 0"/>
                                    </div>

                                    <div>
                                        <x-form.input-label value="Transaction Reference (Optional)" class="text-xs text-slate-500 mb-2"/>
                                        <x-form.text-input
                                            name="transaction_ref"
                                            value="{{ old('transaction_ref') }}"
                                            placeholder="e.g. TNG123456789"
                                            class="block w-full"/>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Footer --}}
                        <div class="flex-none border-t border-slate-200 bg-white">
                            {{-- Security Note --}}
                            <div class="px-6 py-3 bg-slate-50 border-b border-slate-100">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-emerald-500"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24">

                                        <path stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M5 13l4 4L19 7"/>

                                    </svg>

                                    <p class="text-xs text-slate-500">
                                        Your receipt will only be used for payment verification.
                                    </p>
                                </div>
                            </div>

                            {{-- Buttons --}}
                            <div class="p-5 flex flex-col-reverse sm:flex-row gap-3">
                                {{-- Logout / Cancel --}}
                                <x-form.form method="POST" action="{{ route('logout') }}" class="flex-1">
                                    <x-form.secondary-button type="submit" class="w-full justify-center rounded-xl py-3">
                                        Later
                                    </x-form.secondary-button>
                                </x-form.form>

                                {{-- Submit --}}
                                <div class="flex-1">
                                    <x-form.primary-button
                                        type="submit"
                                        loading="loading"
                                        class="w-full justify-center rounded-xl py-3">

                                        @if($payment && $payment->status === 'rejected')
                                            <span x-show="!loading">
                                                Submit New Receipt
                                            </span>
                                        @else
                                            <span x-show="!loading"> Submit Receipt </span>
                                        @endif

                                        <span x-show="loading" x-cloak>
                                            Uploading...
                                        </span>
                                    </x-form.primary-button>
                                </div>
                            </div>
                        </div>
                    </x-form.form>
                @endif
            </div>
        </div>
    </template>
</div>