@props(['statement', 'filters' => [], 'scopeLabel' => '', 'summary' => null, 'showTenant' => false])

<section id="invoice-statement" class="bg-white overflow-hidden">
    @if($summary)
        @if($context = $summary['context'] ?? null)
            <div class="flex flex-wrap gap-x-6 gap-y-2 mb-4 text-sm text-slate-600">
                @if($context['tenant'])<div><span class="text-slate-500">Tenant:</span> <span class="font-medium text-slate-900">{{ $context['tenant'] }}</span></div>@endif
                @if($context['asset'])<div><span class="text-slate-500">Property / Unit / Room:</span> <span class="font-medium text-slate-900">{{ $context['asset'] }}</span></div>@endif
                @if($context['start_date'])<div><span class="text-slate-500">Lease:</span> {{ $context['start_date'] }} &ndash; {{ $context['end_date'] ?? 'No end date' }}</div>@endif
            </div>
        @endif
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-3">
            <div class="rounded-lg bg-slate-50 border border-gray-100 px-4 py-3">
                <div class="text-xs text-slate-500">Outstanding invoice balance</div>
                <div class="text-xl font-semibold text-slate-900 tabular-nums mt-1">RM {{ number_format($summary['outstanding'] / 100, 2) }}</div>
                <div class="text-xs text-slate-500 mt-1">Current unpaid balance, excluding voided invoices</div>
            </div>
            @if($summary['wallet'] !== null)
                <div class="rounded-lg bg-slate-50 border border-gray-100 px-4 py-3">
                    <div class="text-xs text-slate-500">Tenant Wallet balance</div>
                    <div class="text-xl font-semibold text-slate-900 tabular-nums mt-1">RM {{ number_format($summary['wallet'] / 100, 2) }}</div>
                    <div class="text-xs text-slate-500 mt-1">Available across leases; refund reservations may apply</div>
                </div>
            @endif
        </div>
        @if($summary['wallet'] !== null)
            <p class="text-xs text-slate-500 mb-4">The remaining wallet balance is separate and has not been deducted from the outstanding invoice balance shown above.</p>
        @endif
    @endif
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-left divide-y divide-gray-200">
            <caption class="text-left text-xs text-slate-500 pb-3">Latest recorded activity first. Payment rows show the payment date. Expand a row for details.</caption>
            <thead class="bg-gray-50 text-xs uppercase text-slate-600">
                <tr>
                    <th scope="col" class="px-4 py-3">Date</th>
                    @if($showTenant)<th scope="col" class="px-4 py-3">Tenant / Lease</th>@endif
                    <th scope="col" class="px-4 py-3">Document / Description</th>
                    <th scope="col" class="px-4 py-3 text-right">Charges</th>
                    <th scope="col" class="px-4 py-3 text-right">Payment applied</th>
                    <th scope="col" class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($statement as $entry)
                    @php
                        $detailId = 'statement-detail-'.$loop->index;
                        $isInvoice = $entry['kind'] === 'invoice';
                        $isPayment = $entry['kind'] === 'payment';
                        $isVoid = $isInvoice && strtolower($entry['status']) === 'void';
                        $amount = isset($entry['amount_cents']) ? 'RM '.number_format($entry['amount_cents'] / 100, 2) : 'Not recorded';
                        $displayDate = $isPayment ? ($entry['payment_date'] ?? $entry['date']) : $entry['date'];
                    @endphp
                    <tr class="align-top cursor-pointer hover:!bg-indigo-50 transition-colors duration-150" data-statement-event="{{ $entry['id'] }}">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="flex items-start gap-2">
                                <button type="button" data-statement-toggle aria-expanded="false" aria-controls="{{ $detailId }}"
                                    aria-label="Toggle details for {{ $entry['receipt_no'] ?? $entry['invoice_no'] ?? $entry['id'] }}"
                                    class="group inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                    <svg class="fill-current h-4 w-4 transition-transform duration-300 ease-in-out motion-reduce:transition-none group-aria-expanded:rotate-90" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M6 4L14 10L6 16Z" /></svg>
                                </button>
                                <div class="pt-1"><div>{{ $displayDate }}</div><div class="text-xs text-slate-500">{{ $isPayment && ($entry['payment_date'] ?? null) ? 'Payment date' : 'Recorded date' }}</div></div>
                            </div>
                        </td>
                        @if($showTenant)<td class="px-4 py-3"><div class="font-medium">{{ $entry['party'] }}</div><div class="text-xs text-slate-500">{{ $entry['lease_label'] ?? ($entry['lease_id'] ? 'Lease record' : 'Tenant-level record') }}</div></td>@endif
                        <td class="px-4 py-3 min-w-[220px]">
                            <div class="font-medium text-slate-900">
                                @if($isPayment)
                                    {{ $entry['receipt_no'] ?? 'Payment' }} <span class="text-slate-400">&rarr;</span> {{ $entry['invoice_no'] ?? 'Invoice not recorded' }}
                                @else
                                    {{ $entry['invoice_no'] ?? 'Tenant Wallet' }}
                                @endif
                            </div>
                            <div class="text-xs text-slate-600 mt-1">{{ $entry['label'] }}@if($isPayment && $entry['payment_method']) &middot; {{ ucfirst(str_replace('_', ' ', $entry['payment_method'])) }}@endif</div>
                            @if($isInvoice || $isPayment)
                                <div class="text-xs text-slate-500 mt-1 break-words">{{ collect($entry['fees'])->pluck('name')->join(', ') }}@if($entry['billing_period'] ?? null) &middot; {{ $entry['billing_period'] }}@endif</div>
                            @else
                                <div class="text-sm font-medium text-indigo-700 mt-1">{{ $entry['amount'] }}</div>
                                <div class="text-xs text-slate-500 mt-1">Wallet movement only</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                            @if($isInvoice)
                                <span @class(['font-medium', 'text-slate-400 line-through' => $isVoid, 'text-slate-900' => !$isVoid])>{{ $amount }}</span>
                                @if($isVoid)<div class="text-xs text-slate-500 mt-1">Excluded</div>@endif
                            @else
                                <span class="text-slate-400">&mdash;</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                            @if($isPayment)<span class="font-medium text-green-700">{{ $amount }}</span>@else<span class="text-slate-400">&mdash;</span>@endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap"><x-invoice-status-badge :status="$entry['status']" /></td>
                    </tr>
                    <tr id="{{ $detailId }}" hidden>
                        <td colspan="{{ $showTenant ? 6 : 5 }}" class="px-4 py-4 bg-slate-50">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                                <div class="space-y-3 min-w-0">
                                    <h3 class="font-semibold text-slate-900">{{ $isInvoice ? 'Invoice details' : ($isPayment ? 'Payment details' : 'Wallet entry') }}</h3>
                                    <dl class="space-y-2">
                                        @if($entry['invoice_no'])<div class="flex flex-wrap justify-between gap-x-4"><dt class="text-slate-500">{{ $isPayment ? 'Applied to' : 'Invoice' }}</dt><dd class="font-medium">{{ $entry['invoice_no'] }}</dd></div>@endif
                                        @if($entry['receipt_no'])<div class="flex flex-wrap justify-between gap-x-4"><dt class="text-slate-500">Receipt</dt><dd>{{ $entry['receipt_no'] }}</dd></div>@endif
                                        @if($isPayment)<div class="flex flex-wrap justify-between gap-x-4"><dt class="text-slate-500">Payment date</dt><dd>{{ $entry['payment_date'] ?? 'Not recorded' }}</dd></div><div class="flex flex-wrap justify-between gap-x-4"><dt class="text-slate-500">Amount applied</dt><dd class="font-semibold">{{ $amount }}</dd></div>@endif
                                        @if($entry['payment_method'])<div class="flex flex-wrap justify-between gap-x-4"><dt class="text-slate-500">Payment method</dt><dd>{{ ucfirst(str_replace('_', ' ', $entry['payment_method'])) }}</dd></div>@endif
                                        @if($entry['reference'])<div><dt class="text-slate-500">Reference</dt><dd class="break-all">{{ $entry['reference'] }}</dd></div>@endif
                                        @if($isInvoice)
                                            @if($entry['billing_period'] ?? null)<div class="flex flex-wrap justify-between gap-x-4"><dt class="text-slate-500">Billing period</dt><dd>{{ $entry['billing_period'] }}</dd></div>@endif
                                            @if($entry['due_date'] ?? null)<div class="flex flex-wrap justify-between gap-x-4"><dt class="text-slate-500">Due date</dt><dd>{{ $entry['due_date'] }}</dd></div>@endif
                                            <div class="flex flex-wrap justify-between gap-x-4 border-t border-gray-200 pt-2"><dt class="text-slate-500">Invoice amount</dt><dd class="font-semibold">{{ $amount }}</dd></div>
                                            @if(isset($entry['invoice_paid']))<div class="flex flex-wrap justify-between gap-x-4"><dt class="text-slate-500">Payment applied to date</dt><dd>RM {{ number_format($entry['invoice_paid'] / 100, 2) }}</dd></div>@endif
                                            @if($isVoid)
                                                <div class="text-slate-500">Voided invoice. Excluded from the outstanding balance.</div>
                                            @elseif(isset($entry['invoice_balance']))
                                                <div class="flex flex-wrap justify-between gap-x-4"><dt class="font-medium">Current remaining balance</dt><dd class="font-semibold">RM {{ number_format($entry['invoice_balance'] / 100, 2) }}</dd></div>
                                            @endif
                                        @endif
                                        @if(!$isInvoice && !$isPayment)<div class="font-medium text-indigo-700">{{ $entry['amount'] }}</div>@endif
                                    </dl>
                                    @if($isPayment && count($entry['linked_wallet']))
                                        <div class="border-t border-gray-200 pt-3 space-y-2">
                                            <h4 class="font-medium">Wallet movement</h4>
                                            @foreach($entry['linked_wallet'] as $movement)
                                                <div class="border-l-2 border-indigo-200 pl-3" data-linked-wallet="{{ $movement->event_id }}">
                                                    <div>{{ $movement->amount < 0 ? 'Wallet used' : 'Excess credited to wallet' }}: <span class="font-medium">RM {{ number_format(abs($movement->amount) / 100, 2) }}</span></div>
                                                    <div class="text-xs text-slate-500">Recorded: {{ $movement->recorded_at ?? 'Not recorded' }}</div>
                                                    @if(filled($movement->description))<p class="text-xs text-slate-500 whitespace-pre-line">{{ $movement->description }}</p>@endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if($isPayment && $entry['stored_paid'] !== null)
                                        <details class="text-xs text-slate-500 border-t border-gray-200 pt-3">
                                            <summary class="cursor-pointer font-medium">Receipt totals</summary>
                                            <div class="mt-2 space-y-1"><div>Receipt recorded amount: RM {{ number_format($entry['stored_paid'] / 100, 2) }}</div>@if($entry['stored_excess'])<div>Receipt recorded excess: RM {{ number_format($entry['stored_excess'] / 100, 2) }}</div>@endif<p>Receipt totals may include wallet funds or overpayments; they are not a separate payment applied to this invoice.</p></div>
                                        </details>
                                    @endif
                                    @if(!$isInvoice && !$isPayment)<p class="text-xs text-slate-500">Separate wallet record. No verified receipt link is available. This movement is not included in the Payment applied column.</p>@endif
                                </div>
                                <div class="space-y-4 min-w-0">
                                    @if(count($entry['fees']))
                                        <div>
                                            <h3 class="font-semibold text-slate-900 mb-2">{{ $isPayment ? 'Invoice items (reference)' : 'Invoice items' }}</h3>
                                            <div class="space-y-2">
                                                @foreach($entry['fees'] as $fee)
                                                    <div><div class="flex justify-between gap-4"><span class="font-medium">{{ $fee['name'] }}</span>@if(isset($fee['amount']))<span class="whitespace-nowrap tabular-nums">RM {{ number_format($fee['amount'] / 100, 2) }}</span>@endif</div>@if(filled($fee['description']))<p class="text-xs text-slate-500 whitespace-pre-line mt-1">{{ $fee['description'] }}</p>@endif</div>
                                                @endforeach
                                            </div>
                                            @if($isPayment)<p class="text-xs text-slate-500 mt-2">Items describe the invoice; the payment amount is applied to the invoice as a whole.</p>@endif
                                        </div>
                                    @endif
                                    @if(filled($entry['invoice_remarks'] ?? null))<div><h4 class="font-medium mb-1">Invoice remarks</h4><p class="text-slate-600 whitespace-pre-line">{{ $entry['invoice_remarks'] }}</p></div>@endif
                                    @if(filled($entry['description']))<div><h4 class="font-medium mb-1">Remarks</h4><p class="text-slate-600 whitespace-pre-line">{{ $entry['description'] }}</p></div>@endif
                                    <div class="space-y-1 text-xs text-slate-500 border-t border-gray-200 pt-3 break-words">
                                        <div>{{ $entry['party_label'] }}: {{ $entry['party'] }}</div>
                                        @if($entry['payee'])<div>Lease owner: {{ $entry['payee'] }}</div>@endif
                                        @if($entry['issuer'])<div>Invoice issuer: {{ $entry['issuer'] }}</div>@endif
                                        <div>Recorded: {{ $entry['recorded_at'] ?? 'Not recorded' }}</div>
                                        <div>{{ $entry['source_type'] }}</div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $showTenant ? 6 : 5 }}" class="px-6 py-10 text-center text-slate-500">No statement records match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div data-statement-pagination class="p-4 border-t border-gray-100">{{ $statement->links() }}</div>
</section>
