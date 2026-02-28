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
                                        @if($ticket->status === 'sent')
                                            <span class="px-2 py-1 rounded bg-yellow-100 text-yellow-800">Sent</span>
                                        @elseif($ticket->status === 'in_progress')
                                            <span class="px-2 py-1 rounded bg-blue-100 text-blue-800">In Progress</span>
                                        @elseif($ticket->status === 'closed')
                                            <span class="px-2 py-1 rounded bg-gray-100 text-gray-800">Closed</span>
                                        @else
                                            <span class="px-2 py-1 rounded bg-green-100 text-green-800">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span>
                                        @endif
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
                                @if(Auth::user()->role === 'admin')
                                    <div class="flex gap-2">
                                        @if(!$ticket->receiver)
                                            <form action="{{ route('admin.customerService.grab', $ticket->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                                                    Grab This Ticket
                                                </button>
                                            </form>
                                        @endif
                                        @if($ticket->status !== 'closed')
                                            <form action="{{ route('admin.customerService.close', $ticket->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to close this ticket?');">
                                                @csrf
                                                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                                                    Close Ticket
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                                <a href="{{ route('admin.customerService.index') }}" class="text-indigo-600 hover:text-indigo-900">
                                    &larr; Back to Tickets
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold">Conversation</h3>
                        <span id="loading-indicator" class="text-xs text-gray-400 hidden">
                            <svg class="inline-block w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Checking for new messages...
                        </span>
                    </div>
                    <div id="messages-container" class="space-y-3 mb-6 max-h-96 overflow-y-auto" data-ticket-id="{{ $ticket->id }}">
                        @forelse($ticket->messages as $msg)
                            <div class="flex {{ $msg->sender_type == 'ownerAdmin' ? 'justify-end' : 'justify-start' }}" data-message-id="{{ $msg->id }}" data-message-timestamp="{{ $msg->created_at->toIso8601String() }}">
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
                            <p class="text-center text-gray-500" id="no-messages-placeholder">No messages yet.</p>
                        @endforelse
                    </div>

                    <hr class="my-6">

                    @if($ticket->status === 'closed')
                        <div class="bg-gray-100 border border-gray-300 rounded-lg p-6 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <h3 class="text-lg font-semibold text-gray-700 mb-2">This ticket is closed</h3>
                            <p class="text-sm text-gray-600">No further replies can be added to this ticket.</p>
                        </div>
                    @else
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
                    @endif

                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Real-time message polling with AJAX
        document.addEventListener('DOMContentLoaded', function() {
            const messagesContainer = document.getElementById('messages-container');

            // Exit if messages container doesn't exist (e.g., on index page)
            if (!messagesContainer) {
                console.log('[AJAX Poll] Messages container not found, skipping initialization');
                return;
            }

            const loadingIndicator = document.getElementById('loading-indicator');
            const noMessagesPlaceholder = document.getElementById('no-messages-placeholder');
            const ticketId = messagesContainer.dataset.ticketId;
            const ticketStatus = '{{ $ticket->status }}';
            let isPolling = false;
            let pollInterval = 3000; // Poll every 3 seconds

            // Get the timestamp of the last message
            function getLastMessageTimestamp() {
                const messages = messagesContainer.querySelectorAll('[data-message-timestamp]');
                if (messages.length === 0) return null;

                const lastMessage = messages[messages.length - 1];
                return lastMessage.dataset.messageTimestamp;
            }

            // Auto-scroll to bottom of messages
            function scrollToBottom(smooth = true) {
                messagesContainer.scrollTo({
                    top: messagesContainer.scrollHeight,
                    behavior: smooth ? 'smooth' : 'auto'
                });
            }

            // Create message HTML element
            function createMessageElement(msg) {
                const messageDiv = document.createElement('div');
                messageDiv.className = `flex ${msg.sender_type === 'ownerAdmin' ? 'justify-end' : 'justify-start'}`;
                messageDiv.setAttribute('data-message-id', msg.id);
                messageDiv.setAttribute('data-message-timestamp', msg.created_at_iso);

                const senderLabel = msg.sender_type === 'ownerAdmin' ? 'Owner Admin' : msg.sender_type.charAt(0).toUpperCase() + msg.sender_type.slice(1);
                const senderColorClass = msg.sender_type === 'ownerAdmin' ? 'text-indigo-600' : 'text-gray-700';

                messageDiv.innerHTML = `
                    <div class="bg-white border border-gray-300 rounded-lg p-4 shadow-sm max-w-lg animate-fadeIn">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-xs font-bold ${senderColorClass}">
                                ${senderLabel}
                            </span>
                            <span class="text-xs text-gray-500">
                                ${msg.created_at}
                            </span>
                        </div>
                        <p class="text-sm text-gray-900 mb-2">${escapeHtml(msg.message)}</p>
                        <div class="text-xs text-gray-400">
                            Message ID: ${msg.id.substring(0, 8)}...
                        </div>
                    </div>
                `;

                return messageDiv;
            }

            // Escape HTML to prevent XSS
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Fetch new messages from server
            async function fetchNewMessages() {
                if (isPolling) return; // Prevent concurrent polls

                isPolling = true;
                loadingIndicator.classList.remove('hidden');

                try {
                    const lastTimestamp = getLastMessageTimestamp();
                    const url = `/admin/ticket-messages/${ticketId}${lastTimestamp ? '?last_timestamp=' + encodeURIComponent(lastTimestamp) : ''}`;

                    console.log('[AJAX Poll] Checking for new messages...', {
                        ticketId: ticketId,
                        lastTimestamp: lastTimestamp,
                        url: url
                    });

                    const response = await fetch(url, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        }
                    });

                    if (!response.ok) {
                        console.error('[AJAX Poll] Response not OK:', response.status, response.statusText);
                        throw new Error('Failed to fetch messages');
                    }

                    const data = await response.json();
                    console.log('[AJAX Poll] Received data:', data);

                    // Add new messages to the DOM
                    if (data.count > 0) {
                        console.log(`[AJAX Poll] Adding ${data.count} new message(s) to DOM`);

                        // Remove "no messages" placeholder if it exists
                        if (noMessagesPlaceholder) {
                            noMessagesPlaceholder.remove();
                        }

                        data.messages.forEach(msg => {
                            const messageElement = createMessageElement(msg);
                            messagesContainer.appendChild(messageElement);
                            console.log('[AJAX Poll] Appended message:', msg.id);
                        });

                        // Auto-scroll to bottom
                        scrollToBottom();

                        // Optional: Play notification sound or show toast
                        console.log(`[AJAX Poll] ✓ ${data.count} new message(s) displayed`);
                    } else {
                        console.log('[AJAX Poll] No new messages');
                    }

                } catch (error) {
                    console.error('[AJAX Poll] Error fetching new messages:', error);
                } finally {
                    isPolling = false;
                    loadingIndicator.classList.add('hidden');
                }
            }

            // Start polling
            function startPolling() {
                console.log('[AJAX Poll] Initializing...', {
                    ticketId: ticketId,
                    ticketStatus: ticketStatus,
                    pollInterval: pollInterval + 'ms'
                });

                // Initial scroll to bottom on page load
                setTimeout(() => scrollToBottom(false), 100);

                // Only poll for new messages if ticket is not closed
                if (ticketStatus !== 'closed') {
                    console.log('[AJAX Poll] Starting polling every ' + (pollInterval / 1000) + ' seconds');
                    setInterval(fetchNewMessages, pollInterval);
                } else {
                    console.log('[AJAX Poll] Ticket is closed, polling disabled');
                }
            }

            // Clear textarea after form submission
            const replyForm = document.querySelector('form[action*="customerService"]');
            if (replyForm) {
                replyForm.addEventListener('submit', function() {
                    // The form will redirect, but we clear the textarea immediately for better UX
                    const textarea = replyForm.querySelector('textarea[name="message"]');
                    if (textarea) {
                        setTimeout(() => {
                            textarea.value = '';
                        }, 100);
                    }
                });
            }

            // Initialize polling
            startPolling();
        });
    </script>

    <style>
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fadeIn {
            animation: fadeIn 0.3s ease-out;
        }
    </style>
    @endpush
</x-app-layout>