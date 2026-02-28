<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>

<script>
    // 每 3 秒检查一次用户是否点击了邮件里的链接
    const checkInterval = setInterval(async () => {
        try {
            const response = await fetch('/email/verify/status'); // 指向你刚才写的接口
            const data = await response.json();

            if (data.verified) {
                // 如果后端返回已验证，直接刷新或跳转到 Dashboard
                clearInterval(checkInterval);
                window.location.href = "{{ route('dashboard') }}";
            }
        } catch (error) {
            console.error('Error checking verification status:', error);
        }
    }, 3000);
</script>
