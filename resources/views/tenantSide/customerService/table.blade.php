<nav aria-label="Ticket subjects" class="divide-y">
    @forelse($tickets as $ticket)
        <a href="{{ route(($routePrefix ?? 'tenants.customerService').'.index', ['ticket' => $ticket->id]) }}" data-ticket-id="{{ $ticket->id }}" class="block border-l-4 border-l-transparent border-t-gray-100 border-b-gray-100 p-4 hover:bg-indigo-50 focus-visible:ring-2 focus-visible:ring-indigo-500 aria-[current=true]:border-l-indigo-600 aria-[current=true]:bg-indigo-50" aria-current="{{ request('ticket') === $ticket->id ? 'true' : 'false' }}">
            <span class="block break-words text-sm font-semibold text-slate-900">{{ $ticket->subject }}</span>
            @if(!($canCreate ?? true))
                <span class="mt-1 block break-words text-xs text-slate-600">{{ $ticket->sourceLabel() }}</span>
            @endif
            <span class="mt-1 flex justify-between gap-2 text-xs text-gray-500"><span>{{ ucfirst($ticket->status) }}</span><span>{{ $ticket->created_at?->format('d M Y') }}</span></span>
        </a>
    @empty
        <p class="p-6 text-center text-sm text-gray-500">No tickets found.</p>
    @endforelse
</nav>
@if($tickets->hasPages())
    <div class="px-3 py-4 border-t border-gray-100">{{ $tickets->onEachSide(1)->links('pagination::simple-tailwind') }}</div>
@endif
