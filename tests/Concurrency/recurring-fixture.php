<?php
// Isolated concurrency fixture. Called only after runner verifies its disposable database.
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
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
            $t->integer('sort_order')->default(0);
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
        // Freeze only the application's clock to the user-session date, never the OS clock.
        $today = Carbon::parse('2026-11-03 09:00:00', 'Asia/Kuala_Lumpur');
        \Carbon\Carbon::setTestNow($today);
        DB::table('settings')->insert(['user_id' => 'manager', 'key' => 'due_date_config', 'value' => json_encode(['recurring_type' => 'both', 'recurring_feq' => 'after', 'recurring_feq_days' => 0, 'days' => 7])]);
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
