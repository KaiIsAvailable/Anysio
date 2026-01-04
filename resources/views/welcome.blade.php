<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Anysio PMS System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center">
        
        <div class="mb-8 text-center">
            <h1 class="text-4xl font-bold text-indigo-600 mb-2">Anysio PMS System</h1>
            <p class="text-gray-500">Welcome to your Property Management Solution</p>
        </div>

        <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md border border-gray-100">
            <div class="space-y-4">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/dashboard') }}" 
                           class="block w-full text-center bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded-lg transition">
                            Go to Dashboard
                        </a>
                    @else
                        <h2 class="text-xl font-semibold mb-4 text-center">Get Started</h2>
                        
                        <a href="{{ route('login') }}" 
                           class="block w-full text-center bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded-lg transition">
                            Log In
                        </a>
                    @endauth
                @endif
            </div>
        </div>

        <footer class="mt-12 text-gray-400 text-sm">
            &copy; {{ date('Y') }} Anysio PMS. All rights reserved.
        </footer>
    </div>
</body>
</html>