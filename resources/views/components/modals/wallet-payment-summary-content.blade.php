            <div class="p-6 space-y-5 max-h-[65vh] overflow-y-auto">
                <p class="text-sm text-gray-600">
                    @if(($summary['trigger'] ?? 'overpayment') === 'invoice_created')
                        Wallet payment results for newly generated invoice(s) {{ $summary['source_invoice_no'] }} are shown below.
                    @else
                        Wallet payment results after the overpayment on {{ $summary['source_invoice_no'] }} are shown below.
                    @endif
                </p>
                <div class="rounded-lg bg-indigo-50 p-4 flex justify-between gap-4 text-indigo-900">
                    <span class="font-semibold">Wallet balance used</span>
                    <strong>RM{{ number_format($summary['wallet_used_cents'] / 100, 2) }}</strong>
                </div>
                @if(($summary['wallet_reserved_cents'] ?? 0) > 0)
                    <div class="text-sm text-gray-600 space-y-2">
                        <div class="flex justify-between gap-4"><span>Reserved for approved checkout refund</span><strong>RM{{ number_format($summary['wallet_reserved_cents'] / 100, 2) }}</strong></div>
                        <div class="flex justify-between gap-4"><span>Available before automatic payment</span><strong>RM{{ number_format($summary['wallet_available_cents'] / 100, 2) }}</strong></div>
                    </div>
                @endif
                @if($summary['skipped'] ?? [])
                    <h4 class="font-semibold text-gray-900">Skipped Invoices</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-gray-600"><tr>
                                <th class="p-3">Invoice / Fee Type</th><th class="p-3">Reason</th>
                                <th class="p-3 text-right">Unpaid Balance</th><th class="p-3">Next Invoice Paid</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($summary['skipped'] as $skipped)
                                    <tr>
                                        <td class="p-3"><span class="font-semibold">{{ $skipped['invoice_no'] }}</span><div class="text-gray-600">{{ $skipped['items'] }}</div></td>
                                        <td class="p-3 text-amber-800">{{ $skipped['refund_locked'] ? 'Locked to approved checkout refund' : $skipped['reason'] }}</td>
                                        <td class="p-3 text-right whitespace-nowrap">RM{{ number_format($skipped['remaining_cents'] / 100, 2) }}</td>
                                        <td class="p-3">{{ $skipped['next_paid_invoice_no'] ?? 'No later invoice was paid' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if($summary['payments'])
                    <h4 class="font-semibold text-gray-900">Paid Invoices</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-gray-600"><tr>
                                <th class="p-3">Invoice</th><th class="p-3">Fee Type</th><th class="p-3 text-right">Invoice Amount</th>
                                <th class="p-3 text-right">Paid from Wallet</th><th class="p-3 text-right">Unpaid Balance</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($summary['payments'] as $payment)
                                    <tr><td class="p-3 font-semibold">{{ $payment['invoice_no'] }}</td><td class="p-3">{{ $payment['items'] }}</td>
                                        <td class="p-3 text-right whitespace-nowrap">RM{{ number_format($payment['invoice_amount_cents'] / 100, 2) }}</td>
                                        <td class="p-3 text-right whitespace-nowrap font-semibold">RM{{ number_format($payment['paid_cents'] / 100, 2) }}</td>
                                        <td class="p-3 text-right whitespace-nowrap">RM{{ number_format($payment['remaining_cents'] / 100, 2) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @foreach($summary['notes'] as $note)
                    <p class="text-sm text-amber-800">{{ $note }}</p>
                @endforeach
                <div class="flex justify-between border-t pt-4 font-semibold">
                    <span>Wallet remaining</span><span>RM{{ number_format($summary['wallet_remaining_cents'] / 100, 2) }}</span>
                </div>
                @if($summary['payments'])
                    <p class="text-xs text-gray-500">These payments are already recorded. Closing this summary does not reverse them.</p>
                @else
                    <p class="text-xs text-gray-500">No automatic wallet payment was recorded. The balance remains in the wallet.</p>
                @endif
            </div>
            <div class="px-6 py-4 bg-gray-50 border-t flex justify-end">
                <x-form.primary-button type="button" @click="$dispatch('close-wallet-payment-summary')">OK</x-form.primary-button>
            </div>
