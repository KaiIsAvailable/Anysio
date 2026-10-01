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

        Schema::table('roles', function (Blueprint $table) {
            // Drop old user_id foreign key/column if it exists
            try {
                DB::statement('ALTER TABLE roles DROP FOREIGN KEY roles_user_id_foreign;');
            } catch (\Exception $e) {}

            try {
                DB::statement('ALTER TABLE roles DROP INDEX roles_name_guard_name_user_id_unique;');
            } catch (\Exception $e) {}

            if (Schema::hasColumn('roles', 'user_id')) {
                $table->dropColumn('user_id');
            }

            // Add team_id if it doesn't exist
            if (!Schema::hasColumn('roles', 'team_id')) {
                $table->ulid('team_id')->nullable()->index();
            }
        });

        DB::statement('SET foreign_key_checks = 1;');
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'team_id')) {
                $table->dropColumn('team_id');
            }
        });
    }
};