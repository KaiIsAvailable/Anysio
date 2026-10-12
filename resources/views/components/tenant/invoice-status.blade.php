@props(['invoice'])

@php
    // Presentation only: never use the time-of-day comparison in Invoice::isOverdue().
    $today = \Carbon\CarbonImmutable::now('Asia/Kuala_Lumpur')->startOfDay();
    $due = $invoice->due_date
        ? \Carbon\CarbonImmutable::parse($invoice->due_date->format('Y-m-d'), 'Asia/Kuala_Lumpur')->startOfDay()
        : null;
    $outstanding = !$invoice->isVoid() && !$invoice->isPaid() && $invoice->amount_balance > 0;
    $overdue = $outstanding && $due && $today->gt($due);
    $dueToday = $outstanding && $due && $today->equalTo($due);
    $paymentStatus = $invoice->status;
    // Legacy overdue values are not payment states. Show the payment position without changing storage.
    if ($paymentStatus === \App\Models\Invoice::STATUS_OVERDUE) {
        $paymentStatus = $invoice->amount_balance <= 0 ? 'paid' : ($invoice->amount_paid > 0 ? 'partial' : 'unpaid');
    }
    $label = $paymentStatus === \App\Models\Invoice::STATUS_PARTIAL ? 'Partially Paid' : ucfirst($paymentStatus ?? 'N/A');
    $statusClass = match ($paymentStatus) {
        'paid' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'partial' => 'bg-blue-50 text-blue-700 border-blue-200',
        'unpaid' => 'bg-amber-50 text-amber-700 border-amber-200',
        'void' => 'bg-gray-100 text-gray-500 border-gray-200',
        default => 'bg-gray-100 text-gray-600 border-gray-200',
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }}>
    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border {{ $statusClass }}">{{ $label }}</span>
    @if($overdue)
        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border bg-red-50 text-red-700 border-red-200">Overdue</span>
    @elseif($dueToday)
        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border bg-amber-50 text-amber-700 border-amber-200">Due Today</span>
    @endif
</div>
