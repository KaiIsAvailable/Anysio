<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET foreign_key_checks = 0;');

        // 1. Drop old messy tables completely
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');

        DB::statement('SET foreign_key_checks = 1;');

        // 2. Create permissions table
        Schema::create('permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        // 3. Create roles table (Scoped to team/boss using ULID)
        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('team_id')->nullable()->index(); // Boss ID context
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['team_id', 'name', 'guard_name'], 'roles_team_name_guard_unique');
        });

        // 4. Create model_has_permissions table (ULID model_id + team_id)
        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->ulid('model_id'); // Staff / User ULID
            $table->ulid('team_id');  // Boss team ID

            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->index('team_id', 'model_has_permissions_team_id_index');

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->cascadeOnDelete();

            $table->primary(
                ['team_id', 'permission_id', 'model_id', 'model_type'],
                'model_has_permissions_primary'
            );
        });

        // 5. Create model_has_roles table (ULID model_id + team_id)
        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->ulid('model_id'); // Staff / User ULID
            $table->ulid('team_id');  // Boss team ID

            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->index('team_id', 'model_has_roles_team_id_index');

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();

            $table->primary(
                ['team_id', 'role_id', 'model_id', 'model_type'],
                'model_has_roles_primary'
            );
        });

        // 6. Create role_has_permissions table
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->cascadeOnDelete();

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_primary');
        });
    }

    public function down(): void
    {
        DB::statement('SET foreign_key_checks = 0;');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        DB::statement('SET foreign_key_checks = 1;');
    }
};