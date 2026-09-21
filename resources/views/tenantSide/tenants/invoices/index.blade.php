<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Invoices') }}
        </h2>
    </x-slot>

    <div class="min-h-screen bg-gray-50 py-6 sm:py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Page Header --}}
            <div class="mb-6">
                <a href="{{ route('tenants.dashboard') }}"
                   class="inline-flex items-center text-sm font-semibold text-indigo-600 hover:text-indigo-700 mb-4">

                    <svg class="w-4 h-4 mr-1"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M15 19l-7-7 7-7" />
                    </svg>

                    Back to Dashboard
                </a>

                <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">
                    My Invoices
                </h1>

                <p class="mt-1 text-sm text-gray-500">
                    View your invoices, payment status and receipts.
                </p>
            </div>


            @if($invoices->count() > 0)

                {{-- =====================================================
                    MOBILE VERSION
                ====================================================== --}}
                <div class="space-y-4 lg:hidden">

                    @foreach($invoices as $invoice)

                        @php
                            $status = strtolower((string) $invoice->status);

                            $statusClass = match($status) {
                                'paid' =>
                                    'bg-emerald-50 text-emerald-700 border-emerald-200',

                                'partial' =>
                                    'bg-blue-50 text-blue-700 border-blue-200',

                                'unpaid' =>
                                    'bg-amber-50 text-amber-700 border-amber-200',

                                'overdue' =>
                                    'bg-red-50 text-red-700 border-red-200',

                                'void' =>
                                    'bg-gray-100 text-gray-500 border-gray-200',

                                default =>
                                    'bg-gray-100 text-gray-600 border-gray-200',
                            };

                            $invoiceItems = $invoice->items->map(function ($item) {
                                return [
                                    'description' => $item->description
                                        ?? $item->feeType?->name
                                        ?? 'Item',

                                    'amount' => number_format(
                                        ($item->amount ?? 0) / 100,
                                        2
                                    ),
                                ];
                            });

                            $invoiceVariables = app(\App\Services\InvoiceService::class)
                                ->getInvoiceVariables($invoice);
                        @endphp


                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">

                            {{-- Header --}}
                            <div class="p-5 border-b border-gray-100">

                                <div class="flex items-start justify-between gap-4">

                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                            Invoice
                                        </p>

                                        <h2 class="text-lg font-bold text-indigo-600 mt-1">
                                            {{ $invoice->invoice_no }}
                                        </h2>
                                    </div>

                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border uppercase {{ $statusClass }}">
                                        {{ $invoice->status ?? 'N/A' }}
                                    </span>

                                </div>

                            </div>


                            {{-- Information --}}
                            <div class="p-5 space-y-4">

                                <div class="grid grid-cols-2 gap-4">

                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                            Period
                                        </p>

                                        <p class="text-sm font-semibold text-slate-900 mt-1">
                                            {{ $invoice->period_display ?? $invoice->period ?? '—' }}
                                        </p>
                                    </div>


                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                            Due Date
                                        </p>

                                        <p class="text-sm font-semibold text-slate-900 mt-1">
                                            {{ $invoice->due_date?->format('d/m/Y') ?? '—' }}
                                        </p>
                                    </div>

                                </div>


                                {{-- Amount Summary --}}
                                <div class="bg-gray-50 rounded-xl p-4">

                                    <div class="flex justify-between gap-4">
                                        <span class="text-sm text-gray-500">
                                            Total
                                        </span>

                                        <span class="text-sm font-bold text-slate-900">
                                            RM {{ number_format(($invoice->total_amount ?? 0) / 100, 2) }}
                                        </span>
                                    </div>


                                    <div class="flex justify-between gap-4 mt-2">
                                        <span class="text-sm text-gray-500">
                                            Paid
                                        </span>

                                        <span class="text-sm font-semibold text-emerald-600">
                                            RM {{ number_format(($invoice->amount_paid ?? 0) / 100, 2) }}
                                        </span>
                                    </div>


                                    <div class="flex justify-between gap-4 mt-2 pt-2 border-t border-gray-200">
                                        <span class="text-sm font-semibold text-gray-700">
                                            Balance
                                        </span>

                                        <span class="text-sm font-bold text-red-600">
                                            RM {{ number_format(($invoice->amount_balance ?? 0) / 100, 2) }}
                                        </span>
                                    </div>

                                </div>


                                {{-- Remarks --}}
                                @if(!empty($invoice->remarks))
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                            Remarks
                                        </p>

                                        <p class="text-sm text-slate-700 mt-1">
                                            {{ $invoice->remarks }}
                                        </p>
                                    </div>
                                @endif


                                {{-- Documents --}}
                                <div class="pt-4 border-t border-gray-100">

                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">
                                        Documents
                                    </p>

                                    <div class="flex flex-wrap gap-2">

                                        {{-- Invoice Preview --}}
                                        @if($invoice->documentTemplate)

                                            <div x-data="{
                                                invNo: @js($invoice->invoice_no),
                                                content: @js(
                                                    $invoice->documentTemplate?->html_template
                                                    ?? $invoice->documentTemplate?->html_content
                                                    ?? ''
                                                ),
                                                vars: @js($invoiceVariables),
                                                items: @js($invoiceItems)
                                            }">

                                                <x-buttons.preview-doc
                                                    type="invoice"
                                                    color="indigo"
                                                    titleExpr="'Invoice: ' + invNo"
                                                    contentExpr="content"
                                                    variablesExpr="vars"
                                                    itemsExpr="items"
                                                    buttonTextExpr="invNo" />

                                            </div>

                                        @endif


                                        {{-- Receipt Preview --}}
                                        @foreach($invoice->transactions as $receipt)

                                            @php
                                                $receiptNo = $receipt->receipt_no ?? 'Receipt';

                                                $receiptContent =
                                                    $receipt->documentTemplate?->html_template
                                                    ?? $receipt->documentTemplate?->html_content
                                                    ?? '';

                                                $paymentDate = $receipt->payment_date
                                                    ? \Carbon\Carbon::parse($receipt->payment_date)->format('Y-m-d')
                                                    : ($receipt->created_at?->format('Y-m-d') ?? '—');

                                                $paidAmount = number_format(
                                                    ($receipt->amount_paid ?? 0) / 100,
                                                    2,
                                                    '.',
                                                    ''
                                                );

                                                $invoiceTotal = number_format(
                                                    ($invoice->total_amount ?? 0) / 100,
                                                    2,
                                                    '.',
                                                    ''
                                                );

                                                $receiptVariables = array_merge(
                                                    $invoiceVariables,
                                                    [
                                                        'receipt_no' =>
                                                            $receipt->receipt_no ?? '—',

                                                        'amount_paid' =>
                                                            number_format(
                                                                ($receipt->amount_paid ?? 0) / 100,
                                                                2
                                                            ),

                                                        'payment_date' =>
                                                            $receipt->payment_date
                                                                ? \Carbon\Carbon::parse($receipt->payment_date)->format('d/m/Y')
                                                                : ($receipt->created_at?->format('d/m/Y') ?? '—'),

                                                        'payment_method' =>
                                                            $receipt->payment_method ?? '—',

                                                        'reference_no' =>
                                                            $receipt->transaction_ref ?? '—',

                                                        'issued_by_name' =>
                                                            $receipt->approver?->name ?? 'N/A',

                                                        'issued_by_email' =>
                                                            $receipt->approver?->email ?? 'N/A',
                                                    ]
                                                );
                                            @endphp


                                            <div x-data="{
                                                rNo: @js($receiptNo),
                                                rContent: @js($receiptContent),
                                                invVars: @js($invoiceVariables),
                                                invItems: @js($invoiceItems),

                                                extraData: {
                                                    receiptNo: @js($receiptNo),
                                                    paymentDate: @js($paymentDate),
                                                    receiptVariables: @js($receiptVariables),
                                                    paidAmount: @js($paidAmount),
                                                    invoiceNo: @js($invoice->invoice_no),
                                                    invoiceTotal: @js($invoiceTotal)
                                                }
                                            }">

                                                <x-buttons.preview-doc
                                                    type="receipt"
                                                    color="emerald"
                                                    titleExpr="'Receipt: ' + rNo"
                                                    contentExpr="rContent"
                                                    variablesExpr="invVars"
                                                    itemsExpr="invItems"
                                                    buttonTextExpr="rNo"
                                                    extraExpr="extraData" />

                                            </div>

                                        @endforeach


                                        @if(!$invoice->documentTemplate && $invoice->transactions->isEmpty())

                                            <span class="text-sm text-gray-400">
                                                No documents available.
                                            </span>

                                        @endif

                                    </div>

                                </div>

                            </div>

                        </div>

                    @endforeach

                </div>


                {{-- =====================================================
                    DESKTOP VERSION
                ====================================================== --}}
                <div class="hidden lg:block">

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

                        <div class="overflow-x-auto">

                            <table class="w-full min-w-[1100px] divide-y divide-gray-200 text-left">

                                <thead class="bg-gray-50">

                                    <tr>

                                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            Invoice No
                                        </th>

                                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            Documents
                                        </th>

                                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            Period
                                        </th>

                                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            Due Date
                                        </th>

                                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            Amount Details
                                        </th>

                                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            Remarks
                                        </th>

                                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>

                                    </tr>

                                </thead>


                                <tbody class="divide-y divide-gray-100 bg-white">

                                    @foreach($invoices as $invoice)

                                        @php
                                            $status = strtolower((string) $invoice->status);

                                            $statusClass = match($status) {
                                                'paid' =>
                                                    'bg-emerald-50 text-emerald-700 border-emerald-200',

                                                'partial' =>
                                                    'bg-blue-50 text-blue-700 border-blue-200',

                                                'unpaid' =>
                                                    'bg-amber-50 text-amber-700 border-amber-200',

                                                'overdue' =>
                                                    'bg-red-50 text-red-700 border-red-200',

                                                'void' =>
                                                    'bg-gray-100 text-gray-500 border-gray-200',

                                                default =>
                                                    'bg-gray-100 text-gray-600 border-gray-200',
                                            };

                                            $invoiceItems = $invoice->items->map(function ($item) {
                                                return [
                                                    'description' => $item->description
                                                        ?? $item->feeType?->name
                                                        ?? 'Item',

                                                    'amount' => number_format(
                                                        ($item->amount ?? 0) / 100,
                                                        2
                                                    ),
                                                ];
                                            });

                                            $invoiceVariables = app(\App\Services\InvoiceService::class)
                                                ->getInvoiceVariables($invoice);
                                        @endphp


                                        <tr class="hover:bg-indigo-50/60 transition-colors">

                                            {{-- Invoice No --}}
                                            <td class="px-6 py-5 whitespace-nowrap">

                                                <span class="text-sm font-bold text-indigo-600">
                                                    {{ $invoice->invoice_no }}
                                                </span>

                                            </td>


                                            {{-- Documents --}}
                                            <td class="px-6 py-5">

                                                <div class="flex flex-col items-start gap-2">

                                                    @if($invoice->documentTemplate)

                                                        <div x-data="{
                                                            invNo: @js($invoice->invoice_no),
                                                            content: @js(
                                                                $invoice->documentTemplate?->html_template
                                                                ?? $invoice->documentTemplate?->html_content
                                                                ?? ''
                                                            ),
                                                            vars: @js($invoiceVariables),
                                                            items: @js($invoiceItems)
                                                        }">

                                                            <x-buttons.preview-doc
                                                                type="invoice"
                                                                color="indigo"
                                                                titleExpr="'Invoice: ' + invNo"
                                                                contentExpr="content"
                                                                variablesExpr="vars"
                                                                itemsExpr="items"
                                                                buttonTextExpr="invNo" />

                                                        </div>

                                                    @endif


                                                    @foreach($invoice->transactions as $receipt)

                                                        @php
                                                            $receiptNo = $receipt->receipt_no ?? 'Receipt';

                                                            $receiptContent =
                                                                $receipt->documentTemplate?->html_template
                                                                ?? $receipt->documentTemplate?->html_content
                                                                ?? '';

                                                            $paymentDate = $receipt->payment_date
                                                                ? \Carbon\Carbon::parse($receipt->payment_date)->format('Y-m-d')
                                                                : ($receipt->created_at?->format('Y-m-d') ?? '—');

                                                            $paidAmount = number_format(
                                                                ($receipt->amount_paid ?? 0) / 100,
                                                                2,
                                                                '.',
                                                                ''
                                                            );

                                                            $invoiceTotal = number_format(
                                                                ($invoice->total_amount ?? 0) / 100,
                                                                2,
                                                                '.',
                                                                ''
                                                            );

                                                            $receiptVariables = array_merge(
                                                                $invoiceVariables,
                                                                [
                                                                    'receipt_no' =>
                                                                        $receipt->receipt_no ?? '—',

                                                                    'amount_paid' =>
                                                                        number_format(
                                                                            ($receipt->amount_paid ?? 0) / 100,
                                                                            2
                                                                        ),

                                                                    'payment_date' =>
                                                                        $receipt->payment_date
                                                                            ? \Carbon\Carbon::parse($receipt->payment_date)->format('d/m/Y')
                                                                            : ($receipt->created_at?->format('d/m/Y') ?? '—'),

                                                                    'payment_method' =>
                                                                        $receipt->payment_method ?? '—',

                                                                    'reference_no' =>
                                                                        $receipt->transaction_ref ?? '—',

                                                                    'issued_by_name' =>
                                                                        $receipt->approver?->name ?? 'N/A',

                                                                    'issued_by_email' =>
                                                                        $receipt->approver?->email ?? 'N/A',
                                                                ]
                                                            );
                                                        @endphp


                                                        <div x-data="{
                                                            rNo: @js($receiptNo),
                                                            rContent: @js($receiptContent),
                                                            invVars: @js($invoiceVariables),
                                                            invItems: @js($invoiceItems),

                                                            extraData: {
                                                                receiptNo: @js($receiptNo),
                                                                paymentDate: @js($paymentDate),
                                                                receiptVariables: @js($receiptVariables),
                                                                paidAmount: @js($paidAmount),
                                                                invoiceNo: @js($invoice->invoice_no),
                                                                invoiceTotal: @js($invoiceTotal)
                                                            }
                                                        }">

                                                            <x-buttons.preview-doc
                                                                type="receipt"
                                                                color="emerald"
                                                                titleExpr="'Receipt: ' + rNo"
                                                                contentExpr="rContent"
                                                                variablesExpr="invVars"
                                                                itemsExpr="invItems"
                                                                buttonTextExpr="rNo"
                                                                extraExpr="extraData" />

                                                        </div>

                                                    @endforeach

                                                </div>

                                            </td>


                                            {{-- Period --}}
                                            <td class="px-6 py-5 whitespace-nowrap text-sm text-slate-900">

                                                {{ $invoice->period_display ?? $invoice->period ?? '—' }}

                                            </td>


                                            {{-- Due Date --}}
                                            <td class="px-6 py-5 whitespace-nowrap text-sm text-slate-900">

                                                {{ $invoice->due_date?->format('d/m/Y') ?? '—' }}

                                            </td>


                                            {{-- Amount --}}
                                            <td class="px-6 py-5 whitespace-nowrap">

                                                <div class="text-sm font-semibold text-slate-900">

                                                    Total:
                                                    RM {{ number_format(($invoice->total_amount ?? 0) / 100, 2) }}

                                                </div>

                                                <div class="text-xs font-semibold text-emerald-600 mt-1">

                                                    Paid:
                                                    RM {{ number_format(($invoice->amount_paid ?? 0) / 100, 2) }}

                                                </div>

                                                <div class="text-xs font-semibold text-red-600 mt-1">

                                                    Balance:
                                                    RM {{ number_format(($invoice->amount_balance ?? 0) / 100, 2) }}

                                                </div>

                                            </td>


                                            {{-- Remarks --}}
                                            <td class="px-6 py-5 text-sm text-slate-700">

                                                {{ $invoice->remarks ?? '—' }}

                                            </td>


                                            {{-- Status --}}
                                            <td class="px-6 py-5 whitespace-nowrap">

                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border uppercase {{ $statusClass }}">

                                                    {{ $invoice->status ?? 'N/A' }}

                                                </span>

                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>

                    </div>

                </div>


                {{-- Pagination --}}
                @if($invoices->hasPages())

                    <div class="mt-5">
                        {{ $invoices->links() }}
                    </div>

                @endif


            @else

                {{-- Empty State --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-10 text-center">

                    <div class="w-12 h-12 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">

                        <svg class="w-6 h-6 text-gray-400"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">

                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M9 14l2 2 4-4m5-4V6
                                     a2 2 0 00-2-2H6a2 2 0
                                     00-2 2v12a2 2 0 002 2h12
                                     a2 2 0 002-2V8z" />

                        </svg>

                    </div>

                    <h3 class="text-lg font-bold text-slate-900">
                        No Invoices Found
                    </h3>

                    <p class="text-sm text-gray-500 mt-2">
                        You currently do not have any invoices.
                    </p>

                </div>

            @endif

        </div>
    </div>

</x-app-layout>