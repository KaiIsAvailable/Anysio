<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12" x-data="{ openPayment: false }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @php
                // 1. 检查 User Management 状态
                $userMgmt = \App\Models\UserManagement::where('user_id', auth()->id())->first();
                
                // 2. 获取支付记录（用于拿到金额和 Invoice No）
                $latestPayment = \App\Models\UserPayment::where('user_id', auth()->id())
                                    ->where('payment_type', 'subscription')
                                    ->latest()
                                    ->first();
                
                // 判断是否需要强制弹窗：状态是 pending 且不是 Admin (假设 Admin 角色叫 'admin')
                $mustPay = ($userMgmt && $userMgmt->subscription_status !== 'active' && auth()->user()->role !== 'admin');
            @endphp

            @if($mustPay)
                {{-- 自动开启 Modal 的 Alpine 逻辑 --}}
                <div x-init="openPayment = true"></div>

                {{-- 引入你刚才那个高颜值的 Payment Modal Component --}}
                @include('components.modals.make_payment', ['latestPayment' => $latestPayment])

            @else
                {{-- 只要 Controller 说是 true，这里就直接显示，省时省力 
                @if(isset($isProfileIncomplete) && $isProfileIncomplete)
                    <x-notification-banner 
                        type="warning" 
                        message="Your owner details is incomplete! Please update your Company Name, IC, Phone Number, and Gender to unlock full features." 
                        actionLabel="Complete Owner Details" 
                        :actionUrl="route('admin.owners.edit', auth()->user()->owner->id ?? 0)" 
                    />
                @endif--}}

                {{-- 情况 B：已经 Active，显示正常功能 --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        {{ __("Welcome back! Your dashboard is active.") }}
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>