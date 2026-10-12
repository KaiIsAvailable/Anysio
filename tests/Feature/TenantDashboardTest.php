<?php

namespace Tests\Feature;

use App\Models\{FeeType, Invoice, InvoiceItem, Lease, Property, User};
use App\Services\{SettingService, TenantDashboardService};
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Blade, DB, Schema};
use Tests\TestCase;

class TenantDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        foreach ([
            'users' => ['name', 'email', 'role'], 'tenants' => ['user_id', 'created_by'],
            'properties' => ['name', 'owner_id', 'created_by'], 'owners' => ['user_id', 'agent_id'],
            'leases' => ['tenant_id', 'leasable_id', 'leasable_type', 'status', 'start_date', 'end_date', 'checked_out_at', 'agreement_ended_at'],
            'invoices' => ['lease_id', 'invoice_no', 'status', 'due_date', 'amount_balance'],
            'invoice_items' => ['invoice_id', 'fee_type_id'], 'fee_types' => ['category'],
        ] as $table => $columns) {
            Schema::create($table, function (Blueprint $t) use ($table, $columns) {
                $t->string('id')->primary(); $t->timestamps();
                foreach ($columns as $column) $t->text($column)->nullable();
                if ($table === 'leases') $t->boolean('is_current')->default(true);
                if ($table === 'invoices') $t->softDeletes();
            });
        }
        Schema::create('settings', function (Blueprint $t) {
            $t->id(); $t->string('user_id'); $t->string('key'); $t->text('value'); $t->boolean('is_active'); $t->timestamps();
        });
        Carbon::setTestNow(Carbon::parse('2026-10-12 00:01:00', 'Asia/Kuala_Lumpur'));
        foreach (['tenant' => 'tenant', 'other' => 'tenant', 'agent' => 'agentAdmin', 'admin' => 'ownerAdmin', 'owner' => 'owner'] as $id => $role) {
            DB::table('users')->insert(['id' => $id, 'name' => $id, 'email' => $id.'@test.test', 'role' => $role]);
        }
        DB::table('tenants')->insert([['id' => 't', 'user_id' => 'tenant'], ['id' => 'o', 'user_id' => 'other']]);
        DB::table('owners')->insert(['id' => 'owner-record', 'user_id' => 'owner', 'agent_id' => 'agent']);
    }

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    private function lease(string $id, array $changes = []): void
    {
        DB::table('properties')->insert(['id' => $id, 'name' => 'Home '.$id, 'owner_id' => 'owner', 'created_by' => 'agent']);
        DB::table('leases')->insert(array_merge(['id' => $id, 'tenant_id' => 't', 'leasable_id' => $id,
            'leasable_type' => Property::class, 'status' => 'New', 'start_date' => '2026-01-01', 'end_date' => '2026-12-11', 'is_current' => true], $changes));
    }

    private function setting(string $manager, string $key, array $value, bool $active = true): void
    {
        DB::table('settings')->insert(['user_id' => $manager, 'key' => $key, 'value' => json_encode($value), 'is_active' => $active]);
    }

    public function test_invoice_scope_balances_boundaries_and_lease_isolation(): void
    {
        $this->lease('active'); $this->lease('second', ['end_date' => '2026-12-12']);
        $this->lease('old', ['is_current' => false]);
        $this->lease('out', ['checked_out_at' => '2026-10-11']);
        $this->lease('ended', ['status' => 'End']);
        $this->lease('future', ['start_date' => '2026-10-13']);
        $this->lease('other', ['tenant_id' => 'o']);
        $this->setting('agent', 'pending_renewal_config', ['number' => 2, 'mode' => 'months', 'days' => 60]);
        foreach ([['five', 'active', 'unpaid', 100, '2026-10-17'], ['six', 'active', 'unpaid', 100, '2026-10-18'],
            ['today', 'active', 'partial', 50, '2026-10-12'], ['late', 'old', 'partial', 50, '2026-10-11'],
            ['paid', 'active', 'paid', 0, '2026-10-11'], ['void', 'active', Invoice::STATUS_VOID, 100, '2026-10-11'],
            ['void-paid', 'old', Invoice::STATUS_VOID, 0, '2026-10-11'], ['deleted', 'active', 'unpaid', 100, '2026-10-11'],
            ['foreign', 'other', 'unpaid', 100, '2026-10-11']] as [$id, $lease, $status, $balance, $due]) {
            DB::table('invoices')->insert(['id' => $id, 'invoice_no' => $id, 'lease_id' => $lease, 'status' => $status, 'amount_balance' => $balance, 'due_date' => $due]);
        }
        DB::table('invoices')->where('id', 'deleted')->update(['deleted_at' => now()]);
        $data = app(TenantDashboardService::class)->data(User::find('tenant'));
        $this->assertSame(5, $data['invoiceTotal']); $this->assertSame(4, $data['unpaidCount']);
        $this->assertSame(['active', 'second'], $data['activeLeases']->pluck('id')->all());
        $messages = implode(' ', array_column($data['banners'], 'message'));
        $this->assertStringContainsString('due in 5 days', $messages);
        $this->assertStringContainsString('due today', $messages);
        $this->assertStringContainsString('overdue by 1 day', $messages);
        $this->assertStringContainsString('60 days remaining', $messages);
        foreach (['Invoice six', 'Invoice paid', 'Invoice void', 'Invoice deleted', 'Invoice foreign', '61 days remaining', '(#active)'] as $excluded) $this->assertStringNotContainsString($excluded, $messages);
        // Reading the dashboard changes no invoice amount or status.
        $this->assertSame(50, Invoice::find('today')->amount_balance);
        $this->assertSame('partial', Invoice::find('today')->status);
        $this->assertTrue(Invoice::find('void')->isVoid());
        $this->assertSame(100, Invoice::find('void')->amount_balance);
        $this->assertNotNull(Invoice::withTrashed()->find('deleted')->deleted_at);
        // The shared invoice index scope still includes void invoices and historical leases.
        $index = app(TenantDashboardService::class)->invoices(User::find('tenant'));
        $this->assertSame(7, (clone $index)->count());
        $this->assertSame(2, (clone $index)->where('status', Invoice::STATUS_VOID)->count());
        $this->assertTrue((clone $index)->whereKey('late')->exists());
    }

    public function test_each_management_account_uses_its_own_settings_and_ambiguity_is_explicit(): void
    {
        $this->lease('agent-home'); $this->lease('admin-home'); $this->lease('ambiguous');
        DB::table('properties')->where('id', 'admin-home')->update(['owner_id' => 'admin', 'created_by' => 'admin']);
        DB::table('properties')->where('id', 'ambiguous')->update(['created_by' => 'admin']);
        $this->setting('agent', 'pending_renewal_config', ['days' => 60]);
        $this->setting('admin', 'pending_renewal_config', ['days' => 59]);
        $this->setting('tenant', 'pending_renewal_config', ['days' => 999]);
        $data = app(TenantDashboardService::class)->data(User::find('tenant'));
        $messages = implode(' ', array_column($data['banners'], 'message'));
        $this->assertStringContainsString('Home agent-home', $messages);
        $this->assertStringNotContainsString('Home admin-home', $messages);
        $this->assertStringNotContainsString('Home ambiguous', $messages);
        $this->assertStringContainsString('ambiguous', $messages);
        $this->assertNull(app(TenantDashboardService::class)->manager(Lease::find('ambiguous')));
    }

    public function test_penalty_switch_category_grace_and_frequency_match_existing_calculation(): void
    {
        $invoice = new Invoice(['invoice_no' => 'I', 'due_date' => '2026-10-12']);
        $item = new InvoiceItem(); $item->setRelation('feeType', new FeeType(['category' => 'rent']));
        $invoice->setRelation('items', collect([$item]));
        $today = Carbon::today(config('app.timezone'));
        foreach ([0, 1, 5] as $grace) {
            foreach ([1, 3] as $frequency) {
                $settings = ['late_penalty_config' => ['is_active' => true, 'value' => [
                    'applicable_categories' => ['rent'], 'grace_period_days' => $grace, 'frequency' => $frequency]]];
                $message = app(TenantDashboardService::class)->invoiceReminder($invoice, $settings, $today)['message'];
                $this->assertStringContainsString($grace ? "starts in {$grace} day" : 'starts today', $message);
                $this->assertStringContainsString($today->copy()->addDays($grace)->format('d M Y'), $message);
                $this->assertStringNotContainsString('interval', $message);
                $this->assertStringNotContainsString('reference date', $message);
                $this->assertStringNotContainsString('This reminder', $message);
                $this->assertStringNotContainsString('charged', $message);
                $settings['late_penalty_config']['is_active'] = false;
                $this->assertStringNotContainsString('penalty', app(TenantDashboardService::class)->invoiceReminder($invoice, $settings, $today)['message']);
                $settings['late_penalty_config']['is_active'] = true;
                $settings['late_penalty_config']['value']['applicable_categories'] = ['deposit'];
                $this->assertStringNotContainsString('penalty', app(TenantDashboardService::class)->invoiceReminder($invoice, $settings, $today)['message']);
            }
        }
        $this->setting('agent', 'late_penalty_config', ['applicable_categories' => ['rent'], 'grace_period_days' => 1, 'frequency' => 3, 'amount' => 500]);
        $calculator = app(SettingService::class);
        $this->assertNull($calculator->calculateLatePenalty('agent', '2026-10-12', 100, '2026-10-12', [$item]));
        $this->assertEquals(5, $calculator->calculateLatePenalty('agent', '2026-10-12', 100, '2026-10-13', [$item])['amount']);
        $this->assertEquals(5, $calculator->calculateLatePenalty('agent', '2026-10-12', 100, '2026-10-15', [$item])['amount']);
        $this->assertEquals(10, $calculator->calculateLatePenalty('agent', '2026-10-12', 100, '2026-10-16', [$item])['amount']);
    }

    public function test_manager_specific_penalty_switch_and_local_end_of_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 23:59:59', 'Asia/Kuala_Lumpur'));
        $this->lease('a', ['end_date' => '2026-10-12']); $this->lease('b');
        DB::table('properties')->where('id', 'b')->update(['owner_id' => 'admin', 'created_by' => 'admin']);
        $config = ['applicable_categories' => ['rent'], 'grace_period_days' => 1];
        $this->setting('agent', 'late_penalty_config', $config, true);
        $this->setting('admin', 'late_penalty_config', $config, false);
        $this->setting('tenant', 'late_penalty_config', $config, true);
        $this->setting('agent', 'pending_renewal_config', ['days' => 60], false);
        DB::table('fee_types')->insert(['id' => 'rent', 'category' => 'rent']);
        foreach (['a', 'b'] as $id) {
            DB::table('invoices')->insert(['id' => $id, 'lease_id' => $id, 'invoice_no' => $id, 'status' => 'partial', 'amount_balance' => 50, 'due_date' => '2026-10-12']);
            DB::table('invoice_items')->insert(['id' => $id, 'invoice_id' => $id, 'fee_type_id' => 'rent']);
        }
        $data = app(TenantDashboardService::class)->data(User::find('tenant'));
        $this->assertCount(2, $data['activeLeases']);
        $a = collect($data['banners'])->first(fn ($b) => str_starts_with($b['message'], 'Invoice a'))['message'];
        $b = collect($data['banners'])->first(fn ($b) => str_starts_with($b['message'], 'Invoice b'))['message'];
        $this->assertStringContainsString('due today', $a);
        $this->assertStringContainsString('in 1 day', $a);
        $this->assertStringNotContainsString('penalty', $b);
        $this->assertStringNotContainsString('Lease Home a', implode(' ', array_column($data['banners'], 'message')));
        Carbon::setTestNow(Carbon::parse('2026-10-13 00:00:00', 'Asia/Kuala_Lumpur'));
        $next = app(TenantDashboardService::class)->data(User::find('tenant'));
        $this->assertSame(['b'], $next['activeLeases']->pluck('id')->all());
        $message = collect($next['banners'])->first(fn ($b) => str_starts_with($b['message'], 'Invoice a'))['message'];
        $this->assertStringContainsString('overdue by 1 day', $message);
        $this->assertStringContainsString('starts today', $message);
        $this->assertStringNotContainsString('in -', $message);
    }

    public function test_dashboard_blade_and_shared_components_compile(): void
    {
        $compiler = app('blade.compiler');
        foreach (['tenantSide.tenants.dashboard', 'tenantSide.tenants.partials.current-leases', 'tenantSide.customerService.index', 'components.notification-banner', 'components.customer-service.create-modal', 'components.table.search'] as $view) {
            $php = $compiler->compileString(file_get_contents(view($view)->getPath()));
            $this->assertNotEmpty(token_get_all($php, TOKEN_PARSE));
        }
    }

    public function test_current_leases_has_empty_single_and_multiple_choices_without_internal_ids_in_labels(): void
    {
        $service = app(TenantDashboardService::class);
        $render = fn () => view('tenantSide.tenants.partials.current-leases', $service->data(User::find('tenant')))->render();
        $empty = $render();
        $this->assertStringContainsString('You have no current leases.', $empty);
        $this->assertStringNotContainsString('href=', $empty);

        $this->lease('internal-one');
        DB::table('properties')->where('id', 'internal-one')->update(['name' => 'Sunrise Apartment']);
        $single = $render();
        $this->assertSame('Current Leases', trim(strip_tags($single)));
        $this->assertStringContainsString(route('tenants.leases.show', 'internal-one'), $single);
        $this->assertStringNotContainsString('<details', $single);

        $this->lease('internal-two');
        DB::table('properties')->where('id', 'internal-two')->update(['name' => 'Garden Apartment']);
        $multiple = $render();
        $this->assertStringContainsString('<details', $multiple);
        $this->assertStringContainsString(route('tenants.leases.show', 'internal-one'), $multiple);
        $this->assertStringContainsString(route('tenants.leases.show', 'internal-two'), $multiple);
        $this->assertSame(2, substr_count($multiple, '<a '));
        preg_match('/<summary[^>]*>(.*?)<\/summary>/s', $multiple, $summary);
        $this->assertSame('Current Leases', trim(strip_tags($summary[1])));
        $this->assertStringContainsString('Sunrise Apartment', strip_tags($multiple));
        $this->assertStringContainsString('Garden Apartment', strip_tags($multiple));
        $this->assertStringNotContainsString('internal-', strip_tags($multiple));
    }

    public function test_notification_banner_preserves_actions_and_existing_admin_setup_slot(): void
    {
        $message = 'Invoice '.str_repeat('LONG-INVOICE-', 30).' is overdue by 12 days.';
        $html = Blade::render('<x-notification-banner type="warning" :message="$message" actionLabel="View Invoices" actionUrl="/invoices" secondaryActionLabel="View Lease" secondaryActionUrl="/lease" />', compact('message'));
        $this->assertStringContainsString($message, $html);
        $this->assertStringContainsString('href="/invoices"', $html);
        $this->assertStringContainsString('href="/lease"', $html);
        $this->assertStringNotContainsString('truncate', $html);
        $this->assertStringNotContainsString('overflow-hidden', $html);
        $this->assertSame('', trim(Blade::render('<x-notification-banner :show="false" message="Hidden" />')));

        preg_match('/<x-notification-banner\b.*?<\/x-notification-banner>/s', file_get_contents(resource_path('views/dashboard.blade.php')), $matches);
        $checks = array_fill_keys(['tenant', 'owner', 'property', 'asset', 'template'], false);
        $admin = Blade::render($matches[0], compact('checks'));
        $this->assertStringContainsString('Getting Started:', $admin);
        $this->assertSame(5, substr_count($admin, '<li>'));
        $this->assertStringContainsString(route('admin.tenants.create'), $admin);
        $this->assertStringNotContainsString('View Invoices', $admin);
    }

    public function test_overdue_penalty_message_gives_start_date_without_claiming_collection(): void
    {
        $invoice = new Invoice(['invoice_no' => 'INV-EXAMPLE', 'due_date' => '2026-10-10']);
        $item = new InvoiceItem(); $item->setRelation('feeType', new FeeType(['category' => 'rent']));
        $invoice->setRelation('items', collect([$item]));
        $settings = ['late_penalty_config' => ['is_active' => true, 'value' => ['grace_period_days' => 0, 'frequency' => 1, 'applicable_categories' => ['rent']]]];
        $message = app(TenantDashboardService::class)->invoiceReminder($invoice, $settings, Carbon::today())['message'];
        $this->assertSame('Invoice INV-EXAMPLE is overdue by 2 days. Late payment penalty applies from 10 Oct 2026.', $message);
        $this->assertStringNotContainsString('charged', $message);
    }

    public function test_foreign_lease_show_retains_backend_ownership_check(): void
    {
        $this->lease('foreign', ['tenant_id' => 'o']);
        $this->actingAs(User::find('tenant'));
        try {
            app(\App\Http\Controllers\TenantsController::class)->leaseShow(Lease::find('foreign'));
            $this->fail('A foreign lease must be denied.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
