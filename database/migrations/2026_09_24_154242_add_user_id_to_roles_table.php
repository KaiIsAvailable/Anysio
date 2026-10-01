<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update the roles table safely
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'user_id')) {
                $table->foreignUlid('user_id')
                      ->nullable()
                      ->after('id')
                      ->constrained('users')
                      ->cascadeOnDelete();
            }
        });

        try {
            Schema::table('roles', function (Blueprint $table) {
                $table->unique(['name', 'guard_name', 'user_id'], 'roles_name_guard_name_user_id_unique');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropUnique('roles_name_guard_name_unique');
            });
        } catch (\Exception $e) {}

        // 2. Update the model_has_roles table safely
        Schema::table('model_has_roles', function (Blueprint $table) {
            if (!Schema::hasColumn('model_has_roles', 'user_id')) {
                $table->foreignUlid('user_id')->nullable()->after('role_id');
            }
        });

        try {
            Schema::table('model_has_roles', function (Blueprint $table) {
                $table->dropPrimary(['role_id', 'model_id', 'model_type']);
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('model_has_roles', function (Blueprint $table) {
                $table->primary(['user_id', 'role_id', 'model_id', 'model_type'], 'model_has_roles_primary');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('model_has_roles', function (Blueprint $table) {
                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->cascadeOnDelete();
            });
        } catch (\Exception $e) {}
    }

    public function down(): void
    {
        // Revert model_has_roles table changes safely
        Schema::table('model_has_roles', function (Blueprint $table) {
            try {
                $table->dropForeign(['user_id']);
            } catch (\Exception $e) {}

            try {
                $table->dropPrimary('model_has_roles_primary');
            } catch (\Exception $e) {}

            if (Schema::hasColumn('model_has_roles', 'user_id')) {
                $table->dropColumn('user_id');
            }

            try {
                $table->primary(['role_id', 'model_id', 'model_type']);
            } catch (\Exception $e) {}
        });

        // Revert roles table changes safely
        Schema::table('roles', function (Blueprint $table) {
            try {
                $table->dropUnique('roles_name_guard_name_user_id_unique');
            } catch (\Exception $e) {}

            try {
                $table->dropForeign(['user_id']);
            } catch (\Exception $e) {}

            if (Schema::hasColumn('roles', 'user_id')) {
                $table->dropColumn('user_id');
            }
        });
    }
};