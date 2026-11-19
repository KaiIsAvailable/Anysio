<?php

namespace Tests\Feature;

use App\FeeTypeCategory;
use App\Http\Controllers\{FeeTypeController, SettingsController};
use App\Models\{FeeType, Setting, User};
use App\Services\{FeeTypeService, SettingService};
use Illuminate\Database\Eloquent\{Model, ModelNotFoundException};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FeeTypeCrudTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary(); $table->string('role'); $table->text('account_permissions')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_01_03_144458_create_fee_type_table.php'))->up();
        (require database_path('migrations/2026_10_20_112823_add_prices_to_fee_types_table.php'))->up();
        Schema::create('settings', function (Blueprint $table) {
            $table->id(); $table->string('user_id'); $table->string('key'); $table->text('value');
            $table->boolean('is_active')->default(true); $table->timestamps(); $table->unique(['user_id', 'key']);
        });
        foreach (FeeTypeService::REFERENCE_TABLES as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id(); $table->foreignUlid('fee_type_id')->nullable()->constrained('fee_types')->nullOnDelete();
                $table->integer('amount')->default(1111); $table->softDeletes();
            });
        }
        (require database_path('migrations/2026_11_18_000002_protect_referenced_fee_types.php'))->up();
        foreach (['a', 'b'] as $id) DB::table('users')->insert([
            'id' => $id, 'role' => 'ownerAdmin',
            'account_permissions' => json_encode(array_merge(array_map(fn ($action) => 'fee types.'.$action, ['view', 'create', 'edit', 'change status', 'delete']), FeeTypeService::categoryPermissions())),
        ]);
        $this->actingAs(User::findOrFail('a'));
    }

    private function fee(array $values = []): FeeType
    {
        return Model::withoutEvents(fn () => FeeType::create($values + [
            'user_id' => 'a', 'name' => 'Custom Fee', 'category' => 'item', 'is_system' => false, 'is_active' => true,
        ]));
    }

    private function request(array $data = [], string $method = 'POST'): Request
    {
        $request = Request::create('/fee-types', $method, $data, server: ['HTTP_ACCEPT' => 'application/json']);
        $this->app->instance('request', $request);
        return $request;
    }

    private function mutate(callable $action)
    {
        return Model::withoutEvents($action);
    }

    private function prepareSettingsPage(): void
    {
        config(['permissions.modules' => [
            'profile menu' => ['setting'], 'settings' => ['payment tab', 'lease tab', 'user role tab'],
            'fee types' => config('permissions.modules.fee types'),
        ]]);
        view()->share('errors', new ViewErrorBag);
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable(); $table->string('email')->nullable();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id(); $table->string('team_id'); $table->string('name'); $table->string('guard_name');
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('guard_name'); $table->timestamps();
        });
        Schema::create('user_management', function (Blueprint $table) {
            $table->string('id')->primary(); $table->string('user_id'); $table->softDeletes();
        });
        Schema::create('invoices', function (Blueprint $table) {
            $table->string('id')->primary(); $table->string('user_id'); $table->string('context'); $table->string('status'); $table->softDeletes(); $table->timestamps();
        });
    }

    public function test_settings_preloads_authorized_fees_once_and_switches_tabs_without_navigation(): void
    {
        $this->prepareSettingsPage();
        $own = $this->fee(['name' => 'Preloaded account fee']);
        $this->fee(['user_id' => 'b', 'name' => 'Other account fee']);
        $actor = User::findOrFail('a');
        $actor->account_permissions = array_merge($actor->account_permissions, ['profile menu.setting', 'settings.payment tab', 'settings.lease tab', 'settings.user role tab']);
        $this->actingAs($actor);
        $this->request([], 'GET');
        DB::enableQueryLog();
        $view = app(SettingsController::class)->index(app(SettingService::class));
        $this->assertSame([$own->id], $view->getData()['feeTypes']->pluck('id')->all());
        $html = $view->with('errors', new ViewErrorBag)->render();
        $feeQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "fee_types"'));
        $this->assertCount(1, $feeQueries);
        DB::disableQueryLog();
        $this->assertStringContainsString('@click="activeTab = \'fee-types\'"', $html);
        $this->assertStringContainsString('x-show="activeTab === \'fee-types\'"', $html);
        $this->assertStringContainsString('Preloaded account fee', $html);
        $this->assertStringNotContainsString('Other account fee', $html);
        $this->assertStringNotContainsString('loadingFeeTypes', $html);
        $this->assertStringContainsString('@click="activeTab = \'payment\'"', $html);

        $data = app(FeeTypeController::class)->index($this->request([], 'GET'))->getData();
        $this->assertArrayNotHasKey('feeTypesOnly', $data);
        $this->assertSame('fee-types', request('tab'));
        $this->assertArrayHasKey('roles', $data);
    }

    public function test_settings_without_fee_view_permission_does_not_query_or_render_fees(): void
    {
        $this->prepareSettingsPage();
        $this->fee(['name' => 'Private fee']);
        $actor = User::findOrFail('a');
        $actor->account_permissions = ['profile menu.setting', 'settings.payment tab'];
        $this->actingAs($actor);
        $this->request([], 'GET');
        DB::enableQueryLog();
        $view = app(SettingsController::class)->index(app(SettingService::class));
        $this->assertArrayNotHasKey('feeTypes', $view->getData());
        $html = $view->with('errors', new ViewErrorBag)->render();
        $this->assertFalse(collect(DB::getQueryLog())->contains(fn ($query) => str_contains($query['query'], 'from "fee_types"')));
        DB::disableQueryLog();
        $this->assertStringNotContainsString('Private fee', $html);
        $this->assertStringNotContainsString('@click="activeTab = \'fee-types\'"', $html);
    }

    public function test_system_settings_preloads_fees_alongside_account_permissions(): void
    {
        $this->prepareSettingsPage();
        $actor = User::findOrFail('a');
        $actor->role = 'admin';
        $this->actingAs($actor);
        $this->fee(['is_system' => true, 'name' => 'Shared system fee']);
        $this->request([], 'GET');
        $view = app(SettingsController::class)->index(app(SettingService::class));
        $this->assertSame('adminSide.setting.account-permissions', $view->name());
        $this->assertCount(1, $view->getData()['feeTypes']);
        $html = $view->with('errors', new ViewErrorBag)->render();
        $this->assertStringContainsString('@click="activeTab = \'fee-types\'"', $html);
        $this->assertStringContainsString('@click="activeTab = \'accounts\'"', $html);
        $this->assertStringNotContainsString('loadingFeeTypes', $html);
    }

    public function test_adding_a_fee_from_settings_preserves_the_active_fee_tab(): void
    {
        $request = $this->request(['name' => 'New settings fee', 'category' => 'invoice', 'tab' => 'fee-types']);
        $request->headers->set('Accept', 'text/html');
        $response = $this->mutate(fn () => app(FeeTypeController::class)->store($request));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertTrue(session('fee_types_tab'));
    }

    public function test_hidden_categories_cannot_be_found_created_edited_enabled_or_deleted(): void
    {
        $controller = app(FeeTypeController::class);
        foreach (FeeTypeCategory::cases() as $category) {
            $fee = $this->fee(['name' => 'Hidden '.$category->value, 'category' => $category]);
            $actor = User::findOrFail('a');
            $actor->account_permissions = array_values(array_diff($actor->account_permissions, ['fee types.'.$category->value]));
            $this->actingAs($actor);
            $view = $controller->index($this->request(['search' => $fee->name], 'GET'));
            $this->assertCount(0, $view->getData()['feeTypes']);
            $this->assertArrayNotHasKey($category->value, $view->getData()['categoryOptions']);
            foreach ([
                fn () => $controller->store($this->request(['name' => 'Forged', 'category' => $category->value])),
                fn () => $controller->update($this->request(['name' => 'Forged', 'category' => $category->value]), $fee),
                fn () => $controller->changeStatus($this->request(['is_active' => false]), $fee),
                fn () => $controller->destroy($this->request(), $fee),
                fn () => $controller->updateBatch($this->request(['fees' => [['id' => $fee->id, 'name' => 'Forged']]])),
            ] as $action) {
                $response = $this->mutate($action);
                $this->assertSame(403, $response->getStatusCode());
                $this->assertSame('You have no permission.', session('error'));
            }
            $this->assertSame($fee->name, $fee->fresh()->name);
            $this->assertTrue($fee->fresh()->is_active);
        }
    }

    public function test_visible_fee_cannot_be_moved_into_a_hidden_category_or_batched_with_one(): void
    {
        $visible = $this->fee(['name' => 'Visible invoice', 'category' => 'invoice']);
        $hidden = $this->fee(['name' => 'Hidden deposit', 'category' => 'deposit']);
        $actor = User::findOrFail('a');
        $actor->account_permissions = array_values(array_diff($actor->account_permissions, ['fee types.deposit']));
        $this->actingAs($actor);
        $controller = app(FeeTypeController::class);
        $this->assertSame(403, $controller->update($this->request(['name' => 'Moved', 'category' => 'deposit']), $visible)->getStatusCode());
        $response = $this->mutate(fn () => $controller->updateBatch($this->request(['fees' => [
            ['id' => $visible->id, 'name' => 'Changed'], ['id' => $hidden->id, 'is_active' => false],
        ]])));
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Visible invoice', $visible->fresh()->name);
        $this->assertTrue($hidden->fresh()->is_active);
    }

    public function test_invoice_catalog_and_manual_invoice_validation_reject_hidden_categories(): void
    {
        $fee = $this->fee(['category' => 'invoice']);
        $actor = User::findOrFail('a');
        $actor->account_permissions = array_values(array_diff($actor->account_permissions, ['fee types.invoice']));
        $this->actingAs($actor);
        $this->assertCount(0, app(FeeTypeService::class)->getActiveInvoiceFeeTypes());
        $request = \App\Http\Requests\Invoice\StoreInvoiceRequest::create('/', 'POST', ['items' => [['fee_type_id' => $fee->id]]]);
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), ['items.*.fee_type_id' => $request->rules()['items.*.fee_type_id']]);
        $this->assertTrue($validator->fails());
        $this->assertSame($fee->id, FeeType::findOrFail($fee->id)->id, 'Historical fee references remain resolvable.');

        $deposit = $this->fee(['name' => 'Security deposit', 'category' => 'deposit']);
        $actor->account_permissions = array_values(array_diff($actor->account_permissions, ['fee types.deposit']));
        $this->actingAs($actor);
        $leaseRequest = \App\Http\Requests\Lease\StoreLeaseRequest::create('/', 'POST', ['status' => 'New', 'charges' => [['fee_type_id' => $deposit->id]]]);
        $validator = \Illuminate\Support\Facades\Validator::make($leaseRequest->all(), ['charges.*.fee_type_id' => $leaseRequest->rules()['charges.*.fee_type_id']]);
        $this->assertTrue($validator->fails());
    }

    public function test_category_permission_migration_preserves_access_and_existing_partial_choices(): void
    {
        foreach (['permissions', 'roles'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id(); $table->string('name'); $table->string('guard_name'); $table->timestamps();
                if ($name === 'roles') $table->string('team_id')->nullable();
            });
        }
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->integer('role_id'); $table->integer('permission_id'); $table->primary(['role_id', 'permission_id']);
        });
        DB::table('users')->where('id', 'a')->update(['account_permissions' => json_encode(['fee types.view'])]);
        DB::table('users')->where('id', 'b')->update(['account_permissions' => json_encode(['fee types.view', 'fee types.deposit'])]);
        DB::table('users')->insert(['id' => 'json-null', 'role' => 'ownerAdmin', 'account_permissions' => 'null']);
        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'Existing role', 'guard_name' => 'web'],
            ['id' => 2, 'name' => 'Partial role', 'guard_name' => 'web'],
        ]);
        DB::table('permissions')->insert(['id' => 1, 'name' => 'fee types.deposit', 'guard_name' => 'web']);
        DB::table('role_has_permissions')->insert(['role_id' => 2, 'permission_id' => 1]);
        $migration = require database_path('migrations/2026_11_18_000003_add_fee_type_category_permissions.php');
        $migration->up();
        $this->assertCount(8, DB::table('role_has_permissions')->where('role_id', 1)->get());
        $this->assertCount(1, DB::table('role_has_permissions')->where('role_id', 2)->get());
        $this->assertSame(array_merge(['fee types.view'], FeeTypeService::categoryPermissions()), User::findOrFail('a')->account_permissions);
        $this->assertSame(['fee types.view', 'fee types.deposit'], User::findOrFail('b')->account_permissions);
        $this->assertSame(FeeTypeService::categoryPermissions(), User::findOrFail('json-null')->account_permissions);
        DB::table('role_has_permissions')->where('role_id', 1)->where('permission_id', 1)->delete();
        $migration->up();
        $this->assertCount(7, DB::table('role_has_permissions')->where('role_id', 1)->get());
        $this->assertSame(0, FeeType::count());
    }

    public function test_permissions_are_required_before_listing_or_mutating(): void
    {
        $fee = $this->fee();
        $actor = User::findOrFail('a');
        $actor->account_permissions = [];
        $this->actingAs($actor);
        $controller = app(FeeTypeController::class);
        $this->assertSame('errors.403', $controller->index($this->request([], 'GET'))->name());
        foreach ([
            fn () => $controller->store($this->request(['name' => 'New', 'category' => 'item'])),
            fn () => $controller->update($this->request(['name' => 'New', 'category' => 'item']), $fee),
            fn () => $controller->changeStatus($this->request(['is_active' => 0]), $fee),
            fn () => $controller->destroy($this->request(), $fee),
        ] as $action) {
            $this->assertSame(302, $action()->getStatusCode());
            $this->assertSame('You have no permission.', session('error'));
        }
        $this->assertSame(1, FeeType::count());
        $this->assertTrue($fee->fresh()->is_active);
    }

    public function test_quick_add_keeps_its_permission_and_json_shape_and_validates_the_actual_enum(): void
    {
        $actor = User::findOrFail('a');
        $actor->account_permissions = array_merge(['leases.add fee type'], FeeTypeService::categoryPermissions());
        $this->actingAs($actor);
        foreach (FeeTypeCategory::cases() as $category) {
            $response = $this->mutate(fn () => app(FeeTypeController::class)->store($this->request([
                'name' => 'Quick '.$category->value, 'category' => $category->value,
                'original_price' => $category === FeeTypeCategory::ITEM ? 1000 : null,
                'sell_price' => $category === FeeTypeCategory::ITEM ? 2000 : null,
                'user_id' => 'b', 'is_system' => true,
            ])));
            $this->assertSame(200, $response->getStatusCode());
            $data = $response->getData(true);
            $this->assertTrue($data['success']);
            $this->assertSame($category->value, $data['feeType']['category']);
            $this->assertSame('a', $data['feeType']['user_id']);
            $this->assertFalse($data['feeType']['is_system']);
            $this->assertSame('success', $data['session_status']['type']);
        }
        $response = app(FeeTypeController::class)->store($this->request(['name' => 'Wrong', 'category' => 'expense']));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('category', $response->getData(true)['errors']);
        $this->assertNotEmpty(session('error'));
    }

    public function test_duplicate_names_and_legacy_setting_key_collisions_are_prevented(): void
    {
        $this->fee(['name' => 'Cleaning Fee']);
        foreach ([' cleaning fee ', 'Cleaning-Fee', "\u{00A0}"] as $name) {
            $response = $this->mutate(fn () => app(FeeTypeController::class)->store($this->request(['name' => $name, 'category' => 'item'])));
            $this->assertSame(422, $response->getStatusCode());
        }
        $this->mutate(fn () => app(FeeTypeService::class)->createFeeType(['name' => 'Cleaning Fee', 'category' => 'service']));
        $this->actingAs(User::findOrFail('b'));
        $this->mutate(fn () => app(FeeTypeService::class)->createFeeType(['name' => 'Cleaning Fee', 'category' => 'item']));
        $this->assertSame(3, FeeType::count());
    }

    public function test_other_accounts_custom_fees_are_not_listed_or_mutable(): void
    {
        $foreign = $this->fee(['user_id' => 'b', 'name' => 'Other Account']);
        $own = $this->fee();
        $view = app(FeeTypeController::class)->index($this->request([], 'GET'));
        $this->assertSame([$own->id], $view->getData()['feeTypes']->pluck('id')->all());
        foreach ([
            fn () => app(FeeTypeService::class)->update($foreign, ['name' => 'Forged', 'category' => 'item']),
            fn () => app(FeeTypeService::class)->changeStatus($foreign, false),
            fn () => app(FeeTypeService::class)->delete($foreign),
        ] as $action) {
            try { $this->mutate($action); $this->fail('Cross-account mutation must fail.'); }
            catch (ModelNotFoundException) { $this->assertTrue($foreign->fresh()->is_active); }
        }
    }

    public function test_system_identity_and_deletion_are_protected_and_status_is_account_local(): void
    {
        $fee = $this->fee(['user_id' => 'b', 'is_system' => true, 'name' => 'System Service', 'category' => 'service']);
        $duplicate = $this->mutate(fn () => app(FeeTypeController::class)->store($this->request(['name' => 'System Service', 'category' => 'service'])));
        $this->assertSame(422, $duplicate->getStatusCode());
        foreach ([
            fn () => app(FeeTypeService::class)->update($fee, ['name' => 'Changed', 'category' => 'item']),
            fn () => app(FeeTypeService::class)->delete($fee),
        ] as $action) {
            try { $this->mutate($action); $this->fail('System mutation must fail.'); }
            catch (ValidationException) { $this->assertSame('System Service', $fee->fresh()->name); }
        }
        $this->mutate(fn () => app(FeeTypeService::class)->changeStatus($fee, false));
        $this->assertTrue($fee->fresh()->is_active);
        $settings = app(SettingService::class);
        $this->assertFalse($settings->isFeeTypeActive($fee, userId: 'a'));
        $this->assertTrue($settings->isFeeTypeActive($fee, userId: 'b'));
        $this->assertSame(1, Setting::where('user_id', 'a')->count());
        $this->assertSame(0, Setting::where('user_id', 'b')->count());
    }

    public static function referenceTables(): array
    {
        return array_map(fn ($name) => [$name], FeeTypeService::REFERENCE_TABLES);
    }

    #[DataProvider('referenceTables')]
    public function test_every_reference_blocks_category_changes_and_deletion_but_allows_renaming_status_and_prices(string $table): void
    {
        $fee = $this->fee(['original_price' => 1000, 'sell_price' => 1500]);
        DB::table($table)->insert(['fee_type_id' => $fee->id, 'amount' => 1111, 'deleted_at' => now()]);
        foreach ([
            fn () => app(FeeTypeService::class)->delete($fee),
            fn () => app(FeeTypeService::class)->update($fee, ['name' => $fee->name, 'category' => 'service']),
        ] as $action) {
            try { $this->mutate($action); $this->fail('Referenced fees cannot change category or be deleted.'); }
            catch (ValidationException) { $this->assertSame('Custom Fee', $fee->fresh()->name); }
        }
        $this->mutate(fn () => app(FeeTypeService::class)->changeStatus($fee, false));
        $response = $this->mutate(fn () => app(FeeTypeController::class)->updateBatch($this->request(['fees' => [
            ['id' => $fee->id, 'name' => 'Changed', 'sell_price' => 2500],
        ]], 'PUT')));
        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame('Changed', $fee->fresh()->name);
        $this->assertFalse(Setting::sole()->value['item_changed']['is_active']);
        $this->assertArrayNotHasKey('item_custom_fee', Setting::sole()->value);
        $this->assertSame(2500, (int) $fee->fresh()->sell_price);
        $this->assertSame(1111, (int) DB::table($table)->value('amount'));
        $this->assertSame($fee->id, DB::table($table)->value('fee_type_id'));
        $this->assertFalse($fee->fresh()->is_active);
    }

    public function test_renaming_an_unused_custom_fee_moves_its_existing_activation_key_and_it_can_be_deleted(): void
    {
        $fee = $this->fee();
        $this->mutate(fn () => app(FeeTypeService::class)->changeStatus($fee, false));
        $this->mutate(fn () => app(FeeTypeService::class)->update($fee, ['name' => 'Renamed Fee', 'category' => 'item']));
        $value = Setting::sole()->value;
        $this->assertArrayNotHasKey('item_custom_fee', $value);
        $this->assertFalse($value['item_renamed_fee']['is_active']);
        $this->mutate(fn () => app(FeeTypeService::class)->delete($fee));
        $this->assertSame(0, FeeType::count());
        $this->assertArrayNotHasKey('item_renamed_fee', Setting::sole()->value);
    }

    public function test_global_and_account_activation_are_both_required_and_quick_add_catalog_excludes_inactive_fees(): void
    {
        $fee = $this->fee(['category' => 'invoice']);
        $service = app(FeeTypeService::class);
        $this->assertCount(1, $service->getActiveInvoiceFeeTypes());
        $this->mutate(fn () => $service->changeStatus($fee, false));
        $this->assertCount(0, $service->getActiveInvoiceFeeTypes());
        $this->assertFalse(app(SettingService::class)->isFeeTypeActive($fee->fresh()));
        $this->mutate(fn () => $service->changeStatus($fee, true));
        $this->assertCount(1, $service->getActiveInvoiceFeeTypes());
        $this->mutate(fn () => $fee->update(['is_active' => false]));
        $this->assertFalse(app(SettingService::class)->isFeeTypeActive($fee, userId: 'a'));
    }

    #[DataProvider('referenceTables')]
    public function test_database_itself_rejects_deletion_of_a_newly_referenced_fee(string $table): void
    {
        $fee = $this->fee();
        DB::table($table)->insert(['fee_type_id' => $fee->id]);
        try {
            DB::table('fee_types')->where('id', $fee->id)->delete();
            $this->fail('Foreign keys must prevent silent reference removal.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertSame($fee->id, DB::table($table)->value('fee_type_id'));
            $this->assertNotNull($fee->fresh());
        }
    }

    public function test_deleting_a_legacy_duplicate_preserves_the_surviving_fees_activation_key(): void
    {
        $first = $this->fee();
        $second = $this->fee();
        $service = app(FeeTypeService::class);
        $this->mutate(fn () => $service->changeStatus($first, false));
        $this->mutate(fn () => $service->delete($first));
        $this->assertArrayHasKey($second->settingKey(), Setting::sole()->value);
        $this->assertFalse(app(SettingService::class)->isFeeTypeActive($second->fresh()));
    }

    public function test_editor_and_confirmation_dialogs_render_for_authorized_actions(): void
    {
        $fee = $this->fee(['sell_price' => 1500]);
        $view = app(FeeTypeController::class)->index($this->request([], 'GET'));
        $html = view('adminSide.setting.partials.fee-types', $view->getData() + ['errors' => new ViewErrorBag])->render();
        foreach (['Add Fee Type', 'Save Fee Type', 'Delete Fee Type', 'x-show="savingFee"', 'x-show="deletingFee"',
            'open-fee-type-editor', 'open-delete-fee-type', route('admin.fee-types.batch'), 'Save Changes', 'sticky bottom-0',
            'Enable Edit', 'x-model="feeCategories"', 'overflow-visible', 'role="switch"',
            'x-show="feeCategories[0] === \'item\'"', 'Original Price (RM)', 'Sell Price (RM)'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function test_category_filter_preserves_search_and_account_boundaries(): void
    {
        $rent = $this->fee(['name' => 'Filter Rent', 'category' => 'rent']);
        $shared = $this->fee(['user_id' => 'b', 'is_system' => true, 'name' => 'Filter Shared', 'category' => 'rent']);
        $this->fee(['name' => 'Filter Item']);
        $this->fee(['user_id' => 'b', 'name' => 'Filter Foreign', 'category' => 'rent']);
        $request = $this->request(['category' => 'rent', 'search' => 'Filter'], 'GET');
        $fees = app(FeeTypeController::class)->index($request)->getData()['feeTypes'];
        $this->assertSame([$rent->id, $shared->id], $fees->pluck('id')->all());
        $this->assertCount(2, $fees);
        $this->expectException(ValidationException::class);
        app(FeeTypeController::class)->index($this->request(['category' => 'expense'], 'GET'));
    }

    public function test_used_item_has_editable_name_and_price_fields_and_a_disabled_delete_action(): void
    {
        $fee = $this->fee(['sell_price' => 1500]);
        DB::table('invoice_items')->insert(['fee_type_id' => $fee->id]);
        $view = app(FeeTypeController::class)->index($this->request([], 'GET'));
        $html = view('adminSide.setting.partials.fee-types', $view->getData() + ['errors' => new ViewErrorBag])->render();
        $this->assertStringContainsString('In use — cannot delete', $html);
        $this->assertDoesNotMatchRegularExpression('/id="fee-name-'.preg_quote($fee->id, '/').'"[^>]*readonly/', $html);
        $this->assertStringContainsString('This fee is in use and cannot be deleted.', $html);
        $this->assertStringContainsString('rows[rowId].original_price', $html);
        $this->assertStringContainsString('rows[rowId].sell_price', $html);
    }

    public function test_inline_save_and_status_changes_keep_editing_enabled(): void
    {
        $fee = $this->fee();
        $controller = app(FeeTypeController::class);
        $response = $this->mutate(fn () => $controller->update($this->request([
            'name' => $fee->name, 'category' => 'item', 'original_price' => 750, 'sell_price' => 1500, 'editingFees' => 1,
        ]), $fee));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertTrue(session('fee_types_editing'));
        $this->assertSame(1500, (int) $fee->fresh()->sell_price);
        $this->mutate(fn () => $controller->changeStatus($this->request(['is_active' => 0, 'editingFees' => 1]), $fee));
        $this->assertTrue(session('fee_types_editing'));
        $this->assertFalse($fee->fresh()->is_active);
    }

    public function test_confirmation_modal_keeps_default_overflow_behavior_for_other_usages(): void
    {
        $html = \Illuminate\Support\Facades\Blade::render('<x-modals.confirmation-modal id="other-dialog">Body</x-modals.confirmation-modal>');
        $this->assertStringContainsString('overflow-hidden', $html);
        $this->assertStringNotContainsString('overflow-visible', $html);
    }

    public function test_legacy_activation_saves_merge_account_keys_and_reject_foreign_or_controlled_overrides(): void
    {
        $first = $this->fee(['name' => 'First']);
        $second = $this->fee(['name' => 'Second']);
        $service = app(FeeTypeService::class);
        $this->mutate(fn () => $service->saveActivationSettings('a', [$first->settingKey() => ['is_active' => 'false']]));
        $this->mutate(fn () => $service->saveActivationSettings('a', [$second->settingKey() => ['is_active' => 'true']]));
        $this->assertFalse(Setting::sole()->value[$first->settingKey()]['is_active']);
        $this->assertTrue(Setting::sole()->value[$second->settingKey()]['is_active']);
        $foreign = $this->fee(['user_id' => 'b', 'name' => 'Foreign']);
        try {
            $this->mutate(fn () => $service->saveActivationSettings('a', [$foreign->settingKey() => ['is_active' => true]]));
            $this->fail('Foreign fee keys must not be saved.');
        } catch (ValidationException) {
            $this->assertArrayNotHasKey($foreign->settingKey(), Setting::sole()->value);
        }
        $penalty = $this->fee(['name' => 'Late Payment Penalty', 'category' => 'service', 'is_system' => true]);
        try {
            $this->mutate(fn () => $service->changeStatus($penalty, false));
            $this->fail('The late penalty master setting must remain authoritative.');
        } catch (ValidationException) {
            $this->assertTrue($penalty->fresh()->is_active);
        }
    }

    public function test_settings_tab_shows_all_matching_fees_without_pagination_or_other_settings_permissions(): void
    {
        for ($i = 0; $i < 19; $i++) $this->fee(['name' => 'Fee '.$i]);
        $actor = User::findOrFail('a');
        $actor->account_permissions = array_merge(['fee types.view'], FeeTypeService::categoryPermissions());
        $this->actingAs($actor);
        $request = $this->request(['tab' => 'fee-types'], 'GET');
        $view = app(SettingsController::class)->index(app(SettingService::class));
        $data = $view->getData();
        $this->assertTrue($data['feeTypesOnly']);
        $this->assertCount(19, $data['feeTypes']);
        $html = view('adminSide.setting.partials.fee-types', $data + ['errors' => new ViewErrorBag])->render();
        $this->assertStringContainsString('Search fee name or category', $html);
        $this->assertStringNotContainsString('Add Fee Type', $html);
        $this->assertStringNotContainsString('Save Fee Type', $html);
        $this->assertStringContainsString('lg:columns-2', $html);
        $this->assertStringContainsString('break-inside-avoid', $html);
        $this->assertStringNotContainsString('page=', $html);
        $request->query->set('search', 'Fee 18');
        $filtered = app(FeeTypeController::class)->index($request)->getData()['feeTypes'];
        $this->assertCount(1, $filtered);
    }

    public function test_batch_save_applies_only_submitted_changes_and_preserves_historical_amounts(): void
    {
        $fee = $this->fee(['original_price' => 1000, 'sell_price' => 1500]);
        $untouched = $this->fee(['name' => 'Unchanged']);
        DB::table('invoice_items')->insert(['fee_type_id' => $fee->id, 'amount' => 1500]);
        $response = $this->mutate(fn () => app(FeeTypeController::class)->updateBatch($this->request(['fees' => [
            ['id' => $fee->id, 'sell_price' => 2200, 'is_active' => false],
        ]], 'PUT')));
        $this->assertTrue($response->getData(true)['success']);
        $this->assertFalse($response->getData(true)['fees'][0]['effective_active']);
        $this->assertSame(2200, (int) $fee->fresh()->sell_price);
        $this->assertSame(1000, (int) $fee->fresh()->original_price);
        $this->assertSame(1500, DB::table('invoice_items')->value('amount'));
        $this->assertTrue($untouched->fresh()->is_active);
        $this->assertSame('Unchanged', $untouched->fresh()->name);
        $route = app('router')->getRoutes()->match(Request::create(route('admin.fee-types.batch'), 'PUT'));
        $this->assertSame('updateBatch', $route->getActionMethod());
    }

    public function test_batch_checks_edit_and_status_permissions_independently_before_saving(): void
    {
        $fee = $this->fee();
        $controller = app(FeeTypeController::class);
        foreach ([['fee types.edit', ['is_active' => false]], ['fee types.change status', ['name' => 'Denied']], ['fee types.view', ['name' => 'Denied']]] as [$permission, $patch]) {
            $actor = User::findOrFail('a');
            $actor->account_permissions = array_merge([$permission], FeeTypeService::categoryPermissions());
            $this->actingAs($actor);
            $response = $controller->updateBatch($this->request(['fees' => [['id' => $fee->id] + $patch]], 'PUT'));
            $this->assertSame(302, $response->getStatusCode());
            $this->assertSame('You have no permission.', session('error'));
            $this->assertSame('Custom Fee', $fee->fresh()->name);
            $this->assertTrue($fee->fresh()->is_active);
        }
        $actor->account_permissions = array_merge(['fee types.change status'], FeeTypeService::categoryPermissions());
        $response = $this->mutate(fn () => $controller->updateBatch($this->request(['fees' => [['id' => $fee->id, 'is_active' => false]]], 'PUT')));
        $this->assertTrue($response->getData(true)['success']);
        $this->assertFalse($fee->fresh()->is_active);
    }

    public function test_batch_is_atomic_when_any_fee_has_a_duplicate_or_locked_identity(): void
    {
        $first = $this->fee(['name' => 'First']);
        $second = $this->fee(['name' => 'Second']);
        $this->fee(['name' => 'Existing']);
        $controller = app(FeeTypeController::class);
        foreach (['Existing', 'Renamed'] as $name) {
            if ($name === 'Renamed') $this->mutate(fn () => $second->update(['is_system' => true]));
            $response = $this->mutate(fn () => $controller->updateBatch($this->request(['fees' => [
                ['id' => $first->id, 'name' => 'First renamed', 'is_active' => false],
                ['id' => $second->id, 'name' => $name],
            ]], 'PUT')));
            $this->assertSame(422, $response->getStatusCode());
            $this->assertArrayHasKey($name === 'Existing' ? 'fees.1.name' : 'fees.1.fee_type', $response->getData(true)['errors']);
            $this->assertSame('First', $first->fresh()->name);
            $this->assertTrue($first->fresh()->is_active);
            $this->assertSame(0, Setting::count());
        }
    }

    public function test_batch_rejects_cross_account_fees_before_applying_any_changes(): void
    {
        $own = $this->fee();
        $foreign = $this->fee(['user_id' => 'b']);
        try {
            $this->mutate(fn () => app(FeeTypeController::class)->updateBatch($this->request(['fees' => [
                ['id' => $own->id, 'is_active' => false], ['id' => $foreign->id, 'name' => 'Forged'],
            ]], 'PUT')));
            $this->fail('Foreign fees must be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
            $this->assertTrue($own->fresh()->is_active);
            $this->assertSame('Custom Fee', $foreign->fresh()->name);
        }
    }

    public function test_batch_protects_system_identity_and_applies_system_status_only_to_current_account(): void
    {
        $fee = $this->fee(['is_system' => true, 'user_id' => 'b', 'category' => 'service']);
        $controller = app(FeeTypeController::class);
        $response = $this->mutate(fn () => $controller->updateBatch($this->request(['fees' => [['id' => $fee->id, 'name' => 'Forged']]], 'PUT')));
        $this->assertSame(422, $response->getStatusCode());
        $response = $this->mutate(fn () => $controller->updateBatch($this->request(['fees' => [['id' => $fee->id, 'is_active' => false]]], 'PUT')));
        $this->assertTrue($response->getData(true)['success']);
        $this->assertTrue($fee->fresh()->is_active);
        $this->assertFalse(app(SettingService::class)->isFeeTypeActive($fee, userId: 'a'));
        $this->assertTrue(app(SettingService::class)->isFeeTypeActive($fee, userId: 'b'));
    }

    public function test_delete_returns_json_and_audits_an_unused_fee_and_rejects_a_new_reference(): void
    {
        (require database_path('migrations/2026_06_21_233437_create_audit_logs_table.php'))->up();
        $fee = $this->fee();
        $response = app(FeeTypeController::class)->destroy($this->request([], 'DELETE'), $fee);
        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame('success', $response->getData(true)['session_status']['type']);
        $this->assertNull($fee->fresh());
        $this->assertSame('deleted', DB::table('audit_logs')->value('event'));
        $used = $this->fee();
        DB::table('maintenance')->insert(['fee_type_id' => $used->id]);
        $response = app(FeeTypeController::class)->destroy($this->request([], 'DELETE'), $used);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertNotNull($used->fresh());
    }
}
