<x-guest-layout>
    {{-- 
        注意：GuestLayout 内部通常自带一个居中的 div。
        为了完全匹配你原有的外观，我们在这里定义内容。
    --}}
    <div class="flex flex-col items-center justify-center">
        
        <div class="mb-8 text-center">
            <h1 class="text-4xl font-bold text-indigo-600 mb-2">Anysio PMS System</h1>
            <p class="text-gray-500">Welcome to your Property Management Solution</p>
        </div>

        <div class="w-full space-y-4">
            @if (Route::has('login'))
                @auth
                    <a href="{{ url('/dashboard') }}" 
                        class="block w-full text-center bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded-lg transition">
                        Go to Dashboard
                    </a>
                @else
                    <h2 class="text-xl font-semibold mb-4 text-center text-gray-800">Get Started</h2>
                    
                    <a href="{{ route('login') }}" 
                        class="block w-full text-center bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded-lg transition">
                        Log In
                    </a>
                @endauth
            @endif
        </div>

        <footer class="mt-12 text-gray-400 text-sm text-center">
            &copy; {{ date('Y') }} Anysio PMS. All rights reserved.
        </footer>
    </div>
</x-guest-layout>