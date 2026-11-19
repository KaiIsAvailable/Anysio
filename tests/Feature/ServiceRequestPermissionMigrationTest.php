<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class ServiceRequestPermissionMigrationTest extends TestCase
{
    public function test_rename_merges_existing_grants_and_can_be_reversed_without_changing_other_modules(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary(); $table->json('account_permissions')->nullable();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id'); $table->string('name'); $table->string('guard_name');
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->integer('role_id'); $table->integer('permission_id');
            $table->primary(['role_id', 'permission_id']);
        });
        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->integer('permission_id'); $table->string('model_type');
            $table->string('model_id'); $table->string('team_id');
            $table->primary(['permission_id', 'model_type', 'model_id', 'team_id']);
        });
        DB::table('permissions')->insert([
            ['id' => 1, 'name' => 'maintenance.tab', 'guard_name' => 'web'],
            ['id' => 2, 'name' => 'service request.tab', 'guard_name' => 'web'],
            ['id' => 3, 'name' => 'maintenance.reply', 'guard_name' => 'web'],
            ['id' => 4, 'name' => 'tenant.edit', 'guard_name' => 'web'],
            ['id' => 5, 'name' => 'maintenance.tab', 'guard_name' => 'api'],
        ]);
        DB::table('users')->insert([
            ['id' => 'manager', 'account_permissions' => json_encode(['maintenance.tab', 'service request.tab', 'maintenance.reply', 'tenant.edit'])],
            ['id' => 'empty', 'account_permissions' => '[]'],
            ['id' => 'unset', 'account_permissions' => null],
        ]);
        DB::table('role_has_permissions')->insert([
            ['role_id' => 10, 'permission_id' => 1], ['role_id' => 10, 'permission_id' => 2],
            ['role_id' => 11, 'permission_id' => 1], ['role_id' => 11, 'permission_id' => 3],
        ]);
        foreach ([1, 2] as $id) {
            DB::table('model_has_permissions')->insert(['permission_id' => $id, 'model_type' => 'App\Models\User', 'model_id' => 'staff', 'team_id' => 'workspace']);
        }

        $migration = require database_path('migrations/2026_11_17_000001_rename_maintenance_permissions_to_service_request.php');
        $migration->up();
        $migration->up();
        $this->assertSame(['service request.tab', 'service request.reply', 'tenant.edit'], json_decode(DB::table('users')->where('id', 'manager')->value('account_permissions'), true));
        $this->assertSame('[]', DB::table('users')->where('id', 'empty')->value('account_permissions'));
        $this->assertNull(DB::table('users')->where('id', 'unset')->value('account_permissions'));
        $this->assertSame(0, DB::table('permissions')->where('name', 'like', 'maintenance.%')->count());
        $this->assertDatabaseHas('permissions', ['id' => 3, 'name' => 'service request.reply']);
        $this->assertDatabaseHas('permissions', ['id' => 4, 'name' => 'tenant.edit']);
        $this->assertDatabaseHas('permissions', ['id' => 5, 'name' => 'service request.tab', 'guard_name' => 'api']);
        $this->assertSame(3, DB::table('role_has_permissions')->count());
        $this->assertDatabaseHas('role_has_permissions', ['role_id' => 11, 'permission_id' => 2]);
        $this->assertDatabaseHas('role_has_permissions', ['role_id' => 11, 'permission_id' => 3]);
        $this->assertDatabaseHas('model_has_permissions', ['permission_id' => 2, 'model_id' => 'staff', 'team_id' => 'workspace']);
        $this->assertSame(1, DB::table('model_has_permissions')->count());

        $migration->down();
        $this->assertSame(['maintenance.tab', 'maintenance.reply', 'tenant.edit'], json_decode(DB::table('users')->where('id', 'manager')->value('account_permissions'), true));
        $this->assertDatabaseHas('permissions', ['id' => 2, 'name' => 'maintenance.tab']);
        $this->assertSame(3, DB::table('role_has_permissions')->count());
    }
}
