<x-app-layout>
    <div x-data="{ 
            loading: false,
            openPayment: false, 
            shakePayment: false,
            openReceipt: false,
            paymentData: { id: '', invoiceNo: '', totalAmount: 0, actionUrl: '' },
            openMakePayment(data) {
                this.paymentData = {
                    id: data.id,
                    invoiceNo: data.invoice_no,
                    totalAmount: data.total_amount,
                    actionUrl: data.action
                };
                this.openPayment = true;
            },

            receiptImage: '',
            receiptUser: '',
            receiptDate: '',
            openReceiptModal(data) {
                this.receiptImage = data.image;
                this.receiptUser = data.user;
                this.receiptDate = data.date;

                this.openReceipt = true;
            },
         }" 
         class="py-12 bg-gray-50 min-h-screen font-sans text-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Invoices</h1>
                    <p class="mt-2 text-sm text-gray-500">View and process tenant invoices and payment records.</p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                
                <div class="p-5 border-b border-gray-100 bg-white">
                    <div class="flex justify-end">
                        <x-form.form method="GET" action="{{ route('admin.invoices.index') }}" class="flex flex-wrap items-center gap-4">
                            <div class="flex items-stretch justify-between">
                                <x-table.search placeholder="Search invoices..." />
                            </div>
                        </x-form.form>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    @if($invoices->count() > 0)
                        <table class="table-auto w-full min-w-[1200px] divide-y divide-gray-200 text-left">
                            <thead class="bg-gray-50">
                                <tr>
                                    <x-table.th name="Invoice" sortField="inv" />
                                    <x-table.th name="Tenant Details" sortField="t" />
                                    <x-table.th name="Amount (RM)" sortField="a" />
                                    <x-table.th name="Status" sortField="s" />
                                    <x-table.th name="Receipt" />
                                    <x-table.th name="Date" sortField="d" />
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($invoices as $invoice)
                                    <tr class="hover:!bg-indigo-50 transition-colors duration-150">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm font-bold text-indigo-600">{{ $invoice->invoice_no }}</span>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-slate-900">{{ $invoice->user->name ?? 'N/A' }}</div>
                                            <div class="text-xs text-gray-500">
                                                @php
                                                    $leasable = $invoice->lease->leasable ?? null;
                                                @endphp

                                                @if($leasable instanceof \App\Models\Room)
                                                    Room: {{ $leasable->room_no }} (Unit: {{ $leasable->unit->unit_no ?? 'N/A' }})
                                                @elseif($leasable instanceof \App\Models\Unit)
                                                    Unit: {{ $leasable->unit_no }}
                                                @elseif($leasable instanceof \App\Models\Property)
                                                    Property: {{ $leasable->name }}
                                                @else
                                                    Subscription: {{ $invoice->context }}
                                                @endif
                                            </div>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-slate-900 font-semibold">{{ number_format($invoice->total_amount / 100, 2)}}</div>
                                            @if($invoice->amount_paid > 0)
                                                <div class="text-[10px] text-green-600">Paid: {{ number_format($invoice->amount_paid / 100, 2) }}</div>
                                            @endif
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border 
                                                {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-700 border-green-200' : 'bg-yellow-100 text-yellow-700 border-yellow-200' }}">
                                                {{ strtoupper($invoice->status) }}
                                            </span>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($invoice->latestPayment?->receipt_path)
                                                <button
                                                    type="button"
                                                    @click="openReceiptModal({
                                                        image: '{{ route('admin.payments.view-receipt', $invoice->latestPayment) }}',
                                                        user: '{{ $invoice->user->name ?? 'User' }}',
                                                        date: '{{ $invoice->latestPayment->created_at->format('M d, Y @ H:i') }}'
                                                    })"
                                                    class="group inline-flex items-center gap-3 px-4 py-2 bg-white border border-slate-200 rounded-xl shadow-sm hover:shadow-md hover:border-indigo-300 hover:bg-indigo-50 transition-all duration-200"
                                                >
                                                    <div class="flex-shrink-0 w-8 h-8 bg-slate-100 group-hover:bg-white rounded-full flex items-center justify-center transition-colors">
                                                        <svg class="w-4 h-4 text-slate-500 group-hover:text-indigo-600"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z"/>
                                                        </svg>
                                                    </div>

                                                    <div class="flex flex-col">
                                                        <span class="text-[12px] font-bold text-slate-700 group-hover:text-indigo-800 uppercase tracking-tight">
                                                            Payment Receipt
                                                        </span>
                                                        <span class="text-[10px] text-slate-400 group-hover:text-indigo-500 font-medium">
                                                            Click to Preview
                                                        </span>
                                                    </div>

                                                    <svg class="w-4 h-4 text-slate-300 group-hover:text-indigo-400 ml-2"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                                    </svg>
                                                </a>
                                            @else
                                                <div class="inline-flex items-center gap-2 px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-gray-400 italic text-sm">
                                                    <svg class="w-4 h-4"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                    </svg>
                                                    No receipt uploaded
                                                </div>
                                            @endif
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $invoice->created_at->format('d M Y') }}
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <div class="flex justify-center items-center gap-2">
                                                @if($invoice->status === 'unpaid')
                                                    <button type="button" 
                                                            {{-- 1. 改为 @click --}}
                                                            {{-- 2. 函数名改为 openMakePayment --}}
                                                            {{-- 3. 直接传数据对象 --}}
                                                            @click="openMakePayment({
                                                                id: '{{ $invoice->id }}',
                                                                invoice_no: '{{ $invoice->invoice_no }}',
                                                                total_amount: '{{ number_format($invoice->total_amount / 100 , 2, '.', '') }}',
                                                                action: '{{ route('admin.invoices.update', $invoice->id) }}'
                                                            })"
                                                            class="inline-flex items-center justify-center p-2 text-emerald-600 hover:text-emerald-900 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors shrink-0 leading-none" 
                                                            title="Pay Now">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                        </svg>
                                                        <span class="ml-1 text-[10px] font-bold uppercase tracking-tighter">Pay</span>
                                                    </button>
                                                @endif

                                                <form action="{{ route('admin.invoices.void', $invoice) }}" 
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
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="text-center py-20 bg-white">
                            <p class="text-gray-500">No payment records found.</p>
                        </div>
                    @endif
                </div>

                @if($invoices->hasPages())
                    {{ $invoices->links() }}
                @endif
            </div>
        </div>
        @include('components.modals.payment-modal')
        @include('components.modals.receipt-preview-modal')
    </div>
</x-app-layout>