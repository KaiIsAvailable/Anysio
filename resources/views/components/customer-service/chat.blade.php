@props([
    'ticket',
    'currentRole',
    'sendUrl',
    'readUrl',
    'canSend' => false,
    'messages' => [],
    'limits' => config('customer_service'),
    'id' => 'ticket-chat',
])

<section data-ticket-chat data-current-role="{{ $currentRole }}" data-send-url="{{ $sendUrl }}" data-read-url="{{ $readUrl }}" data-can-send="{{ $canSend ? 'true' : 'false' }}" data-limits="{{ json_encode($limits) }}" class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
    <header class="flex items-start justify-between gap-4 border-b p-5">
        <h2 data-chat-subject class="text-xl font-bold text-slate-900 break-words">{{ $ticket->subject }}</h2>
        <span data-chat-status class="shrink-0 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ $ticket->status }}</span>
    </header>
    <div data-chat-messages aria-live="polite" class="overflow-y-auto space-y-3 bg-gray-50 p-4 sm:p-6 h-[55vh] min-h-64">
        @forelse($messages as $message)
            @php($own = $message['sender_type'] === $currentRole)
            <div class="flex {{ $own ? 'justify-end' : 'justify-start' }}">
                <article class="max-w-[85%] rounded-2xl px-4 py-3 text-slate-900 {{ $own ? 'bg-indigo-100' : 'bg-white border' }}">
                    <p class="mb-1 text-xs text-gray-500">{{ $own ? 'You' : $message['sender_type'] }} · {{ $message['created_at'] }}</p>
                    @if($message['attachment_url'])
                        @if(in_array($message['extension'], ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                            <img src="{{ $message['attachment_url'] }}" alt="Ticket attachment" loading="lazy" class="max-h-64 max-w-full rounded-lg">
                        @elseif(in_array($message['extension'], ['mp4', 'webm', 'mov']))
                            <video src="{{ $message['attachment_url'] }}" controls preload="metadata" class="max-h-64 max-w-full rounded-lg"></video>
                        @endif
                        <a href="{{ $message['attachment_url'] }}?download=1" class="inline-block mt-2 text-sm text-indigo-700 underline">Download {{ strtoupper($message['extension']) }} attachment</a>
                    @else
                        <p class="text-sm whitespace-pre-wrap break-words">{{ $message['text'] }}</p>
                    @endif
                </article>
            </div>
        @empty
            <p class="text-center text-sm text-gray-500">No messages yet.</p>
        @endforelse
    </div>
    <p data-chat-error role="alert" class="px-5 text-sm whitespace-pre-line text-red-700"></p>
    <p data-chat-closed @if($canSend) hidden @endif class="border-t p-5 text-sm text-gray-600">This conversation is read-only. You can view messages and download attachments.</p>
    <form data-chat-form @if(!$canSend) hidden @endif action="{{ $sendUrl }}" method="POST" enctype="multipart/form-data" class="border-t p-4 space-y-3">
        @csrf
        <label class="sr-only" for="{{ $id }}-message">Message</label>
        <textarea id="{{ $id }}-message" data-chat-draft rows="2" maxlength="{{ $limits['max_text_length'] }}" placeholder="Write a message..." class="w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
        <div data-attachment-previews class="flex gap-3 overflow-x-auto"></div>
        <div class="flex items-center justify-between gap-3">
            <div>
                <label for="{{ $id }}-files" class="block text-sm font-semibold text-gray-700">Attachments</label>
                <input id="{{ $id }}-files" data-chat-files type="file" multiple accept="{{ implode(',', array_map(fn ($ext) => '.'.$ext, array_keys($limits['types']))) }}" class="block max-w-full text-xs">
            </div>
            <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white">Send</button>
        </div>
        <p class="text-xs text-gray-500">Images and documents: {{ $limits['types']['jpg']['max_kb'] / 1024 }} MB each. Videos: {{ $limits['types']['mp4']['max_kb'] / 1024 }} MB each. Up to {{ $limits['max_files'] }} files and {{ $limits['max_total_kb'] / 1024 }} MB per send.</p>
    </form>
</section>
