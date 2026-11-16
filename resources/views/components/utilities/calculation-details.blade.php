@props(['snapshot' => [], 'previous' => null, 'current' => null, 'consumption' => null, 'amount' => null, 'amountLabel' => 'Calculated charge', 'inverse' => false])
@php
    $decimal = fn ($value, $places = 3) => rtrim(rtrim(number_format((float) $value, $places, '.', ','), '0'), '.');
    $money = fn ($cents) => 'RM '.number_format($cents / 100, 2);
    $method = $snapshot['method'] ?? data_get($snapshot, 'tariff.calculation');
    $event = $snapshot['meter_event'] ?? null;
@endphp
<div {{ $attributes->class(['space-y-1 text-xs tabular-nums', $inverse ? 'text-gray-200' : 'text-gray-600']) }}>
    @if(!in_array($method, ['fixed', 'actual_bill']) && $previous !== null && $current !== null && $consumption !== null)
        <p @class(['font-medium', $inverse ? 'text-white' : 'text-slate-800'])>
            @if(($event['type'] ?? '') === 'rollover')
                {{ $decimal($event['capacity']) }} − {{ $decimal($previous) }} + {{ $decimal($current) }}
            @elseif($event)
                ({{ $decimal($event['old_closing']) }} − {{ $decimal($previous) }}) + ({{ $decimal($current) }} − {{ $decimal($event['new_opening']) }})
            @else
                {{ $decimal($current) }} − {{ $decimal($previous) }}
            @endif
            = {{ $decimal($consumption) }} m³
        </p>
    @elseif($method === 'fixed')
        <p>Fixed monthly charge: RM {{ $decimal(data_get($snapshot, 'tariff.fixed_amount', '0'), 6) }}</p>
    @elseif($method === 'actual_bill')
        <p>Provider bill: RM {{ $decimal($snapshot['provider_bill_amount'] ?? '0', 2) }}</p>
        @if(!empty($snapshot['bill_reference']))<p>Bill reference: {{ $snapshot['bill_reference'] }}</p>@endif
    @endif
    @foreach($snapshot['blocks'] ?? [] as $block)
        <p>{{ $decimal($block['quantity']) }} m³ × RM {{ $decimal($block['rate'], 6) }} = RM {{ isset($block['amount_nano_rm']) ? rtrim(rtrim(\App\Services\UtilityDecimal::format((int) $block['amount_nano_rm'], 9), '0'), '.') : number_format(($block['amount'] ?? 0) / 100, 2) }}</p>
    @endforeach
    @if(data_get($snapshot, 'tariff.minimum_policy', 'none') !== 'none' && (float) ($snapshot['minimum_charge'] ?? 0) > 0)
        <p>Minimum charge: RM {{ $decimal($snapshot['minimum_charge'], 6) }} ({{ !empty($snapshot['minimum_applied']) ? 'applied' : 'not applied' }})</p>
    @endif
    @foreach($snapshot['adjustments'] ?? [] as $adjustment)
        <p>{{ $adjustment['reason'] }}: {{ $adjustment['amount'] >= 0 ? '+' : '−' }}{{ $money(abs($adjustment['amount'])) }}</p>
    @endforeach
    @if(!empty($snapshot['blocks']))<p class="text-gray-400">Total rounded to 2 decimal places.</p>@endif
    <p @class(['font-semibold', $inverse ? 'text-white' : 'text-slate-800'])>{{ $amountLabel }}: {{ $amount === null ? 'Not calculated' : $money($amount) }}</p>
</div>
