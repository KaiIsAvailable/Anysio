@props([
    'ticket' => null,
    'currentRole',
    'sendUrl' => '',
    'readUrl' => '',
    'markReadUrl' => '',
    'canSend' => false,
    'canAttach' => true,
    'context' => '',
    'messages' => [],
    'limits' => config('customer_service'),
    'id' => 'ticket-chat',
    'embedded' => false,
])

@php
            $media = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov'];
            $accept = fn ($extensions) => implode(',', array_map(fn ($ext) => '.'.$ext, $extensions));
        @endphp

<section data-ticket-chat data-mark-read-url="{{ $markReadUrl }}" data-can-attach="{{ $canAttach ? 'true' : 'false' }}" data-empty="{{ $ticket ? 'false' : 'true' }}" data-current-role="{{ $currentRole }}" data-send-url="{{ $sendUrl }}" data-read-url="{{ $readUrl }}" data-can-send="{{ $canSend ? 'true' : 'false' }}" data-limits="{{ json_encode($limits) }}" class="bg-white rounded-2xl shadow-lg border border-gray-100 {{ $embedded ? 'h-full min-h-0 flex flex-col' : '' }}">
    <header class="flex shrink-0 items-start gap-2 border-b p-3 sm:gap-3 sm:p-5">
        @if($embedded)
            <button type="button" data-back-to-list aria-label="Back to conversations" title="Back to conversations" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-indigo-700 hover:bg-indigo-50 focus-visible:ring-2 focus-visible:ring-indigo-500 md:hidden">
                <svg aria-hidden="true" focusable="false" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5m7 7-7-7 7-7" />
                </svg>
            </button>
        @endif
        <h2 data-chat-subject class="min-w-0 flex-1 py-2 text-xl font-bold text-slate-900 break-words">{{ $ticket?->subject ?? 'Customer Service' }}</h2>
        <x-customer-service.ticket-status data-chat-status :status="$ticket?->status ?? ''" :hidden="!$ticket" class="mt-2" />
    </header>
    <p data-chat-context class="shrink-0 px-5 py-2 text-sm text-gray-600" @if(!$context) hidden @endif>{{ $context }}</p>
    <div data-chat-messages aria-live="polite" class="overflow-y-auto space-y-3 bg-gray-50 p-4 sm:p-6 {{ $embedded ? 'flex-1 min-h-0' : 'h-[55vh] min-h-64' }}">
        @php($previousDate = null)
        @php($unreadMessages = collect($messages)->filter(fn ($message) => ($message['incoming'] ?? false) && ($message['status'] ?? null) === 'unread'))
        @forelse($messages as $message)
            @php($startsDay = ($message['date_key'] ?? null) && $previousDate !== $message['date_key'])
            @php($startsUnread = $unreadMessages->isNotEmpty() && ($unreadMessages->first()['id'] ?? null) === ($message['id'] ?? null))
            @if($startsDay)
                <div @if($startsUnread) data-unread-divider @endif class="flex items-center gap-3 py-2" role="separator" aria-label="{{ $message['date_label'] }}">
                    <span class="h-px flex-1 bg-indigo-100"></span>
                    <span data-message-date="{{ $message['date_key'] }}" class="text-xs font-medium text-indigo-700">{{ $message['date_label'] }}</span>
                    @if($startsUnread)
                        <span class="text-xs font-medium text-indigo-700">{{ $unreadMessages->count() }} unread messages</span>
                    @endif
                    <span class="h-px flex-1 bg-indigo-100"></span>
                </div>
                @php($previousDate = $message['date_key'])
            @endif
            @php($own = ($message['own'] ?? false))
            @if($startsUnread && !$startsDay)
                <div data-unread-divider class="flex items-center gap-3 py-2" role="separator">
                    <span class="h-px flex-1 bg-indigo-100"></span>
                    <span class="text-xs font-medium text-indigo-700">{{ $unreadMessages->count() }} unread messages</span>
                    <span class="h-px flex-1 bg-indigo-100"></span>
                </div>
            @endif
            <div data-message-id="{{ $message['id'] ?? '' }}" data-incoming="{{ ($message['incoming'] ?? false) ? 'true' : 'false' }}" data-message-status="{{ $message['status'] ?? '' }}" class="flex {{ $own ? 'justify-end' : 'justify-start' }}">
                <article class="max-w-[85%] rounded-2xl px-4 py-3 text-slate-900 {{ $own ? 'bg-indigo-100' : 'bg-white border' }}">
                    <p class="mb-1 text-xs text-gray-500">{{ $own ? 'You' : ($message['sender_label'] ?? $message['sender_type']) }} · {{ $message['created_at'] }}</p>
                    @if($message['attachment_url'])
                        @if($message['extension'] === 'pdf')
                            <x-customer-service.pdf-attachment :url="$message['attachment_url']" :filename="$message['filename'] ?? 'Attachment.pdf'" />
                        @elseif(in_array($message['extension'], ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                            <img src="{{ $message['attachment_url'] }}" alt="Ticket attachment" loading="lazy" class="max-h-64 max-w-full rounded-lg">
                        @elseif(in_array($message['extension'], ['mp4', 'webm', 'mov']))
                            <video src="{{ $message['attachment_url'] }}" controls preload="metadata" class="max-h-64 max-w-full rounded-lg"></video>
                        @endif
                        <a href="{{ $message['attachment_url'] }}?download=1" class="inline-block mt-2 text-sm text-indigo-700 underline">Download {{ strtoupper($message['extension']) }} attachment</a>
                    @else
                        <p class="text-sm whitespace-pre-wrap break-words">{{ $message['text'] }}</p>
                    @endif
                    @if($own)
                        <p class="mt-1 text-right text-xs text-gray-500">{{ ($message['status'] ?? null) === 'read' ? 'Read' : (($message['status'] ?? null) === 'unread' ? 'Unread' : 'Read status unknown') }}</p>
                    @endif
                </article>
            </div>
        @empty
            <p class="{{ !$ticket ? 'h-full flex items-center justify-center' : 'text-center' }} text-sm text-gray-500">{{ $ticket ? 'No messages yet.' : 'Select a conversation' }}</p>
        @endforelse
    </div>
    <p data-chat-error role="alert" class="px-5 text-sm whitespace-pre-line text-red-700"></p>
    <p data-chat-closed @if($canSend || !$ticket) hidden @endif class="border-t p-5 text-sm text-gray-600">This conversation is read-only. You can view messages and download attachments.</p>
    <form data-chat-form @if(!$canSend && $ticket) hidden @endif action="{{ $sendUrl }}" method="POST" enctype="multipart/form-data" class="shrink-0 border-t p-3 space-y-3">
        @csrf

        <div data-attachment-previews class="flex gap-3 overflow-x-auto max-h-36" aria-live="polite"></div>
        <div class="relative flex items-end gap-2 rounded-2xl border border-indigo-100 bg-white p-2 shadow-sm">
            <div data-chat-attachment-menu id="{{ $id }}-attachments" hidden class="absolute bottom-full left-0 z-50 mb-2 w-64 max-w-[calc(100vw-3rem)] rounded-2xl border border-indigo-100 bg-white p-2 shadow-xl" aria-label="Add attachments">
                <button type="button" data-chat-pick="document" class="block w-full rounded-xl p-3 text-left text-sm font-medium text-indigo-700 hover:bg-indigo-50 focus:bg-indigo-50">Document</button>
                <button type="button" data-chat-pick="media" class="block w-full rounded-xl p-3 text-left text-sm font-medium text-indigo-700 hover:bg-indigo-50 focus:bg-indigo-50">Photos &amp; Videos</button>
                <button type="button" data-chat-pick="camera" class="block w-full rounded-xl p-3 text-left text-sm font-medium text-indigo-700 hover:bg-indigo-50 focus:bg-indigo-50">Camera</button>
                <p class="border-t p-3 text-xs text-gray-500">Images: {{ $limits['types']['jpg']['max_kb'] / 1024 }} MB; videos: {{ $limits['types']['mp4']['max_kb'] / 1024 }} MB; documents: {{ $limits['types']['pdf']['max_kb'] / 1024 }} MB each. Up to {{ $limits['max_files'] }} files, {{ $limits['max_total_kb'] / 1024 }} MB total.</p>
            </div>
            <button type="button" data-chat-attach @if(!$canAttach) hidden @endif @disabled(!$ticket) aria-label="Add attachments" aria-controls="{{ $id }}-attachments" aria-expanded="false" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-indigo-600 hover:bg-indigo-50 focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-50">
                <svg aria-hidden="true" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 11-8.5 8.5a6 6 0 0 1-8.5-8.5l9-9a4 4 0 0 1 5.7 5.7l-9 9a2 2 0 0 1-2.8-2.8L15 6"/></svg>
            </button>
            <label class="sr-only" for="{{ $id }}-message">Message</label>
            <textarea id="{{ $id }}-message" data-chat-draft @disabled(!$ticket) rows="1" maxlength="{{ $limits['max_text_length'] }}" placeholder="Write a message..." class="min-w-0 flex-1 min-h-11 max-h-40 resize-none overflow-y-auto border-0 bg-white py-3 text-base leading-5 focus:ring-0"></textarea>
            <button type="submit" data-chat-send disabled aria-label="Send message" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-40">
                <span data-chat-send-icon><svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z M22 2 11 13"/></svg></span>
                <span data-chat-loading hidden><svg aria-hidden="true" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="9" opacity=".25"/><path d="M12 3a9 9 0 0 1 9 9"/></svg></span>
            </button>
        </div>
        <input data-chat-files="document" @disabled(!$ticket) type="file" multiple hidden accept="{{ $accept(array_values(array_diff(array_keys($limits['types']), $media))) }}">
        <input data-chat-files="media" @disabled(!$ticket) type="file" multiple hidden accept="{{ $accept($media) }}">
        <input data-chat-files="camera" @disabled(!$ticket) type="file" hidden accept="image/*" capture="environment">
    </form>
</section>
