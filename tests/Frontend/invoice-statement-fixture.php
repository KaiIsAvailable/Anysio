<?php

// Isolated UI fixture: no login, database queries or application writes.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array']);
view()->share('errors', new Illuminate\Support\ViewErrorBag());
if (($argv[1] ?? '') === 'results') {
    $entry = [
        'id' => 'invoice:fixture', 'date' => '27/10/2026 12:00', 'date_only' => false, 'recorded_at' => null,
        'label' => 'Invoice issued', 'source_type' => 'Current invoice status: unpaid', 'payment_method' => null,
        'party_label' => 'Billed to', 'party' => 'Fixture Tenant', 'payee' => 'Fixture Owner', 'issuer' => null,
        'invoice_no' => 'INV-FIXTURE-0001', 'receipt_no' => null, 'reference' => null,
        'fees' => [['name' => 'Rental', 'description' => 'Fixture monthly rental'], ['name' => 'Water', 'description' => null]],
        'description' => null, 'amount' => '-1,000.00', 'amount_class' => 'text-red-600',
    ];
    echo view('components.invoice-statement', [
        'statement' => new Illuminate\Pagination\LengthAwarePaginator(array_fill(0, 20, $entry), 40, 20, 1, [
            'path' => $argv[2] ?? 'http://statement.test/statement', 'pageName' => 'statement_page',
        ]),
        'scopeLabel' => 'Fixture records',
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
