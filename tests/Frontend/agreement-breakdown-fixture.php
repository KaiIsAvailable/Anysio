<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array']);
view()->share('errors', new Illuminate\Support\ViewErrorBag());
Illuminate\Support\Facades\Gate::before(fn ($user = null) => true);
if (($argv[1] ?? '') === 'descriptions') {
    $cases = [];
    foreach (['WIFI UNIT', 'STANDARD UNIT', ' wifi unit ', ' sTaNdArD uNiT ', 'Other', null, ''] as $type) {
        $property = (new App\Models\Property(['type' => $type]))->setRelation('owner', null);
        $lease = (new App\Models\Lease(['leasable_type' => App\Models\Property::class]))
            ->setRelation('leasable', $property)->setRelation('charges', collect())->setRelation('tenant', null);
        $cases[] = ['type' => $type, 'variables' => app(App\Services\LeaseService::class)->getAgreementVariables($lease)];
    }
    echo json_encode($cases);
    exit;
}
echo Illuminate\Support\Facades\Blade::render('<x-preview-invoice-modal />');
