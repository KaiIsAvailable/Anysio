<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Temporarily disable foreign key checks to prevent MySQL lock errors
        DB::statement('SET foreign_key_checks = 0;');

        // 1. Fix model_has_roles table
        if (Schema::hasColumn('model_has_roles', 'user_id')) {
            try {
                DB::statement('ALTER TABLE model_has_roles DROP FOREIGN KEY model_has_roles_user_id_foreign;');
            } catch (\Exception $e) {}

            try {
                // Drop the composite primary key that included user_id
                DB::statement('ALTER TABLE model_has_roles DROP PRIMARY KEY;');
                // Restore standard Spatie primary key
                DB::statement('ALTER TABLE model_has_roles ADD PRIMARY KEY (role_id, model_id, model_type);');
            } catch (\Exception $e) {}

            try {
                DB::statement('ALTER TABLE model_has_roles DROP COLUMN user_id;');
            } catch (\Exception $e) {}
        }

        if (!Schema::hasColumn('model_has_roles', 'team_id')) {
            DB::statement('ALTER TABLE model_has_roles ADD COLUMN team_id CHAR(26) NULL AFTER role_id;');
        }

        // 2. Fix model_has_permissions table
        if (Schema::hasColumn('model_has_permissions', 'user_id')) {
            try {
                DB::statement('ALTER TABLE model_has_permissions DROP FOREIGN KEY model_has_permissions_user_id_foreign;');
            } catch (\Exception $e) {}

            try {
                DB::statement('ALTER TABLE model_has_permissions DROP COLUMN user_id;');
            } catch (\Exception $e) {}
        }

        if (!Schema::hasColumn('model_has_permissions', 'team_id')) {
            DB::statement('ALTER TABLE model_has_permissions ADD COLUMN team_id CHAR(26) NULL AFTER permission_id;');
        }

        // Re-enable foreign key checks
        DB::statement('SET foreign_key_checks = 1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback logic if needed
    }
};