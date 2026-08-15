<x-app-layout>
    <div x-data="{ 
            loading: false,
            openPayment: false, 
            shakePayment: false,
            openReceipt: false,
            paymentData: { id: '', invoice_no: '', amount_due: 0, actionUrl: '' },

            receiptImage: '',
            receiptUser: '',
            receiptDate: '',
            openReceiptModal(data) {
                this.receiptImage = data.image;
                this.receiptUser = data.user;
                this.receiptDate = data.date;

                this.openReceipt = true;
            }
         }"
         @open-payment.window="paymentData = $event.detail; openPayment = true;"
         class="py-12 bg-gray-50 min-h-screen font-sans text-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Invoices</h1>
                    <p class="mt-2 text-sm text-gray-500">View and process tenant invoices and payment records.</p>
                </div>
            </div>

            <!-- Main Content Card -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                
                <!-- Search Filter Bar -->
                <div class="p-5 border-b border-gray-100 bg-white">
                    <div class="flex justify-end">
                        <x-form.form method="GET" action="{{ route('admin.invoices.index') }}" class="flex flex-wrap items-center gap-4">
                            <div class="flex items-stretch justify-between">
                                <x-table.search placeholder="Search invoices..." />
                            </div>
                        </x-form.form>
                    </div>
                </div>

                <!-- Table Container -->
                <div class="overflow-x-auto">
                    @if($invoices->count() > 0)
                        <table class="table-auto w-full min-w-[1200px] divide-y divide-gray-200 text-left">
                            <thead class="bg-gray-50">
                                <tr>
                                    <x-table.th name="Invoice & Template" sortField="inv" />
                                    <x-table.th name="Tenant Details" sortField="t" />
                                    <x-table.th name="Amount (RM)" sortField="a" />
                                    <x-table.th name="Status" sortField="s" />
                                    <x-table.th name="Date" sortField="d" />
                                    <x-table.th name="Proof of Payment" />
                                    <x-table.th name="Actions" />
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($invoices as $invoice)
                                    <tr class="hover:!bg-indigo-50 transition-colors duration-150">
                                        <!-- Invoice Number & Template Preview Button -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex flex-col gap-1.5">
                                                <span class="text-sm font-bold text-indigo-600">{{ $invoice->invoice_no }}</span>
                                                
                                                @php
                                                    $template = data_get($invoice, 'documentTemplate');
                                                    $templateTitle = is_object($template) ? ($template->title ?? null) : (is_array($template) ? ($template['title'] ?? null) : null);
                                                @endphp

                                                @if(data_get($invoice, 'document_template_id') && $templateTitle)
                                                    <button type="button" 
                                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-2 py-1 rounded-md border border-indigo-200 transition-all w-fit"
                                                        @click="
                                                            let content = @js(optional($invoice->documentTemplate)->html_content ?? '');
                                                            if (!content) return;

                                                            let dynamicRows = '';
                                                            let items = @js($invoice->invoice_items ?? []);
                                                            if (items.length > 0) {
                                                                items.forEach(item => {
                                                                    dynamicRows += `
                                                                        <tr style='border-bottom: 1px solid #e2e8f0;'>
                                                                            <td style='padding: 12px 15px; color: #0f172a;'>${item.description || 'Item'}</td>
                                                                            <td style='padding: 12px 15px; text-align: center; color: #475569;'>1</td>
                                                                            <td style='padding: 12px 15px; text-align: right; color: #475569;'>RM ${item.amount || '0.00'}</td>
                                                                            <td style='padding: 12px 15px; text-align: right; color: #0f172a; font-weight: 500;'>RM ${item.amount || '0.00'}</td>
                                                                        </tr>
                                                                    `;
                                                                });
                                                            } else {
                                                                dynamicRows = `<tr><td colspan='4' style='padding: 12px 15px; text-align: center; color: #94a3b8; font-style: italic;'>No items billed.</td></tr>`;
                                                            }

                                                            let tempDiv = document.createElement('div');
                                                            tempDiv.innerHTML = content;
                                                            let tbody = tempDiv.querySelector('#dynamic-invoice-tbody');
                                                            if (tbody) {
                                                                tbody.innerHTML = dynamicRows;
                                                            }
                                                            content = tempDiv.innerHTML;

                                                            const replacements = {
                                                                '@{{ invoice_number }}': @js($invoice->invoice_no),
                                                                '@{{ tenant_name }}': @js($invoice->user->name ?? 'N/A'),
                                                                '@{{ property_address }}': @js($invoice->lease?->leasable?->address ?? 'N/A'),
                                                                '@{{ owner_name }}': @js($invoice->lease?->leasable?->owner_name ?? 'N/A'),
                                                                '@{{ issue_date }}': @js($invoice->created_at->format('M d, Y')),
                                                                '@{{ due_date }}': @js($invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') : '—'),
                                                                '@{{ total_amount }}': @js(number_format($invoice->total_amount / 100, 2)),
                                                                '@{{ rent_price }}': @js(number_format($invoice->total_amount / 100, 2))
                                                            };

                                                            Object.keys(replacements).forEach(key => {
                                                                let val = replacements[key] || 'N/A';
                                                                content = content.split(key).join(`<span class='text-inherit font-semibold text-indigo-600'>${val}</span>`);
                                                            });

                                                            $dispatch('open-agreement-preview', {
                                                                title: 'Invoice: ' + @js($invoice->invoice_no), 
                                                                content: content 
                                                            });
                                                            document.body.style.overflow = 'hidden';
                                                        ">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                        <span>{{ optional($invoice->documentTemplate)->title }}</span>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>

                                        <!-- Tenant & Leasable Details -->
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

                                        <!-- Amount -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-slate-900 font-semibold">RM {{ number_format($invoice->total_amount / 100, 2) }}</div>
                                            @if($invoice->amount_paid > 0)
                                                <div class="text-[10px] text-green-600">Paid: RM {{ number_format($invoice->amount_paid / 100, 2) }}</div>
                                            @endif
                                        </td>

                                        <!-- Status Badge -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase
                                                @if($invoice->status === 'paid') bg-green-100 text-green-700 border-green-200
                                                @elseif($invoice->status === 'unpaid') bg-yellow-100 text-yellow-700 border-yellow-200
                                                @elseif($invoice->status === 'rejected') bg-red-100 text-red-700 border-red-200
                                                @elseif($invoice->status === 'void') bg-gray-100 text-gray-500 border-gray-200 line-through
                                                @endif">
                                                {{ $invoice->status }}
                                            </span>
                                        </td>

                                        <!-- Date -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $invoice->created_at->format('d M Y') }}
                                        </td>

                                        <!-- Proof of Payment -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($invoice->receipt_path && $invoice->latestPayment)
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
                                                        <svg class="w-4 h-4 text-slate-500 group-hover:text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z"/>
                                                        </svg>
                                                    </div>
                                                    <div class="flex flex-col text-left">
                                                        <span class="text-[12px] font-bold text-slate-700 group-hover:text-indigo-800 uppercase tracking-tight">Payment Receipt</span>
                                                        <span class="text-[10px] text-slate-400 group-hover:text-indigo-500 font-medium">Click to Preview</span>
                                                    </div>
                                                    <svg class="w-4 h-4 text-slate-300 group-hover:text-indigo-400 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                                    </svg>
                                                </button>
                                            @else
                                                <div class="inline-flex items-center gap-2 px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-gray-400 italic text-sm">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                    </svg>
                                                    No proof uploaded
                                                </div>
                                            @endif
                                        </td>

                                        <!-- Actions -->
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <div class="flex justify-center items-center gap-2">
                                                @if($invoice->status === 'unpaid')
                                                    @php
                                                        $paymentPayload = json_encode([
                                                            'id' => $invoice->id,
                                                            'invoiceNo' => $invoice->invoice_no,
                                                            'totalAmount' => number_format($invoice->amount_balance / 100, 2),
                                                            'invoiceItems' => $invoice->invoice_items,
                                                            'actionUrl' => route('admin.invoices.payment', $invoice->id)
                                                        ]);
                                                    @endphp

                                                    <button type="button"
                                                        @click="$dispatch('open-payment', {{ $paymentPayload }})"
                                                        class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-600 transition hover:bg-emerald-100"
                                                        title="Record Payment">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                                        </svg>
                                                        <span class="text-[10px] font-bold uppercase tracking-tighter">Record Payment</span>
                                                    </button>

                                                    <form method="POST" action="{{ route('admin.invoices.reject-payment', $invoice->id) }}" onsubmit="return confirm('Reject this payment?')">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-600 transition hover:bg-rose-100">
                                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                            <span class="text-[10px] font-bold uppercase tracking-tighter">Reject</span>
                                                        </button>
                                                    </form>
                                                @endif

                                                @if(!in_array($invoice->status, ['paid', 'void']))
                                                    <form method="POST" action="{{ route('admin.invoices.void', $invoice->id) }}" onsubmit="return confirm('Void this invoice?')">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600 transition hover:bg-slate-200">
                                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <circle cx="12" cy="12" r="9"/>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.5 5.5l13 13"/>
                                                            </svg>
                                                            <span class="text-[10px] font-bold uppercase tracking-tighter">Void</span>
                                                        </button>
                                                    </form>
                                                @endif
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

                <!-- Pagination -->
                @if($invoices->hasPages())
                    <div class="p-4 border-t border-gray-100">
                        {{ $invoices->links() }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Modals -->
        <x-modals.payment-modal />
        <x-preview-agreement-modal />
        @include('components.modals.receipt-preview-modal')
    </div>
</x-app-layout>