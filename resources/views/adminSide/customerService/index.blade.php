<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Customer Service') }}
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
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold">
                            @if(Auth::user()->role === 'ownerAdmin')
                                My Tickets
                            @elseif(Auth::user()->role === 'admin')
                                All Tickets
                            @else
                                Tickets
                            @endif
                        </h3>
                        @if(Auth::user()->role === 'ownerAdmin')
                            <button id="toggleButton" onclick="toggleCreateForm()" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                                Create New Ticket
                            </button>
                        @endif
                    </div>

                    @if(Auth::user()->role === 'ownerAdmin')
                    <div id="createTicketForm" class="hidden mb-6 p-4 bg-gray-50 rounded-lg">
                        <h4 class="text-md font-semibold mb-3">Submit a New Ticket</h4>

                        <form action="{{ route('admin.customerService.store') }}" method="POST">
                            @csrf

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block font-medium text-sm text-gray-700">Category</label>
                                    <select name="category" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                        <option value="System Problem">System Problem</option>
                                        <option value="Feedback">Feedback</option>
                                        <option value="Rating">Rating</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-medium text-sm text-gray-700">Subject</label>
                                    <input type="text" name="subject" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="block font-medium text-sm text-gray-700">Message</label>
                                <textarea name="message" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required></textarea>
                            </div>

                            <div class="mt-4 flex justify-end gap-2">
                                <button type="button" onclick="toggleCreateForm()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                                    Cancel
                                </button>
                                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                                    Submit Ticket
                                </button>
                            </div>
                        </form>
                    </div>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                                    @if(Auth::user()->role === 'ownerAdmin')
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Receiver</th>
                                    @elseif(Auth::user()->role === 'admin')
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Sender</th>
                                    @endif
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($tickets as $ticket)
                                <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('admin.customerService.show', $ticket->id) }}'">
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ substr($ticket->id, 0, 8) }}...</td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            {{ $ticket->category }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-medium">{{ $ticket->subject }}</td>
                                    @if(Auth::user()->role === 'ownerAdmin')
                                        <td class="px-6 py-4 text-sm">
                                            @if($ticket->receiver)
                                                <span class="text-gray-700 font-medium">{{ $ticket->receiver->name }}</span>
                                                <span class="text-xs text-gray-500">({{ ucfirst($ticket->receiver->role) }})</span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                    @elseif(Auth::user()->role === 'admin')
                                        <td class="px-6 py-4 text-sm">
                                            <span class="text-gray-700 font-medium">{{ $ticket->sender->name }}</span>
                                            <span class="text-xs text-gray-500">({{ ucfirst($ticket->sender->role) }})</span>
                                        </td>
                                    @endif
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $ticket->created_at->format('Y-m-d H:i') }}</td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            {{ $ticket->status === 'sent' ? 'bg-yellow-100 text-yellow-800' : ($ticket->status === 'in_progress' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800') }}">
                                            {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if(Auth::user()->role === 'admin' && !$ticket->receiver)
                                            <form action="{{ route('admin.customerService.grab', $ticket->id) }}" method="POST" onclick="event.stopPropagation()" class="inline">
                                                @csrf
                                                <button type="submit" class="bg-indigo-600 text-white px-3 py-1 rounded text-xs hover:bg-indigo-700 mr-2">
                                                    Grab
                                                </button>
                                            </form>
                                        @endif
                                        <a href="{{ route('admin.customerService.show', $ticket->id) }}"
                                           class="text-indigo-600 hover:text-indigo-900 font-medium text-sm"
                                           onclick="event.stopPropagation()">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                        No tickets found.
                                        @if(Auth::user()->role === 'ownerAdmin')
                                            Create your first ticket above.
                                        @endif
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        function toggleCreateForm() {
            const form = document.getElementById('createTicketForm');
            const button = document.getElementById('toggleButton');

            form.classList.toggle('hidden');

            if (form.classList.contains('hidden')) {
                button.textContent = 'Create New Ticket';
            } else {
                button.textContent = 'Collapse';
            }
        }
    </script>
</x-app-layout>