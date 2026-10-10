<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <a href="{{ route('tenants.customerService.index') }}" class="inline-flex items-center text-sm font-semibold text-indigo-600 hover:text-indigo-700 mb-4">&larr; Back to Customer Service</a>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Ticket Details</h1>
                <p class="mt-2 text-sm text-gray-500">View the conversation and send messages about this ticket.</p>
            </div>
            <x-customer-service.chat
                :ticket="$ticket"
                :current-role="$currentRole"
                :send-url="route('tenants.customerService.reply', $ticket)"
                :read-url="route('tenants.customerService.show', $ticket)"
                :can-send="$canSend"
                :messages="$messages"
            />
        </div>
    </div>
</x-app-layout>
