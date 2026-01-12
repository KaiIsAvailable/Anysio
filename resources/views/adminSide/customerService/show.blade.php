<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Ticket Details') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <div class="mb-6 border-b pb-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <h1 class="text-2xl font-bold mb-2">{{ $ticket->subject }}</h1>
                                <div class="flex gap-4 text-sm text-gray-600">
                                    <span><strong>Ticket ID:</strong> <code class="bg-gray-100 px-2 py-1 rounded">{{ $ticket->id }}</code></span>
                                    <span><strong>Category:</strong>
                                        <span class="px-2 py-1 rounded bg-blue-100 text-blue-800">{{ $ticket->category }}</span>
                                    </span>
                                    <span><strong>Status:</strong>
                                        <span class="px-2 py-1 rounded {{ $ticket->status === 'sent' ? 'bg-yellow-100 text-yellow-800' : ($ticket->status === 'in_progress' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800') }}">
                                            {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                        </span>
                                    </span>
                                </div>
                                <div class="mt-2 text-sm text-gray-600">
                                    <span><strong>Sender:</strong> {{ $ticket->sender->name }} ({{ ucfirst($ticket->sender->role) }})</span>
                                    <span class="ml-4"><strong>Receiver:</strong>
                                        @if($ticket->receiver)
                                            {{ $ticket->receiver->name }} ({{ ucfirst($ticket->receiver->role) }})
                                        @else
                                            <span class="text-gray-400">Not assigned yet</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="mt-2 text-sm text-gray-500">
                                    <span><strong>Created:</strong> {{ $ticket->created_at->format('Y-m-d H:i:s') }}</span>
                                    @if($ticket->updated_at != $ticket->created_at)
                                        <span class="ml-4"><strong>Last Updated:</strong> {{ $ticket->updated_at->format('Y-m-d H:i:s') }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-col gap-2 items-end">
                                @if(Auth::user()->role === 'admin' && !$ticket->receiver)
                                    <form action="{{ route('admin.customerService.grab', $ticket->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                                            Grab This Ticket
                                        </button>
                                    </form>
                                @endif
                                <a href="{{ route('admin.customerService.index') }}" class="text-indigo-600 hover:text-indigo-900">
                                    &larr; Back to Tickets
                                </a>
                            </div>
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold mb-4">Conversation</h3>
                    <div class="space-y-3 mb-6 max-h-96 overflow-y-auto">
                        @forelse($ticket->messages as $msg)
                            <div class="flex {{ $msg->sender_type == 'ownerAdmin' ? 'justify-end' : 'justify-start' }}">
                                <div class="bg-white border border-gray-300 rounded-lg p-4 shadow-sm max-w-lg">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="text-xs font-bold {{ $msg->sender_type == 'ownerAdmin' ? 'text-indigo-600' : 'text-gray-700' }}">
                                            {{ $msg->sender_type == 'ownerAdmin' ? 'Owner Admin' : ucfirst($msg->sender_type) }}
                                        </span>
                                        <span class="text-xs text-gray-500">
                                            {{ $msg->created_at->format('d M Y, H:i') }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-900 mb-2">{{ $msg->message }}</p>
                                    <div class="text-xs text-gray-400">
                                        Message ID: {{ substr($msg->id, 0, 8) }}...
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-center text-gray-500">No messages yet.</p>
                        @endforelse
                    </div>

                    <hr class="my-6">
                    <h3 class="text-lg font-bold mb-2">Reply to this Ticket</h3>

                    <form action="{{ route('admin.customerService.update', $ticket->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <textarea name="message" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Type your reply here..." required></textarea>

                        <div class="mt-3 flex justify-end">
                            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700">
                                Send Reply
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>