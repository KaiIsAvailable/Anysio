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
            });
        }
        Schema::create('staff', function (Blueprint $t) { $t->string('id'); $t->string('user_id'); $t->string('user_mgnt_id')->nullable(); });
        Schema::create('permissions', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps(); });
        Schema::create('roles', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->string('team_id')->nullable(); });
        Schema::create('role_has_permissions', function (Blueprint $t) { $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id'); });
        Schema::create('model_has_roles', function (Blueprint $t) { $t->unsignedBigInteger('role_id'); $t->string('model_type'); $t->string('model_id'); $t->string('team_id'); });
        Schema::create('model_has_permissions', function (Blueprint $t) { $t->unsignedBigInteger('permission_id'); $t->string('model_type'); $t->string('model_id'); $t->string('team_id'); });
        Schema::create('user_management', function (Blueprint $t) { $t->string('id'); $t->string('user_id'); $t->softDeletes(); });
        Schema::create('invoices', function (Blueprint $t) { $t->string('id'); $t->string('user_id'); $t->string('context'); $t->string('status'); $t->timestamps(); $t->softDeletes(); });
        Schema::table('users', fn (Blueprint $t) => $t->json('account_permissions')->nullable());
        Schema::create('maintenance', function (Blueprint $t) {
            $t->ulid('id')->primary(); $t->string('lease_id'); $t->string('asset_room_id')->nullable();
            $t->string('title'); $t->text('desc'); $t->string('photo_path')->nullable();
            $t->string('status')->default('Pending'); $t->integer('cost')->default(0); $t->string('paid_by'); $t->timestamps();
        });
        (require database_path('migrations/2026_11_15_000001_add_customer_service_maintenance_work_orders.php'))->up();
    }
}
