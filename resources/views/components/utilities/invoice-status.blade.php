@props(['record' => null, 'showStatus' => true, 'showChargeDetails' => true,
    'chargeLinkClass' => 'mt-1 block text-xs text-indigo-600 hover:underline'])
@if($showStatus)
@if($record?->invoice_item_id)
    @php($invoice = $record->invoiceItem?->invoice)
    <div class="mt-1 text-xs">
        <span class="font-semibold text-indigo-600">Invoice generated</span>
        @if($invoice)
            @canany(['super-admin', 'invoice.view invoice'])
                @if($invoice->documentTemplate)
                    <button type="button" data-document-url="{{ route('admin.invoices.document-data', $invoice) }}" data-document-title="Invoice: {{ $invoice->invoice_no }}"
                        @click="window.openLeaseInvoiceDocument($el.dataset.documentUrl, $el.dataset.documentTitle)"
                        class="ml-1 font-semibold text-indigo-600 hover:text-indigo-900 hover:underline">{{ $invoice->invoice_no }}</button>
                @else
                    <span class="ml-1 text-indigo-600">{{ $invoice->invoice_no }}</span>
                @endif
            @else
                <span class="ml-1 text-indigo-600">{{ $invoice->invoice_no }}</span>
            @endcanany
            <span class="text-gray-500">({{ ucfirst($invoice->status) }})</span>
        @endif
    </div>
@else
    @php($shares = $record?->allocations ?? collect())
    @if($shares->whereNotNull('invoice_item_id')->isNotEmpty())
        <div class="mt-1 text-xs font-semibold text-emerald-700">Allocated invoices generated</div>
    @elseif($shares->isNotEmpty())
        <div class="mt-1 text-xs text-gray-500">Allocated · Not invoiced</div>
    @else
        <div class="mt-1 text-xs text-gray-400">Not invoiced</div>
    @endif
@endif
@endif
@if($showChargeDetails && $record?->utility_service_id)
    @canany(['super-admin', 'utilities.reading history'])
        <a class="{{ $chargeLinkClass }}" href="{{ route('admin.utilities.charges.show', ['utility' => $record, 'management_user_id' => $record->utilityService?->management_user_id]) }}">Charge details</a>
    @endcanany
@endif
