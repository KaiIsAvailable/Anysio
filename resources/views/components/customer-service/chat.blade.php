@props([
    'ticket',
    'currentRole',
    'sendUrl',
    'readUrl',
    'canSend' => false,
    'messages' => [],
    'limits' => config('customer_service'),
    'id' => 'ticket-chat',
    'embedded' => false,
])

@php
            $media = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov'];
            $accept = fn ($extensions) => implode(',', array_map(fn ($ext) => '.'.$ext, $extensions));
        @endphp

<section data-ticket-chat data-current-role="{{ $currentRole }}" data-send-url="{{ $sendUrl }}" data-read-url="{{ $readUrl }}" data-can-send="{{ $canSend ? 'true' : 'false' }}" data-limits="{{ json_encode($limits) }}" class="bg-white rounded-2xl shadow-lg border border-gray-100 {{ $embedded ? 'h-full min-h-0 flex flex-col' : '' }}">
    <header class="flex items-start justify-between gap-4 border-b p-5">
        <h2 data-chat-subject class="text-xl font-bold text-slate-900 break-words">{{ $ticket->subject }}</h2>
        <span data-chat-status class="shrink-0 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ $ticket->status }}</span>
    </header>
    <div data-chat-messages aria-live="polite" class="overflow-y-auto space-y-3 bg-gray-50 p-4 sm:p-6 {{ $embedded ? 'flex-1 min-h-0' : 'h-[55vh] min-h-64' }}">
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
    <form data-chat-form @if(!$canSend) hidden @endif action="{{ $sendUrl }}" method="POST" enctype="multipart/form-data" class="shrink-0 border-t p-3 space-y-3">
        @csrf

        <div data-attachment-previews class="flex gap-3 overflow-x-auto max-h-36" aria-live="polite"></div>
        <div class="relative flex items-end gap-2 rounded-2xl border border-indigo-100 bg-white p-2 shadow-sm">
            <div data-chat-attachment-menu id="{{ $id }}-attachments" hidden class="absolute bottom-full left-0 z-50 mb-2 w-64 max-w-[calc(100vw-3rem)] rounded-2xl border border-indigo-100 bg-white p-2 shadow-xl" aria-label="Add attachments">
                <button type="button" data-chat-pick="document" class="block w-full rounded-xl p-3 text-left text-sm font-medium text-indigo-700 hover:bg-indigo-50 focus:bg-indigo-50">Document</button>
                <button type="button" data-chat-pick="media" class="block w-full rounded-xl p-3 text-left text-sm font-medium text-indigo-700 hover:bg-indigo-50 focus:bg-indigo-50">Photos &amp; Videos</button>
                <button type="button" data-chat-pick="camera" class="block w-full rounded-xl p-3 text-left text-sm font-medium text-indigo-700 hover:bg-indigo-50 focus:bg-indigo-50">Camera</button>
                <p class="border-t p-3 text-xs text-gray-500">Images/documents: {{ $limits['types']['jpg']['max_kb'] / 1024 }} MB each. Videos: {{ $limits['types']['mp4']['max_kb'] / 1024 }} MB each. Up to {{ $limits['max_files'] }} files, {{ $limits['max_total_kb'] / 1024 }} MB total.</p>
            </div>
            <button type="button" data-chat-attach aria-label="Add attachments" aria-controls="{{ $id }}-attachments" aria-expanded="false" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-indigo-600 hover:bg-indigo-50 focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-50">
                <svg aria-hidden="true" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 11-8.5 8.5a6 6 0 0 1-8.5-8.5l9-9a4 4 0 0 1 5.7 5.7l-9 9a2 2 0 0 1-2.8-2.8L15 6"/></svg>
            </button>
            <label class="sr-only" for="{{ $id }}-message">Message</label>
            <textarea id="{{ $id }}-message" data-chat-draft rows="1" maxlength="{{ $limits['max_text_length'] }}" placeholder="Write a message..." class="min-w-0 flex-1 min-h-11 max-h-40 resize-none overflow-y-auto border-0 bg-white py-3 text-base leading-5 focus:ring-0"></textarea>
            <button type="submit" data-chat-send disabled aria-label="Send message" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-40">
                <span data-chat-send-icon><svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z M22 2 11 13"/></svg></span>
                <span data-chat-loading hidden><svg aria-hidden="true" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="9" opacity=".25"/><path d="M12 3a9 9 0 0 1 9 9"/></svg></span>
            </button>
        </div>
        <input data-chat-files="document" type="file" multiple hidden accept="{{ $accept(array_values(array_diff(array_keys($limits['types']), $media))) }}">
        <input data-chat-files="media" type="file" multiple hidden accept="{{ $accept($media) }}">
        <input data-chat-files="camera" type="file" hidden accept="image/*" capture="environment">
    </form>
</section>
