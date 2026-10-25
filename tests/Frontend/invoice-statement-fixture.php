<?php

// Isolated UI fixture: no login, database queries or application writes.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array']);
view()->share('errors', new Illuminate\Support\ViewErrorBag());
if (($argv[1] ?? '') === 'results') {
    echo view('components.invoice-statement', [
        'statement' => new Illuminate\Pagination\LengthAwarePaginator([], 0, 20),
        'scopeLabel' => 'Fixture records',
    ])->render();
    exit;
}
echo Illuminate\Support\Facades\Blade::render(<<<'BLADE'
<div x-data>
    <button id="lease-entry" @click="$dispatch('open-modal', 'invoice-statement')">Lease View Statement</button>
    <button id="invoice-entry" @click="$dispatch('open-modal', 'invoice-statement')">Invoice View Statement</button>
    <x-invoice-statement-modal endpoint="http://statement.test/statement" />
</div>
BLADE);
