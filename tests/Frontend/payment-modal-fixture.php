<?php
// Isolated rendering only: all browser requests are intercepted by the test.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array']);
view()->share('errors', new Illuminate\Support\ViewErrorBag());
echo Illuminate\Support\Facades\Blade::render(<<<'BLADE'
<div x-data="{ openPayment: false, shakePayment: false, paymentData: {} }">
    <x-modals.payment-modal />
</div>
BLADE);
