<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Anysio') }}</title>
        <link rel="icon" type="image/x-icon" href="{{ asset('image/anysio_logo.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet"> <!-- Quill Editor Styles -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script> <!-- Quill Editor Script -->
        <script>window.currentUser = { name: "{{ auth()->user()->name }}" };</script>

        @vite([
            'resources/css/app.css',
            'resources/js/app.js', 
            'resources/js/tenants.js', 
            'resources/js/userManagement.js',
            'resources/js/room.js',
            'resources/js/agreementPreview.js',
        ])
        @stack('scripts')
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            @auth
                @php
                    $userMgmt = \App\Models\UserManagement::where('user_id', auth()->id())->first();
                    $latestPayment = \App\Models\UserPayment::where('user_id', auth()->id())
                                        ->where('payment_type', 'subscription')
                                        ->latest()
                                        ->first();
                    
                    // 如果不是 Admin 且状态不是 active，则必须支付
                    $mustPay = false;
                    if ($userMgmt && auth()->user()->role !== 'admin') {
                        $isNotActive = $userMgmt->subscription_status !== 'active';
                        $hasPrice = $userMgmt->package && (int)$userMgmt->package->price > 0;

                        if ($isNotActive && $hasPrice) {
                            $mustPay = true;
                        }
                    }
                @endphp
            @endauth

            <x-auth-session-status class="mb-4" :status="session('status')" />

            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main x-data="{ openPayment: @json($mustPay ?? false) }">
                @auth
                    @if($mustPay)
                        {{-- 强制显示 Modal --}}
                        <div x-init="openPayment = true"></div>
                        @include('components.make_payment', ['latestPayment' => $latestPayment])

                        {{-- 背景遮罩层：防止用户点击主界面内容 --}}
                        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 z-[40] backdrop-blur-sm"></div>
                    @endif
                @endauth

                {{ $slot }}
            </main>
        </div>

        <script>
            // 自动消失逻辑：5秒后自动向右滑出并移除
            window.addEventListener('DOMContentLoaded', function() {
                const toasts = document.querySelectorAll('[id^="toast-"]');
                toasts.forEach(toast => {
                    setTimeout(() => {
                        // 添加平滑消失动画
                        toast.style.transition = 'all 0.5s ease';
                        toast.style.opacity = '0';
                        toast.style.transform = 'translateX(100%)';
                        
                        // 动画结束后彻底移除元素
                        setTimeout(() => toast.remove(), 500);
                    }, 5000); // 5秒显示时间
                });
            });
        </script>
    </body>
</html>