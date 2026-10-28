<?php

// Isolated UI fixture: no login, database queries or application writes.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array']);
view()->share('errors', new Illuminate\Support\ViewErrorBag());
if (($argv[1] ?? '') === 'results') {
    $entry = [
        'lease_id' => '01K7FIXTURELEASE00000000001', 'kind' => 'invoice', 'status' => 'Unpaid', 'id' => 'invoice:fixture', 'date' => '27/10/2026', 'date_only' => false, 'recorded_at' => '27/10/2026 12:00:00',
        'label' => 'Invoice issued', 'source_type' => 'Current invoice status: unpaid', 'payment_method' => null,
        'party_label' => 'Billed to', 'party' => 'Fixture Tenant', 'payee' => 'Fixture Owner', 'issuer' => null,
        'invoice_no' => 'INV-FIXTURE-0001', 'receipt_no' => null, 'reference' => null,
        'fees' => [['name' => 'Rental', 'description' => 'Fixture monthly rental'], ['name' => 'Water', 'description' => null]],
        'description' => null, 'amount' => 'Charged RM1,000.00', 'amount_class' => 'text-slate-800',
    ];
    $rows = array_fill(0, 20, $entry);
    foreach ([
        1 => ['kind' => 'payment', 'label' => 'Invoice payment applied', 'status' => 'Recorded', 'amount' => 'Applied RM500.00', 'stored_paid' => 50000, 'stored_excess' => 0, 'linked_wallet' => []],
        2 => ['kind' => 'wallet_credit', 'label' => 'Wallet credit', 'status' => 'Recorded', 'amount' => 'Wallet credited RM200.00'],
        3 => ['kind' => 'wallet_payment', 'label' => 'Wallet payment', 'status' => 'Recorded', 'amount' => 'Wallet used RM100.00'],
        4 => ['status' => 'Paid'], 5 => ['status' => 'Partial'], 6 => ['status' => 'Overdue'],
    ] as $index => $changes) $rows[$index] = array_replace($entry, $changes);
    echo view('components.invoice-statement', [
        'statement' => new Illuminate\Pagination\LengthAwarePaginator($rows, 40, 20, 1, [
            'path' => $argv[2] ?? 'http://statement.test/statement', 'pageName' => 'statement_page',
        ]),
        'scopeLabel' => 'Fixture records',
        'showTenant' => str_contains($argv[2] ?? '', 'invoice-entry'),
        'summary' => ['outstanding' => 12345, 'wallet' => str_contains($argv[2] ?? '', 'lease-entry') ? 50000 : null],
    ])->render();
    exit;
}
echo Illuminate\Support\Facades\Blade::render(<<<'BLADE'
<div x-data>
    <button id="lease-entry" @click="$dispatch('open-modal', 'invoice-statement')">Lease View Statement</button>
    <button id="invoice-entry" @click="$dispatch('open-modal', 'invoice-statement')">Invoice View Statement</button>
    <x-invoice-statement-modal :endpoint="$endpoint" />
</div>
BLADE, ['endpoint' => $argv[2] ?? 'http://statement.test/statement']);
