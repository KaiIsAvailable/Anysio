<?php

foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'stderr'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Auth::setUser(new App\Models\User(['name' => 'Manager', 'role' => 'admin']));
Illuminate\Support\Facades\Gate::before(fn () => true);
$module = in_array($argv[1] ?? '', ['owner', 'tenant', 'property', 'form']) ? $argv[1] : 'owner';
if ($module === 'form') {
    $errors = (new Illuminate\Support\ViewErrorBag)->put('default', new Illuminate\Support\MessageBag(['floor' => ['Invalid floor']]));
    view()->share('errors', $errors);
    echo Illuminate\Support\Facades\Blade::render('<x-form.form id="validation-fixture" :focus-errors="true">
        <x-form.text-input name="floor" value="G" pattern="G|(?:\\+|-)?[0-9]+" />
        <x-form.input-select name="owner_id" :options="[\'owner-choice\' => \'Owner Example\']" value="owner-choice" />
        <x-form.text-input name="room_no" required />
        <x-form.primary-button loading="loading">Save</x-form.primary-button>
    </x-form.form>', ['errors' => $errors]);

    return;
}
$source = file_get_contents(resource_path($module === 'property' ? 'views/adminSide/rooms/property/bulk-edit.blade.php' : 'views/adminSide/'.$module.'s/bulk-edit.blade.php'));
$source = str_replace(['<x-app-layout>', '</x-app-layout>'], '', $source);
$records = new Illuminate\Pagination\LengthAwarePaginator([], 0, 50);
$row = ['key' => 'owner-fixture', 'name' => 'Owner Example', 'email' => 'owner@example.test', 'phone' => '0123456789', 'ic' => '900101145678', 'gender' => 'Male', 'state' => 'Perak', 'type' => 'individual', 'nationality' => 'MALAYSIAN', 'contacts' => [['name' => 'Contact', 'phone' => '0199999999', 'relationship' => 'Parent']], 'accountLastFour' => '5678'];
if ($module === 'property') {
    $row = ['key' => 'property-fixture', 'name' => 'Property Example', 'ownerId' => 'owner-choice', 'propertyType' => 'Condo',
        'address' => '12 Main Street', 'postcode' => '01234', 'city' => 'Ipoh', 'state' => 'Perak'];
}
$gridConfig = $module === 'property' ? ['hierarchy' => true, 'permissions' => array_fill_keys(['property', 'unit', 'room'], ['view' => true, 'create' => true, 'edit' => true, 'delete' => true])] : [];
echo Illuminate\Support\Facades\Blade::render($source, ['rows' => collect([$row]), 'records' => $records, 'tenants' => $records, 'canEditBank' => true,
    'draftKey' => $module.'-fixture', 'gridConfig' => $gridConfig, 'ownerOptions' => ['' => 'No owner', 'owner-choice' => 'Owner Example (owner@example.test)'], 'saveRequestKey' => (string) Illuminate\Support\Str::uuid()]);
