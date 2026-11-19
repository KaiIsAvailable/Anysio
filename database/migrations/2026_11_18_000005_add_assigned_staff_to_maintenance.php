<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance', function (Blueprint $table) {
            $table->foreignUlid('assigned_staff_id')->nullable()->constrained('staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('maintenance')->whereNotNull('assigned_staff_id')->exists()) {
            throw new RuntimeException('Maintenance staff assignments exist. Preserve these records before rolling back.');
        }
        Schema::table('maintenance', fn (Blueprint $table) => $table->dropConstrainedForeignId('assigned_staff_id'));
    }
};
