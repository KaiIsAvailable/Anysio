@props(['statement', 'filters' => [], 'scopeLabel' => 'All records you can view'])

<section id="invoice-statement" class="bg-white overflow-hidden">
    <div class="p-6 border-b border-gray-100 space-y-3">
        <p class="text-sm text-slate-600">{{ $scopeLabel }}</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-left divide-y divide-gray-200">
            <thead class="bg-gray-50 text-xs uppercase text-slate-600">
                <tr>
                    <th class="px-4 py-3">Date / Time</th><th class="px-4 py-3">Record</th>
                    <th class="px-4 py-3">Related parties</th><th class="px-4 py-3">Invoice No. / Reference</th>
                    <th class="px-4 py-3">Fee Type / Description</th>
                    <th class="px-4 py-3 text-right">Amount (RM)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($statement as $entry)
                    <tr class="align-top" data-statement-event="{{ $entry['id'] }}">
                        <td class="px-4 py-4 whitespace-nowrap">
                            {{ $entry['date'] }}
                            @if($entry['date_only'])<div class="text-xs text-slate-500">Payment date</div>@endif
                            @if($entry['date_only'] && $entry['recorded_at'])<div class="text-xs text-slate-500">Recorded {{ $entry['recorded_at'] }}</div>@endif
                        </td>
                        <td class="px-4 py-4">
                            <div class="font-semibold">{{ $entry['label'] }}</div>
                            <div class="text-xs text-slate-500">{{ $entry['source_type'] }}</div>
                            @if($entry['payment_method'])<div class="text-xs">{{ $entry['payment_method'] }}</div>@endif
                        </td>
                        <td class="px-4 py-4">
                            <div class="text-xs text-slate-500">{{ $entry['party_label'] }}</div>
                            <div>{{ $entry['party'] }}</div>
                            @if($entry['payee'])<div class="text-xs text-slate-500 mt-2">Pay to (lease owner)</div><div>{{ $entry['payee'] }}</div>@endif
                            @if($entry['issuer'])<div class="text-xs text-slate-500 mt-2">Invoice issuer</div><div>{{ $entry['issuer'] }}</div>@endif
                        </td>
                        <td class="px-4 py-4 break-words">
                            <div class="font-semibold">{{ $entry['invoice_no'] ?? 'Not linked to an invoice' }}</div>
                            @if($entry['receipt_no'])<div class="text-xs">Receipt: {{ $entry['receipt_no'] }}</div>@endif
                            @if($entry['reference'])<div class="text-xs text-slate-500">Ref: {{ $entry['reference'] }}</div>@endif
                        </td>
                        <td class="px-4 py-4 min-w-[200px]">
                            @foreach($entry['fees'] as $fee)
                                <div class="mb-2"><div class="font-medium">{{ $fee['name'] }}</div>
                                    @if(filled($fee['description']))<p class="text-xs text-slate-500 whitespace-pre-line">{{ $fee['description'] }}</p>@endif
                                </div>
                            @endforeach
                            @if(filled($entry['description']))<p class="mt-2 text-xs text-slate-600 whitespace-pre-line">{{ $entry['description'] }}</p>@endif
                        </td>
                        <td class="px-4 py-4 text-right whitespace-nowrap {{ $entry['amount_class'] }}">{{ $entry['amount'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-10 text-center text-slate-500">No statement records match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-gray-100">{{ $statement->links() }}</div>
</section>
