@props(['statement', 'filters' => [], 'scopeLabel' => '', 'summary' => null, 'showTenant' => false])

<section id="invoice-statement" class="bg-white overflow-hidden">
    @if($summary)
        <div class="flex flex-wrap gap-4 mb-4">
            <div class="rounded-lg bg-slate-50 px-4 py-3"><div class="text-xs text-slate-500">Outstanding invoices</div><div class="text-lg font-semibold">RM{{ number_format($summary['outstanding'] / 100, 2) }}</div></div>
            @if($summary['wallet'] !== null)
                <div class="rounded-lg bg-slate-50 px-4 py-3"><div class="text-xs text-slate-500">Tenant Wallet · Available across leases</div><div class="text-lg font-semibold">RM{{ number_format($summary['wallet'] / 100, 2) }}</div></div>
            @endif
        </div>
    @endif
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-left divide-y divide-gray-200">
            <thead class="bg-gray-50 text-xs uppercase text-slate-600">
                <tr>
                    <th class="px-4 py-3">Date</th><th class="px-4 py-3">Type</th>
                    @if($showTenant)<th class="px-4 py-3">Tenant / Lease</th>@endif
                    <th class="px-4 py-3">Item / Document</th><th class="px-4 py-3">Method</th>
                    <th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($statement as $entry)
                    @php
                        $detailId = 'statement-detail-'.$loop->index;
                        $amountColor = match ($entry['kind']) {
                            'invoice' => 'text-orange-700',
                            'payment' => 'text-green-700',
                            'wallet_credit' => 'text-blue-700',
                            'wallet_payment' => 'text-purple-700',
                            default => 'text-slate-800',
                        };
                    @endphp
                    <tr class="align-top cursor-pointer hover:!bg-indigo-50 transition-colors duration-150" data-statement-event="{{ $entry['id'] }}">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <button type="button" data-statement-toggle aria-expanded="false" aria-controls="{{ $detailId }}"
                                    aria-label="Toggle details for {{ $entry['receipt_no'] ?? $entry['invoice_no'] ?? $entry['id'] }}"
                                    class="group inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                    <svg class="fill-current h-4 w-4 transition-transform duration-300 ease-in-out group-aria-expanded:rotate-90" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M6 4L14 10L6 16Z" /></svg>
                                </button>
                                <span>{{ $entry['date'] }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-medium">{{ $entry['label'] }}</td>
                        @if($showTenant)<td class="px-4 py-3"><div>{{ $entry['party'] }}</div><div class="text-xs text-slate-500 break-all">{{ $entry['lease_id'] ?? 'Tenant-level record' }}</div></td>@endif
                        <td class="px-4 py-3"><div>{{ $entry['invoice_no'] ?? 'Tenant Wallet' }}</div>@if($entry['receipt_no'])<div class="text-xs text-slate-500">{{ $entry['receipt_no'] }}</div>@endif</td>
                        <td class="px-4 py-3">{{ $entry['payment_method'] ? ucfirst(str_replace('_', ' ', $entry['payment_method'])) : '—' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap font-medium {{ $amountColor }}">{{ $entry['amount'] }}</td>
                        <td class="px-4 py-3 whitespace-nowrap"><x-invoice-status-badge :status="$entry['status']" /></td>
                    </tr>
                    <tr id="{{ $detailId }}" hidden>
                        <td colspan="{{ $showTenant ? 7 : 6 }}" class="px-4 py-4 bg-slate-50">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                <div class="space-y-1 break-words">
                                    @if($entry['invoice_no'])<div><span class="text-slate-500">Invoice:</span> {{ $entry['invoice_no'] }}</div>@endif
                                    @if($entry['receipt_no'])<div><span class="text-slate-500">Receipt:</span> {{ $entry['receipt_no'] }}</div>@endif
                                    <div><span class="text-slate-500">{{ $entry['party_label'] }}:</span> {{ $entry['party'] }}</div>
                                    @if($entry['payee'])<div><span class="text-slate-500">Lease owner:</span> {{ $entry['payee'] }}</div>@endif
                                    @if($entry['issuer'])<div><span class="text-slate-500">Invoice issuer:</span> {{ $entry['issuer'] }}</div>@endif
                                    @if($entry['payment_date'] ?? null)<div>Payment date: {{ $entry['payment_date'] }}</div>@endif
                                    <div>Recorded: {{ $entry['recorded_at'] ?? 'Not recorded (listed after dated records)' }}</div>
                                    @if($entry['payment_method'])<div>Method: {{ $entry['payment_method'] }}</div>@endif
                                    @if($entry['reference'])<div class="break-all">Reference: {{ $entry['reference'] }}</div>@endif
                                    <div class="text-xs text-slate-500">{{ $entry['source_type'] }}</div>
                                </div>
                                <div class="space-y-2">
                                    @foreach($entry['fees'] as $fee)
                                        <div><div class="font-medium">{{ $fee['name'] }}</div>@if(filled($fee['description']))<p class="text-slate-600 whitespace-pre-line">{{ $fee['description'] }}</p>@endif</div>
                                    @endforeach
                                    @if(filled($entry['invoice_remarks'] ?? null))<div><span class="text-slate-500">Invoice remarks:</span><p class="whitespace-pre-line">{{ $entry['invoice_remarks'] }}</p></div>@endif
                                    @if(filled($entry['description']))<div><span class="text-slate-500">Remarks:</span><p class="whitespace-pre-line">{{ $entry['description'] }}</p></div>@endif
                                </div>
                                @if($entry['kind'] === 'payment')
                                    <div class="space-y-2 sm:col-span-2">
                                        <div class="font-medium">Invoice allocation: {{ $entry['invoice_no'] }} · {{ $entry['amount'] }}</div>
                                        @if($entry['stored_paid'] !== null)<div>Receipt recorded amount: RM{{ number_format($entry['stored_paid'] / 100, 2) }}</div>@endif
                                        @if($entry['stored_excess'])<div>Receipt recorded excess: RM{{ number_format($entry['stored_excess'] / 100, 2) }}</div>@endif
                                        <p class="text-xs text-slate-500">The recorded receipt amount may include Wallet funds or excess. External cash received cannot be verified from this receipt alone.</p>
                                        @foreach($entry['linked_wallet'] as $movement)
                                            <div class="border-l-2 border-slate-300 pl-3" data-linked-wallet="{{ $movement->event_id }}">
                                                <div>{{ $movement->amount < 0 ? 'Wallet debit' : 'Excess credited to Wallet' }}: RM{{ number_format(abs($movement->amount) / 100, 2) }}</div>
                                                <div class="text-xs text-slate-500">Recorded: {{ $movement->recorded_at ?? 'Not recorded' }} · {{ $movement->event_id }}</div>
                                                @if(filled($movement->description))<p class="whitespace-pre-line">{{ $movement->description }}</p>@endif
                                            </div>
                                        @endforeach
                                    </div>
                                @elseif(str_starts_with($entry['kind'], 'wallet_'))
                                    <p class="text-xs text-slate-500 sm:col-span-2">Separate Wallet record. No reliable link to a specific receipt is available.</p>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $showTenant ? 7 : 6 }}" class="px-6 py-10 text-center text-slate-500">No statement records match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div data-statement-pagination class="p-4 border-t border-gray-100">{{ $statement->links() }}</div>
</section>
