<x-app-layout>
    <div class="flex flex-col items-center justify-center min-h-[60vh] text-center py-12">
        <h1 class="text-4xl font-bold text-gray-800 mb-2">Access Denied</h1>
        <p class="text-gray-600 mb-6">Sorry, you do not have the required permissions to view this page.</p>
        <a href="{{ route('welcome') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            Back to Welcome Page
        </a>
    </div>
</x-app-layout>