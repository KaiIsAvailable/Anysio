<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance', function (Blueprint $table) {
            $table->dropUnique('maintenance_maintenance_number_unique');
            $table->unique(['management_user_id', 'maintenance_number'], 'maintenance_management_number_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('maintenance')->whereNotNull('maintenance_number')->groupBy('maintenance_number')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Maintenance numbers overlap across accounts. Preserve these records before rolling back.');
        }
        Schema::table('maintenance', function (Blueprint $table) {
            $table->dropUnique('maintenance_management_number_unique');
            $table->unique('maintenance_number');
        });
    }
};
