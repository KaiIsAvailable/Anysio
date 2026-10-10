<div class="overflow-x-auto">
    <table class="w-full divide-y divide-gray-200 text-left">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Subject</th>
                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Owner</th>
                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Created</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white">
            @forelse($tickets as $ticket)
                <tr data-ticket-url="{{ route('tenants.customerService.show', $ticket) }}" tabindex="0" role="link" aria-label="{{ 'Open conversation: '.$ticket->subject }}" class="cursor-pointer hover:bg-indigo-50/60 transition-colors focus:bg-indigo-50">
                    <td class="px-6 py-5 text-sm font-semibold text-slate-900"><a href="{{ route('tenants.customerService.show', $ticket) }}" class="hover:text-indigo-700">{{ $ticket->subject }}</a></td>
                    <td class="px-6 py-5 text-sm text-slate-900">{{ $ticket->receiver?->name ?? 'Unavailable' }}</td>
                    <td class="px-6 py-5"><span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">{{ ucfirst($ticket->status) }}</span></td>
                    <td class="px-6 py-5 text-sm text-gray-500 whitespace-nowrap">{{ $ticket->created_at?->format('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">No tickets found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($tickets->hasPages())
    <div class="px-6 py-4 border-t border-gray-100">{{ $tickets->links() }}</div>
@endif
