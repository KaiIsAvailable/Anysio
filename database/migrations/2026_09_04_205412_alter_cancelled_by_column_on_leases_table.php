<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Execute immediately and catch error 1091 if it doesn't exist
        try {
            DB::statement('ALTER TABLE leases DROP FOREIGN KEY leases_cancelled_by_foreign');
        } catch (\Exception $e) {
            // Foreign key doesn't exist, safe to ignore
        }

        Schema::table('leases', function (Blueprint $table) {
            if (Schema::hasColumn('leases', 'cancelled_by')) {
                $table->dropColumn('cancelled_by');
            }
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->foreignUlid('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE leases DROP FOREIGN KEY leases_cancelled_by_foreign');
        } catch (\Exception $e) {}

        Schema::table('leases', function (Blueprint $table) {
            if (Schema::hasColumn('leases', 'cancelled_by')) {
                $table->dropColumn('cancelled_by');
            }
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
        });
    }
};