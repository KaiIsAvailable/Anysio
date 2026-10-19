<nav aria-label="Ticket subjects" class="divide-y">
    @forelse($tickets as $ticket)
        <a href="{{ route(($routePrefix ?? 'tenants.customerService').'.index', ['ticket' => $ticket->id]) }}" data-ticket-id="{{ $ticket->id }}" class="block border-l-4 border-l-transparent border-t-gray-100 border-b-gray-100 p-4 hover:bg-indigo-50 focus-visible:ring-2 focus-visible:ring-indigo-500 aria-[current=true]:border-l-indigo-600 aria-[current=true]:bg-indigo-50" aria-current="{{ request('ticket') === $ticket->id ? 'true' : 'false' }}">
            <span class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-x-3 gap-y-1">
                <span class="min-w-0 [overflow-wrap:anywhere]">
                    <span class="block text-sm font-semibold text-slate-900">{{ $ticket->subject }}</span>
                    @if(!($canCreate ?? true))
                        <span class="mt-1 block text-xs text-slate-600">{{ $ticket->sourceLabel() }}</span>
                    @endif
                </span>
                <span data-ticket-unread @if(!$ticket->unread_count) hidden @endif aria-label="{{ $ticket->unread_count }} unread messages" class="col-start-2 row-start-1 inline-flex h-6 min-w-6 items-center justify-center justify-self-end rounded-full bg-indigo-600 px-1 text-xs font-semibold text-white [&[hidden]]:hidden">{{ $ticket->unread_count }}</span>
                @php($latestMessageAt = $ticket->messages_max_created_at ? \Illuminate\Support\Carbon::parse($ticket->messages_max_created_at) : $ticket->created_at)
                <span class="col-start-1 row-start-2 min-w-0 text-xs text-gray-500"><x-customer-service.ticket-status :status="$ticket->status" /></span>
                <span class="col-start-2 row-start-2 whitespace-nowrap text-right text-xs text-gray-500">{{ $latestMessageAt?->copy()->setTimezone('Asia/Kuala_Lumpur')->format('d M Y') }}</span>
            </span>
        </a>
    @empty
        <p class="p-6 text-center text-sm text-gray-500">No tickets found.</p>
    @endforelse
</nav>
@if($tickets->hasPages())
    <div class="px-3 py-4 border-t border-gray-100">{{ $tickets->onEachSide(1)->links('pagination::simple-tailwind') }}</div>
@endif
