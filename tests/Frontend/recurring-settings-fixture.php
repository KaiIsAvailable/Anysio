<?php
// Full production Settings view and layout; only account data is isolated in memory.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array']);
Illuminate\Support\Facades\DB::purge('sqlite');
foreach (['users' => ['name', 'email', 'role'], 'user_management' => ['user_id', 'deleted_at'],
    'invoices' => ['user_id', 'context', 'status', 'deleted_at']] as $table => $columns) {
    Illuminate\Support\Facades\Schema::create($table, function ($t) use ($columns) {
        $t->string('id')->primary(); $t->timestamps();
        foreach ($columns as $column) $t->string($column)->nullable();
    });
}
Illuminate\Support\Facades\Schema::create('settings', function ($t) {
    $t->id(); $t->string('user_id'); $t->string('key'); $t->text('value'); $t->boolean('is_active')->default(true); $t->timestamps();
});
Illuminate\Support\Facades\DB::table('users')->insert(['id' => 'fixture', 'name' => 'Settings Test', 'email' => 'test@example.test', 'role' => 'ownerAdmin']);
$user = App\Models\User::find('fixture');
Illuminate\Support\Facades\Auth::setUser($user);
Illuminate\Support\Facades\Gate::swap(new Illuminate\Auth\Access\Gate($app, fn () => $user));
Illuminate\Support\Facades\Gate::before(fn ($user, $ability) => $ability === 'owner-admin' || str_starts_with($ability, 'settings.'));
$values = json_decode($argv[1] ?? '{}', true, flags: JSON_THROW_ON_ERROR);
Illuminate\Support\Facades\DB::table('settings')->insert([
    ['user_id' => 'fixture', 'key' => 'pending_renewal_config', 'value' => '{"days":90,"number":3,"mode":"months"}'],
    ['user_id' => 'other', 'key' => 'due_date_config', 'value' => '{"days":19}'],
]);
$untouched = Illuminate\Support\Facades\DB::table('settings')->get()->toJson();
if ($values) Illuminate\Database\Eloquent\Model::withoutEvents(fn () => app(App\Http\Controllers\SettingsController::class)->update(
    Illuminate\Http\Request::create('/admin/settings', 'PATCH', ['due_date_config' => $values]), app(App\Services\SettingService::class)
));
if (Illuminate\Support\Facades\DB::table('settings')->where('key', 'pending_renewal_config')->orWhere('user_id', 'other')->get()->toJson() !== $untouched) {
    throw new RuntimeException('Independent settings were modified.');
}
view()->share('errors', new Illuminate\Support\ViewErrorBag());
$data = ['settings' => app(App\Services\SettingService::class)->getEffectiveSettings('fixture'), 'modules' => [], 'feeTypeCategoryOptions' => ['rent' => 'Rent']];
foreach (['rentFeeTypes', 'depositFeeTypes', 'utilityFeeTypes', 'serviceFeeTypes', 'penaltyFeeTypes', 'managementFeeTypes', 'roles'] as $key) $data[$key] = collect();
echo view('adminSide.setting.index', $data)->render();
