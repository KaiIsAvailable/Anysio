@forelse($payments as $payment)
    <tr class="hover:bg-gray-50 transition-colors">
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="text-sm font-bold text-indigo-600">{{ $payment->invoice_no }}</div>
            <div class="text-xs text-gray-400">{{ $payment->created_at->format('d M Y') }}</div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
            <span class="capitalize font-medium">{{ str_replace('_', ' ', $payment->payment_type) }}</span>
            @if($payment->period)
                <div class="text-xs text-gray-500">For {{ \Carbon\Carbon::parse($payment->period)->format('M Y') }}</div>
            @endif
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="text-sm text-gray-900">Paid: <b>RM {{ number_format($payment->amount_paid / 100, 2) }}</b></div>
            @if ($payment->amount_paid != null)
                <div class="text-xs text-gray-500">Overpaid: <b>RM {{ number_format($payment->amount_over_paid / 100, 2) }}</b></div>
                <div class="text-xs text-gray-500">Underpaid: <b>RM {{ number_format($payment->amount_under_paid / 100, 2) }}</b></div>
            @endif
            @if($payment->amount_due > $payment->amount_paid)
                <div class="text-xs text-red-500 italic">Due: RM {{ number_format($payment->amount_due / 100, 2) }}</div>
            @endif
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm">
            @php
                $statusClasses = match($payment->status) {
                    'paid'     => 'bg-green-100 text-green-700 border-green-200',
                    'approved' => 'bg-blue-100 text-blue-700 border-blue-200',
                    'unpaid'   => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                    'rejected' => 'bg-red-100 text-red-700 border-red-200',
                    'void'     => 'bg-gray-100 text-gray-500 border-gray-200 line-through',
                    default    => 'bg-gray-100 text-gray-600',
                };
            @endphp
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $statusClasses }}">
                {{ strtoupper($payment->status) }}
            </span>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
            {{ $payment->payment_method ?? '—' }}
            @if($payment->transaction_ref)
                <div class="text-[10px] text-gray-400">Ref: {{ $payment->transaction_ref }}</div>
            @endif
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
            <div class="flex justify-center items-center gap-2">
                @if($payment->status === 'unpaid')
                    <button type="button" onclick="openPaymentModal(this)"
                            data-url="{{ route('admin.payments.update', $payment->id) }}"
                            data-invoice="{{ $payment->invoice_no }}"
                            data-due="{{ $payment->amount_due / 100 }}"
                            class="inline-flex items-center justify-center p-2 text-emerald-600 hover:text-emerald-900 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors shrink-0 leading-none" 
                            title="Pay Now">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <span class="ml-1 text-[10px] font-bold uppercase tracking-tighter">Pay</span>
                    </button>
                @endif

                <form action="{{ route('admin.payments.voidPayment', $payment->id) }}" 
                    method="POST" 
                    onsubmit="return confirm('Void this invoice?');" 
                    class="inline-flex items-center m-0 p-0"> @csrf
                    @method('DELETE')
                    <button type="submit" 
                            class="inline-flex items-center justify-center p-2 text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 rounded-lg transition-colors shrink-0" 
                            title="Void Invoice">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </form>
            </div>
        </td>
@empty
    <tr>
        <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 italic">{{ $emptyMessage ?? 'No records found.' }}</td>
    </tr>
@endforelse