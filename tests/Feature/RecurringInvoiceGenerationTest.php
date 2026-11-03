<?php

namespace Tests\Feature;

use App\Models\{Invoice, Lease, User};
use App\Services\{InvoiceService, LeaseManagementResolver, RecurringInvoicePeriod, RecurringInvoiceService};
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class RecurringInvoiceGenerationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        // Explicit, disposable fixtures: no migrations, seeders, or production connections.
        Schema::create('users', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('role'); $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->id(); $t->string('user_id'); $t->string('key'); $t->text('value');
            $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('leases', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('tenant_id')->default('tenant');
            $t->date('start_date'); $t->date('end_date')->nullable();
            $t->string('status')->default('New'); $t->boolean('is_current')->default(true);
            foreach (['cancelled_at', 'checked_out_at', 'agreement_ended_at'] as $field) $t->date($field)->nullable();
            $t->timestamps();
        });
        Schema::create('fee_types', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('user_id'); $t->string('name'); $t->string('category')->default('rent');
            $t->boolean('is_active')->default(true); $t->boolean('is_system')->default(false); $t->timestamps();
        });
        Schema::create('lease_charges', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('lease_id'); $t->string('fee_type_id');
            $t->string('description')->default('Rent'); $t->integer('amount')->default(12000);
            $t->string('charge_type')->default('recurring'); $t->string('frequency')->default('monthly');
            $t->boolean('is_active')->default(true); $t->date('next_billing_date')->nullable(); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->string('id')->primary();
            foreach (['lease_id', 'user_id', 'billable_type', 'billable_id', 'document_template_id', 'invoice_no', 'type', 'remarks'] as $field) $t->string($field)->nullable();
            $t->date('period'); $t->date('due_date')->nullable(); $t->string('status')->default('unpaid');
            foreach (['total_amount', 'amount_paid', 'amount_balance'] as $field) $t->integer($field)->default(0);
            $t->timestamps(); $t->softDeletes(); $t->unique(['user_id', 'invoice_no']);
        });
        Schema::create('invoice_items', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('invoice_id'); $t->string('lease_charge_id')->nullable();
            $t->string('fee_type_id'); $t->string('description')->default('Rent'); $t->integer('amount')->default(12000); $t->timestamps();
        });
        Schema::create('document_templates', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('user_id')->nullable(); $t->string('category'); $t->string('status'); $t->softDeletes();
        });
        Schema::create('document_sequences', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('user_id'); $t->string('category'); $t->text('sequence'); $t->timestamps();
        });
        DB::table('users')->insert(['id' => 'manager', 'role' => 'ownerAdmin']);
        DB::table('leases')->insert(['id' => 'lease', 'start_date' => '2026-01-15', 'end_date' => '2027-12-31']);
        DB::table('fee_types')->insert(['id' => 'rent', 'user_id' => 'manager', 'name' => 'Monthly Rental']);
        DB::table('lease_charges')->insert(['id' => 'charge', 'lease_id' => 'lease', 'fee_type_id' => 'rent', 'next_billing_date' => '2026-02-15']);
        $this->settings();
        $this->travelTo(Carbon::parse('2026-11-15 09:00:00', config('app.timezone')));
    }

    private function settings(string $mode = 'both', string $direction = 'after', int $offset = 0): void
    {
        DB::table('settings')->updateOrInsert(['user_id' => 'manager', 'key' => 'due_date_config'], [
            'value' => json_encode(['recurring_type' => $mode, 'recurring_feq' => $direction, 'recurring_feq_days' => $offset, 'days' => 7]),
        ]);
    }

    private function generate(bool $auto = true, bool $dryRun = false): int
    {
        return Model::withoutEvents(fn () => app(RecurringInvoiceService::class)->generate(
            Lease::findOrFail('lease'), User::findOrFail('manager'), $auto, $dryRun
        ));
    }

    public function test_end_date_is_inclusive_and_later_scheduled_date_is_skipped(): void
    {
        $this->settings('both', 'after', 3);
        DB::table('leases')->update(['end_date' => '2026-11-18']);
        $this->travelTo(Carbon::parse('2026-11-18 23:59:59'));
        $this->assertSame(1, $this->generate(true, true));
        $this->assertSame(1, $this->generate());
        $this->assertSame('2026-11-15', Invoice::first()->period->toDateString());
        $this->assertSame(0, $this->generate());
        DB::table('invoice_items')->delete(); DB::table('invoices')->delete();
        DB::table('leases')->update(['end_date' => '2026-11-17']);
        $this->assertSame(0, $this->generate(true, true));
        $this->assertSame(0, $this->generate());
        $this->assertSame(0, Invoice::count());
    }

    public function test_no_backfill_and_dry_run_has_no_database_writes(): void
    {
        $before = DB::table('lease_charges')->first();
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        $this->assertSame(1, $this->generate(true, true));
        foreach ($queries as $sql) $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|replace)/i', $sql);
        $this->assertEquals($before, DB::table('lease_charges')->first());
        $this->assertSame(0, DB::table('document_sequences')->count());
        $this->assertSame(0, Invoice::count());
        $this->travelTo(Carbon::parse('2026-11-16'));
        $this->assertSame(0, $this->generate());
        $this->travelTo(Carbon::parse('2026-12-15'));
        $this->assertSame(1, $this->generate());
        $this->assertSame(['2026-12-15'], Invoice::all()->map(fn ($i) => $i->period->toDateString())->all());
    }

    public function test_manual_and_automatic_share_duplicate_guard_in_both_orders(): void
    {
        $this->actingAs(User::findOrFail('manager'));
        $manual = fn () => Model::withoutEvents(fn () => app(InvoiceService::class)->generateRecurringInvoices(Lease::findOrFail('lease')));
        $this->assertSame(1, $manual());
        $this->assertSame(0, $this->generate());
        $this->travelTo(Carbon::parse('2026-12-15'));
        $this->assertSame(1, $this->generate());
        $this->assertSame(0, $manual());
        $this->assertSame(2, Invoice::count());
        $this->assertSame(2, DB::table('invoice_items')->where('lease_charge_id', 'charge')->count());
    }

    public function test_void_and_deleted_legacy_invoice_still_prevent_reissue(): void
    {
        DB::table('invoices')->insert(['id' => 'old', 'lease_id' => 'lease', 'period' => '2026-11-01', 'status' => 'void', 'deleted_at' => now()]);
        DB::table('invoice_items')->insert(['id' => 'old-item', 'invoice_id' => 'old', 'fee_type_id' => 'rent']);
        $this->assertSame(0, $this->generate());
        $this->assertSame(0, $this->generate(false));
    }

    public function test_daily_periods_are_not_collapsed_to_month_start(): void
    {
        DB::table('lease_charges')->update(['frequency' => 'daily']);
        $this->assertSame(1, $this->generate());
        $this->travelTo(Carbon::parse('2026-11-16'));
        $this->assertSame(1, $this->generate());
        $this->assertSame(['2026-11-15', '2026-11-16'], Invoice::orderBy('period')->get()->map(fn ($i) => $i->period->toDateString())->all());
    }

    public function test_before_offset_across_year_and_after_offset_across_month(): void
    {
        $this->settings('auto', 'before', 31);
        $this->travelTo(Carbon::parse('2026-12-15'));
        $this->assertSame(1, $this->generate());
        $this->assertSame('2027-01-15', Invoice::first()->period->toDateString());
        $this->settings('auto', 'after', 31);
        $this->travelTo(Carbon::parse('2027-03-18'));
        $this->assertSame(1, $this->generate());
        $this->assertTrue(Invoice::whereDate('period', '2027-02-15')->exists());
    }

    public function test_month_end_and_leap_year_keep_original_anchor(): void
    {
        $calculator = app(RecurringInvoicePeriod::class);
        $anchor = Carbon::parse('2024-01-31');
        foreach (['2024-02-29', '2024-03-31', '2025-02-28'] as $date) {
            $this->assertSame($date, $calculator->containing($anchor, Carbon::parse($date), 'monthly')->toDateString());
        }
        $anchor = Carbon::parse('2024-02-29');
        $this->assertSame('2025-02-28', $calculator->containing($anchor, Carbon::parse('2025-03-01'), 'yearly')->toDateString());
        $this->assertSame('2028-02-29', $calculator->next($anchor, Carbon::parse('2027-02-28'), 'yearly')->toDateString());
        DB::table('leases')->update(['start_date' => '2024-02-29', 'end_date' => '2028-12-31']);
        DB::table('lease_charges')->update(['frequency' => 'yearly']);
        $this->travelTo(Carbon::parse('2027-02-28'));
        $this->assertSame(1, $this->generate());
        $this->travelTo(Carbon::parse('2027-03-01'));
        $this->assertSame(0, $this->generate());
    }

    public function test_modes_invalid_leases_and_inactive_fees_are_skipped(): void
    {
        $this->settings('manual');
        $this->assertSame(0, $this->generate());
        $this->settings('auto');
        $this->assertSame(0, $this->generate(false));
        $this->settings();
        foreach (['status' => 'End', 'is_current' => false, 'cancelled_at' => '2026-11-01', 'checked_out_at' => '2026-11-01', 'agreement_ended_at' => '2026-11-01'] as $field => $value) {
            $old = DB::table('leases')->value($field);
            DB::table('leases')->update([$field => $value]);
            $this->assertSame(0, $this->generate());
            DB::table('leases')->update([$field => $old]);
        }
        DB::table('fee_types')->update(['is_active' => false]);
        $this->assertSame(0, $this->generate());
    }

    public function test_command_dry_run_uses_resolved_account_without_authentication(): void
    {
        $resolver = \Mockery::mock(LeaseManagementResolver::class);
        $resolver->shouldReceive('resolve')->once()->andReturn(User::findOrFail('manager'));
        $this->app->instance(LeaseManagementResolver::class, $resolver);
        $this->assertNull(auth()->user());
        $this->artisan('invoices:generate-recurring', ['--dry-run' => true, '--lease' => 'lease'])
            ->expectsOutput('Dry-run eligible: 1; unresolved: 0; errors: 0.')->assertSuccessful();
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, DB::table('document_sequences')->count());
    }

    public function test_failure_rolls_back_invoice_sequence_and_charge_updates(): void
    {
        $before = DB::table('lease_charges')->first();
        DB::statement("CREATE TRIGGER reject_item BEFORE INSERT ON invoice_items BEGIN SELECT RAISE(ABORT, 'isolated failure'); END");
        try {
            $this->generate();
            $this->fail('Expected injected failure.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('isolated failure', $e->getMessage());
        }
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, DB::table('document_sequences')->count());
        $this->assertEquals($before, DB::table('lease_charges')->first());
    }

    public function test_status_scheduler_keeps_end_date_eligible_until_next_day(): void
    {
        Schema::table('leases', fn (Blueprint $t) => $t->boolean('is_pending_renewal')->default(false));
        Schema::create('tenants', function (Blueprint $t) { $t->string('id'); $t->string('created_by'); });
        Schema::create('user_management', function (Blueprint $t) { $t->string('id'); $t->string('user_id'); $t->softDeletes(); });
        DB::table('tenants')->insert(['id' => 'tenant', 'created_by' => 'manager']);
        DB::table('user_management')->insert(['id' => 'management', 'user_id' => 'manager']);
        DB::table('leases')->update(['end_date' => '2026-11-15']);
        $this->artisan('leases:update-statuses')->assertSuccessful();
        $this->assertSame('New', Lease::first()->status);
        $this->assertSame(1, $this->generate(true, true));
        $this->travelTo(Carbon::parse('2026-11-16 00:00:00'));
        $this->artisan('leases:update-statuses')->assertSuccessful();
        $this->assertSame('End', Lease::first()->status);
    }

    public function test_schedule_is_daily_in_application_timezone_and_prevents_overlap(): void
    {
        $this->artisan('list')->assertSuccessful();
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());
        $event = $events->first(fn ($event) => str_contains($event->command ?? '', 'invoices:generate-recurring'));
        $this->assertNotNull($event);
        $this->assertSame('10 0 * * *', $event->expression);
        $this->assertSame(config('app.timezone'), $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_dry_run_bootstrap_skips_permission_database_mutations(): void
    {
        Schema::create('permissions', function (Blueprint $t) { $t->id(); $t->string('name'); });
        DB::table('permissions')->insert(['name' => 'existing-permission']);
        $argv = $_SERVER['argv'] ?? [];
        try {
            $_SERVER['argv'] = ['artisan', 'invoices:generate-recurring', '--dry-run'];
            (new \App\Providers\AppServiceProvider($this->app))->boot();
        } finally {
            $_SERVER['argv'] = $argv;
        }
        $this->assertSame(['existing-permission'], DB::table('permissions')->pluck('name')->all());
    }

    public function test_report_dry_run_date_and_mode_matrix(): void
    {
        // Separate preview-only coverage; previously passed write-path tests need not rerun.
        $cases = [
            ['manual', 'monthly', '2026-01-15', 'after', 0, '2026-11-15', '2027-12-31', 0],
            ['auto', 'monthly', '2026-01-15', 'after', 0, '2026-11-15', '2027-12-31', 1],
            ['both', 'monthly', '2026-01-15', 'after', 0, '2026-11-15', '2027-12-31', 1],
            ['auto', 'monthly', '2026-01-15', 'after', 0, '2026-11-14', '2027-12-31', 0],
            ['both', 'monthly', '2026-01-15', 'after', 0, '2026-11-16', '2027-12-31', 0],
            ['both', 'monthly', '2026-01-15', 'after', 0, '2026-12-15', '2027-12-31', 1],
            ['auto', 'daily', '2026-01-15', 'before', 1, '2026-11-15', '2027-12-31', 1],
            ['auto', 'monthly', '2026-01-15', 'after', 3, '2026-11-18', '2027-12-31', 1],
            ['auto', 'yearly', '2024-02-29', 'before', 3, '2027-02-25', '2027-12-31', 1],
            ['auto', 'yearly', '2024-02-29', 'after', 3, '2027-03-03', '2027-12-31', 1],
            ['both', 'monthly', '2026-01-15', 'after', 3, '2026-11-18', '2026-11-18', 1],
            ['both', 'monthly', '2026-01-15', 'after', 3, '2026-11-18', '2026-11-17', 0],
        ];
        foreach ($cases as [$mode, $frequency, $start, $direction, $offset, $today, $end, $expected]) {
            $this->settings($mode, $direction, $offset);
            DB::table('leases')->update(['start_date' => $start, 'end_date' => $end]);
            DB::table('lease_charges')->update(['frequency' => $frequency]);
            $this->travelTo(Carbon::parse($today));
            $this->assertSame($expected, $this->generate(true, true), implode(' / ', [$mode, $frequency, $direction, $offset, $today, $end]));
        }
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, DB::table('document_sequences')->count());
        $this->assertSame('2026-02-15', DB::table('lease_charges')->value('next_billing_date'));

        // Represent invoices already issued by either route using direct fixture inserts.
        DB::table('leases')->update(['end_date' => '2027-12-31']);
        foreach (['manual', 'automatic'] as $origin) {
            DB::table('invoices')->insert(['id' => $origin, 'lease_id' => 'lease', 'period' => '2026-11-15', 'remarks' => $origin]);
            DB::table('invoice_items')->insert(['id' => $origin, 'invoice_id' => $origin, 'lease_charge_id' => 'charge', 'fee_type_id' => 'rent']);
            $this->assertSame(0, $this->generate(true, true), $origin);
            DB::table('invoice_items')->delete(); DB::table('invoices')->delete();
        }
    }

    public function test_report_dry_run_resolves_multiple_accounts_and_keeps_settings_and_leases_separate(): void
    {
        Schema::table('leases', function (Blueprint $t) { $t->string('leasable_type')->nullable(); $t->string('leasable_id')->nullable(); });
        Schema::create('properties', function (Blueprint $t) { $t->string('id'); $t->string('created_by'); $t->string('owner_id'); });
        Schema::create('owners', function (Blueprint $t) { $t->string('user_id'); $t->string('agent_id')->nullable(); });
        DB::table('users')->insert(['id' => 'manager-b', 'role' => 'ownerAdmin']);
        foreach (['manager' => 'asset-a', 'manager-b' => 'asset-b'] as $manager => $asset) {
            DB::table('properties')->insert(['id' => $asset, 'created_by' => $manager, 'owner_id' => $manager]);
        }
        DB::table('leases')->where('id', 'lease')->update(['leasable_type' => \App\Models\Property::class, 'leasable_id' => 'asset-a']);
        DB::table('leases')->insert(['id' => 'lease-b', 'start_date' => '2026-01-15', 'end_date' => '2027-12-31', 'leasable_type' => \App\Models\Property::class, 'leasable_id' => 'asset-b']);
        DB::table('fee_types')->insert(['id' => 'rent-b', 'user_id' => 'manager-b', 'name' => 'Monthly Rental']);
        DB::table('lease_charges')->insert(['id' => 'charge-b', 'lease_id' => 'lease-b', 'fee_type_id' => 'rent-b']);
        $this->settings('auto', 'before', 2);
        DB::table('settings')->insert(['user_id' => 'manager-b', 'key' => 'due_date_config', 'value' => json_encode([
            'recurring_type' => 'both', 'recurring_feq' => 'after', 'recurring_feq_days' => 3, 'days' => 14,
        ])]);
        // Real resolver, no mock and no authenticated user.
        $resolver = app(LeaseManagementResolver::class);
        $this->assertSame('manager', $resolver->resolve(Lease::findOrFail('lease'))->id);
        $this->assertSame('manager-b', $resolver->resolve(Lease::findOrFail('lease-b'))->id);
        foreach (['2026-11-13' => 'lease', '2026-11-18' => 'lease-b'] as $today => $eligible) {
            $this->travelTo(Carbon::parse($today));
            foreach (['lease', 'lease-b'] as $id) {
                $lease = Lease::findOrFail($id);
                $this->assertSame($id === $eligible ? 1 : 0, app(RecurringInvoiceService::class)->generate($lease, $resolver->resolve($lease), true, true));
            }
            $this->artisan('invoices:generate-recurring', ['--dry-run' => true])
                ->expectsOutput("Lease {$eligible}: 1 invoice(s).")
                ->expectsOutput('Dry-run eligible: 1; unresolved: 0; errors: 0.')->assertSuccessful();
        }
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, DB::table('invoice_items')->count());
        $this->assertSame(0, DB::table('document_sequences')->count());
    }

    private function prepareLiveCommandFixture(): void
    {
        // Freeze only the application's clock to the user-session date, never the OS clock.
        $today = Carbon::parse('2026-11-03 09:00:00', 'Asia/Kuala_Lumpur');
        $this->travelTo($today);
        $this->settings('both', 'after', 0);
        Schema::table('users', fn (Blueprint $t) => $t->string('name')->nullable());
        Schema::table('leases', function (Blueprint $t) { $t->string('leasable_type')->nullable(); $t->string('leasable_id')->nullable(); });
        Schema::create('properties', function (Blueprint $t) { $t->string('id'); $t->string('created_by'); $t->string('owner_id'); });
        Schema::create('owners', function (Blueprint $t) { $t->string('user_id'); $t->string('agent_id')->nullable(); });
        Schema::create('tenants', function (Blueprint $t) { $t->string('id')->primary(); $t->string('user_id'); });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->string('id')->primary();
            foreach (['user_id', 'event', 'auditable_type', 'auditable_id', 'ip_address', 'user_agent'] as $field) $t->string($field)->nullable();
            $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->timestamps();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('created_by')->nullable(); $t->string('type');
            $t->string('title'); $t->text('data'); $t->timestamps();
        });
        Schema::create('notification_recipients', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('notification_id'); $t->string('user_id'); $t->timestamps();
        });
        DB::table('users')->where('id', 'manager')->update(['name' => 'Test Manager']);
        DB::table('users')->insert([
            ['id' => 'test-owner', 'role' => 'owner', 'name' => 'Test Payee'],
            ['id' => 'test-tenant-user', 'role' => 'tenant', 'name' => 'Test Tenant'],
        ]);
        DB::table('tenants')->insert(['id' => 'tenant', 'user_id' => 'test-tenant-user']);
        DB::table('properties')->insert(['id' => 'test-property', 'created_by' => 'manager', 'owner_id' => 'test-owner']);
        DB::table('owners')->insert(['user_id' => 'test-owner', 'agent_id' => 'manager']);
        DB::table('document_templates')->insert(['id' => 'test-template', 'user_id' => 'manager', 'category' => 'invoice', 'status' => 'active']);
        DB::table('leases')->where('id', 'lease')->update([
            'start_date' => '2026-10-03', 'end_date' => '2027-10-03',
            'leasable_type' => \App\Models\Property::class, 'leasable_id' => 'test-property',
        ]);
        DB::table('lease_charges')->where('id', 'charge')->update(['amount' => 120000, 'description' => 'Monthly rent', 'next_billing_date' => '2026-11-03']);
        DB::table('fee_types')->insert(['id' => 'fee-extra', 'user_id' => 'manager', 'name' => 'Monthly Service']);
        DB::table('lease_charges')->insert(['id' => 'charge-extra', 'lease_id' => 'lease', 'fee_type_id' => 'fee-extra', 'amount' => 5000, 'description' => 'Monthly service', 'next_billing_date' => '2026-11-03']);
        // A second eligible lease proves --lease does not run the entire candidate set.
        DB::table('leases')->insert(['id' => 'control-lease', 'tenant_id' => 'tenant', 'start_date' => '2026-10-03', 'end_date' => '2027-10-03', 'leasable_type' => \App\Models\Property::class, 'leasable_id' => 'test-property']);
        DB::table('lease_charges')->insert(['id' => 'control-charge', 'lease_id' => 'control-lease', 'fee_type_id' => 'rent', 'next_billing_date' => '2026-11-03']);
    }

    public function test_scoped_command_real_write_and_both_mode_with_live_model_events(): void
    {
        $this->prepareLiveCommandFixture();
        $this->assertNull(auth()->user());
        $this->assertSame('manager', app(LeaseManagementResolver::class)->resolve(Lease::findOrFail('lease'))->id);

        $this->artisan('invoices:generate-recurring', ['--lease' => 'control-lease', '--dry-run' => true])
            ->expectsOutput('Dry-run eligible: 1; unresolved: 0; errors: 0.')->assertSuccessful();
        $this->artisan('invoices:generate-recurring', ['--lease' => 'lease', '--dry-run' => true])
            ->expectsOutput('Dry-run eligible: 1; unresolved: 0; errors: 0.')->assertSuccessful();
        foreach (['invoices', 'invoice_items', 'document_sequences', 'notifications', 'audit_logs'] as $table) $this->assertSame(0, DB::table($table)->count());

        // Real command and real Eloquent writes, including observers/audits; no withoutEvents.
        $this->artisan('invoices:generate-recurring', ['--lease' => 'lease'])
            ->expectsOutput('Generated: 1; unresolved: 0; errors: 0.')->assertSuccessful();
        $invoice = Invoice::sole();
        $this->assertSame('lease', $invoice->lease_id);
        $this->assertSame('2026-11-03', $invoice->period->toDateString());
        $this->assertSame('2026-11-10', $invoice->due_date->toDateString());
        $this->assertSame('INV00001', $invoice->invoice_no);
        $this->assertSame('test-template', $invoice->document_template_id);
        $this->assertSame('manager', $invoice->user_id);
        $this->assertSame(\App\Models\Tenants::class, $invoice->billable_type);
        $this->assertSame('tenant', $invoice->billable_id);
        $this->assertSame('Test Tenant', $invoice->billable->user->name);
        $this->assertSame('test-owner', $invoice->lease->leasable->owner->id);
        $this->assertSame('Test Payee', $invoice->lease->leasable->owner->name);
        $this->assertSame(125000, $invoice->total_amount);
        $this->assertSame(125000, $invoice->amount_balance);
        $this->assertSame(0, $invoice->amount_paid);
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame([
            ['charge', 'rent', 'Monthly rent', 120000],
            ['charge-extra', 'fee-extra', 'Monthly service', 5000],
        ], $invoice->items()->orderBy('lease_charge_id')->get()->map(fn ($i) => [$i->lease_charge_id, $i->fee_type_id, $i->description, (int) $i->amount])->all());
        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertSame('manager', DB::table('notification_recipients')->sole()->user_id);
        $this->assertTrue(DB::table('audit_logs')->where('auditable_type', Invoice::class)->where('auditable_id', $invoice->id)->exists());
        $this->assertSame(0, Invoice::where('lease_id', 'control-lease')->count());
        $this->assertSame('2026-11-03', DB::table('lease_charges')->where('id', 'control-charge')->value('next_billing_date'));
        $this->assertSame('2026-12-03', \App\Models\LeaseCharge::findOrFail('charge')->next_billing_date->toDateString());

        $counts = collect(['invoices', 'invoice_items', 'notifications', 'audit_logs'])->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();
        $sequence = DB::table('document_sequences')->sole()->sequence;
        $this->artisan('invoices:generate-recurring', ['--lease' => 'lease'])
            ->expectsOutput('Generated: 0; unresolved: 0; errors: 0.')->assertSuccessful();
        $this->actingAs(User::findOrFail('manager'));
        $this->assertSame(0, app(InvoiceService::class)->generateRecurringInvoices(Lease::findOrFail('lease')));
        foreach ($counts as $table => $count) $this->assertSame($count, DB::table($table)->count());
        $this->assertSame($sequence, DB::table('document_sequences')->sole()->sequence);

        // Also verify the reverse order using the independent control lease in Both mode.
        $this->assertSame(1, app(InvoiceService::class)->generateRecurringInvoices(Lease::findOrFail('control-lease')));
        auth()->forgetGuards();
        $this->artisan('invoices:generate-recurring', ['--lease' => 'control-lease'])
            ->expectsOutput('Generated: 0; unresolved: 0; errors: 0.')->assertSuccessful();
        $this->assertSame(1, Invoice::where('lease_id', 'control-lease')->count());
        $this->assertSame(1, Invoice::where('lease_id', 'lease')->count());
    }

    public function test_status_command_then_generation_command_on_end_day_and_following_day(): void
    {
        $this->prepareLiveCommandFixture();
        Schema::table('leases', fn (Blueprint $t) => $t->boolean('is_pending_renewal')->default(false));
        Schema::table('tenants', fn (Blueprint $t) => $t->string('created_by')->nullable());
        Schema::create('user_management', function (Blueprint $t) { $t->string('id'); $t->string('user_id'); $t->softDeletes(); });
        DB::table('tenants')->update(['created_by' => 'manager']);
        DB::table('user_management')->insert(['id' => 'management', 'user_id' => 'manager']);
        DB::table('leases')->update(['end_date' => '2026-11-03']);
        // Keep an unbilled control lease: next-day skipping must not rely on duplicates.
        DB::table('lease_charges')->where('id', 'control-charge')->update(['frequency' => 'daily']);
        $this->travelTo(Carbon::parse('2026-11-03 00:00:00', 'Asia/Kuala_Lumpur'));
        $this->artisan('leases:update-statuses')->assertSuccessful();
        $this->assertSame('New', Lease::findOrFail('lease')->status);
        $this->assertSame('New', Lease::findOrFail('control-lease')->status);
        $this->travelTo(Carbon::parse('2026-11-03 00:10:00', 'Asia/Kuala_Lumpur'));
        $this->artisan('invoices:generate-recurring', ['--lease' => 'lease'])
            ->expectsOutput('Generated: 1; unresolved: 0; errors: 0.')->assertSuccessful();
        $this->assertSame('2026-11-03', Invoice::sole()->period->toDateString());
        $this->assertSame(1, Invoice::count());
        $this->assertSame(2, DB::table('invoice_items')->count());
        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertSame(1, DB::table('notification_recipients')->count());

        $this->travelTo(Carbon::parse('2026-11-04 00:00:00', 'Asia/Kuala_Lumpur'));
        $this->artisan('leases:update-statuses')->assertSuccessful();
        $this->assertSame('End', Lease::findOrFail('lease')->status);
        $this->assertSame('End', Lease::findOrFail('control-lease')->status);
        $counts = collect(['invoices', 'invoice_items', 'notifications', 'notification_recipients', 'audit_logs'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
        $sequence = DB::table('document_sequences')->sole()->sequence;
        $this->travelTo(Carbon::parse('2026-11-04 00:10:00', 'Asia/Kuala_Lumpur'));
        foreach (['lease', 'control-lease'] as $id) {
            $this->artisan('invoices:generate-recurring', ['--lease' => $id])
                ->expectsOutput('Generated: 0; unresolved: 0; errors: 0.')->assertSuccessful();
        }
        foreach ($counts as $table => $count) $this->assertSame($count, DB::table($table)->count());
        $this->assertSame($sequence, DB::table('document_sequences')->sole()->sequence);
        $this->assertSame(0, Invoice::where('lease_id', 'control-lease')->count());
    }

    public function test_scoped_command_empty_or_zero_id_never_becomes_unrestricted(): void
    {
        $resolver = \Mockery::mock(LeaseManagementResolver::class);
        $resolver->shouldNotReceive('resolve');
        $this->app->instance(LeaseManagementResolver::class, $resolver);
        $this->artisan('invoices:generate-recurring', ['--lease' => ''])
            ->expectsOutput('--lease must contain a lease ID; refusing an unrestricted run.')->assertFailed();
        $this->artisan('invoices:generate-recurring', ['--lease' => '0'])
            ->expectsOutput('Generated: 0; unresolved: 0; errors: 0.')->assertSuccessful();
        $this->assertSame(0, Invoice::count());
    }

    public function test_unresolved_account_is_reported_and_skipped(): void
    {
        $resolver = \Mockery::mock(LeaseManagementResolver::class);
        $resolver->shouldReceive('resolve')->once()->andReturnNull();
        $this->app->instance(LeaseManagementResolver::class, $resolver);
        $this->artisan('invoices:generate-recurring', ['--dry-run' => true])
            ->expectsOutput('Dry-run eligible: 0; unresolved: 1; errors: 0.')->assertSuccessful();
        $this->assertSame(0, Invoice::count());
    }
}
