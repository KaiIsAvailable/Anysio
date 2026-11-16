@php
    $summary = session('wallet_auto_payment_summary');
    $initialHtml = $summary ? view('components.modals.wallet-payment-summary-content', compact('summary'))->render() : '';
@endphp
<div x-data="{ body: @js($initialHtml) }"
    @wallet-payment-summary.window="body = $event.detail.html; $nextTick(() => $dispatch('open-wallet-payment-summary'))">
    <x-modals.confirmation-modal id="wallet-payment-summary" title="Auto record payment using wallet" maxWidth="sm:max-w-3xl" :show="(bool) $summary">
        <div x-html="body">{!! $initialHtml !!}</div>
    </x-modals.confirmation-modal>
</div>
