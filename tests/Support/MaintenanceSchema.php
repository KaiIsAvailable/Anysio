<?php
namespace Tests\Support;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
final class MaintenanceSchema
{
    public static function create(): void
    {
        $database = DB::connection()->getDatabaseName();
        if (!(DB::getDriverName() === 'sqlite' && $database === ':memory:')
            && !(DB::getDriverName() === 'mysql' && preg_match('/^anysio_maintenance_test_[a-f0-9]{12}$/D', $database))) {
            throw new \RuntimeException('Refusing schema creation outside an isolated maintenance test database.');
        }
        foreach (['users', 'owners', 'tenants', 'properties', 'units', 'rooms', 'leases', 'tickets', 'ticket_msgs', 'audit_logs'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->string('id', 26)->primary();
                $t->timestamps();
                $columns = match ($table) {
                    'users' => ['name', 'email', 'password', 'role', 'email_verified_at'],
                    'tenants' => ['user_id', 'created_by'],
                    'owners' => ['user_id', 'agent_id'],
                    'properties' => ['name', 'owner_id', 'created_by'],
                    'units' => ['unit_no', 'owner_id', 'property_id', 'created_by'],
                    'rooms' => ['room_no', 'owner_id', 'unit_id'],
                    'leases' => ['tenant_id', 'leasable_type', 'leasable_id', 'status', 'start_date', 'end_date', 'checked_out_at', 'agreement_ended_at'],
                    'tickets' => ['sender_id', 'receive_id', 'lease_id', 'category', 'subject', 'status'],
                    'ticket_msgs' => ['ticket_id', 'sender_type', 'message', 'status', 'read_at', 'read_by'],
                    'audit_logs' => ['user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent'],
                };
                foreach ($columns as $column) $t->text($column)->nullable();
                if ($table === 'leases') $t->boolean('is_current')->default(true);
                if (in_array($table, ['properties', 'units', 'rooms'], true)) $t->string('status')->default('Occupied');
                if ($table === 'users') { $t->string('status')->default('active'); $t->boolean('is_agree')->default(false); }
            });
        }
        Schema::create('staff', function (Blueprint $t) { $t->string('id')->primary(); $t->string('user_id'); $t->string('user_mgnt_id')->nullable(); $t->string('is_active')->default('1'); $t->string('role')->nullable(); $t->timestamps(); });
        Schema::create('permissions', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps(); });
        Schema::create('roles', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->string('team_id')->nullable(); });
        Schema::create('role_has_permissions', function (Blueprint $t) { $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id'); });
        Schema::create('model_has_roles', function (Blueprint $t) { $t->unsignedBigInteger('role_id'); $t->string('model_type'); $t->string('model_id'); $t->string('team_id'); });
        Schema::create('model_has_permissions', function (Blueprint $t) { $t->unsignedBigInteger('permission_id'); $t->string('model_type'); $t->string('model_id'); $t->string('team_id'); });
        Schema::create('user_management', function (Blueprint $t) { $t->string('id'); $t->string('user_id'); $t->softDeletes(); });
        Schema::create('invoices', function (Blueprint $t) { $t->string('id')->primary(); $t->string('user_id'); $t->string('context'); $t->string('status'); $t->string('lease_id')->nullable(); $t->string('invoice_no')->nullable(); $t->bigInteger('amount_balance')->default(0); $t->bigInteger('total_amount')->default(0); $t->timestamps(); $t->softDeletes(); });
        Schema::create('invoice_items', function (Blueprint $t) { $t->string('id')->primary(); $t->string('invoice_id'); });
        Schema::create('refunds', function (Blueprint $t) {
            $t->ulid('id')->primary(); $t->string('refund_number')->unique(); $t->string('lease_id'); $t->string('tenant_id');
            $t->string('type'); $t->string('status'); $t->text('reason'); $t->text('internal_notes')->nullable();
            foreach (['deposit_collected_cents', 'wallet_refund_cents', 'gross_amount_cents', 'auto_deduction_cents', 'manual_deduction_cents', 'deduction_amount_cents', 'net_amount_cents'] as $column) $t->bigInteger($column)->default(0);
            foreach (['requested_by', 'approved_by', 'paid_by'] as $column) $t->string($column)->nullable();
            foreach (['requested_at', 'approved_at', 'paid_at'] as $column) $t->timestamp($column)->nullable();
            $t->timestamps();
        });
        Schema::create('refund_items', function (Blueprint $t) {
            $t->ulid('id')->primary(); $t->string('refund_id'); $t->string('origin')->default('manual');
            foreach (['fee_type_id', 'lease_charge_id', 'invoice_id', 'invoice_item_id'] as $column) $t->string($column)->nullable();
            $t->string('type'); $t->string('description'); $t->unsignedInteger('qty')->default(1);
            $t->bigInteger('unit_amount_cents')->nullable(); $t->bigInteger('amount_cents'); $t->timestamps();
        });
        Schema::create('refund_disbursements', function (Blueprint $t) {
            $t->ulid('id')->primary(); $t->string('refund_id'); $t->bigInteger('amount_cents');
            $t->string('bank_detail_id')->nullable(); $t->string('method'); $t->string('reference')->nullable();
            $t->string('status'); $t->timestamp('processed_at')->nullable(); $t->timestamps();
        });
        Schema::table('leases', function (Blueprint $t) { $t->boolean('is_pending_renewal')->default(false); $t->string('refund_document_id')->nullable(); });
        Schema::table('users', fn (Blueprint $t) => $t->json('account_permissions')->nullable());
        Schema::create('maintenance', function (Blueprint $t) {
            $t->ulid('id')->primary(); $t->string('lease_id'); $t->string('asset_room_id')->nullable();
            $t->string('title'); $t->text('desc'); $t->string('photo_path')->nullable();
            $t->string('status')->default('Pending'); $t->integer('cost')->default(0); $t->string('paid_by'); $t->timestamps();
        });
        Schema::create('fee_types', function (Blueprint $t) {
            $t->ulid('id')->primary(); $t->string('user_id'); $t->string('name'); $t->string('category'); $t->boolean('is_active')->default(true); $t->boolean('is_system')->default(false); $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->id(); $t->string('user_id'); $t->string('key'); $t->json('value'); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        (require database_path('migrations/2026_11_15_000001_add_customer_service_maintenance_work_orders.php'))->up();
        (require database_path('migrations/2026_11_18_000001_add_manual_maintenance_items.php'))->up();
        (require database_path('migrations/2026_01_07_042414_create_document_sequences_table.php'))->up();
        (require database_path('migrations/2026_11_18_000004_scope_maintenance_numbers_to_management_account.php'))->up();
        (require database_path('migrations/2026_11_18_000005_add_assigned_staff_to_maintenance.php'))->up();
        (require database_path('migrations/2026_11_19_000001_add_phone_to_staff_table.php'))->up();
        (require database_path('migrations/2026_11_19_000002_link_refund_deductions_to_maintenance.php'))->up();
        (require database_path('migrations/2026_11_19_000003_link_maintenance_to_leasables.php'))->up();
    }
}
