<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Add lease_id as a ULID, making it nullable in case some tickets aren't tied to a lease
            $table->ulid('lease_id')->nullable()->after('receive_id')->index();

            // Optional: If you want a strict foreign key relationship
            // $table->foreign('lease_id')->references('id')->on('leases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // $table->dropForeign(['lease_id']); // Uncomment if you added the foreign key above
            $table->dropColumn('lease_id');
        });
    }
};