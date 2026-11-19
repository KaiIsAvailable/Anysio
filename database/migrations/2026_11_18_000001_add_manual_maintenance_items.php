<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance', function (Blueprint $table) {
            $table->foreignUlid('fee_type_id')->nullable()->constrained('fee_types')->nullOnDelete();
            $table->unsignedInteger('qty')->default(1);
            $table->uuid('manual_request_key')->nullable();
            $table->string('manual_payload_hash', 64)->nullable();
            $table->unique(['management_user_id', 'manual_request_key'], 'maintenance_manual_request_unique');
        });
        Schema::table('maintenance_events', fn (Blueprint $table) => $table->foreignUlid('ticket_id')->nullable()->change());
    }

    public function down(): void
    {
        if (DB::table('maintenance')->where('source_type', 'manual')->orWhereNotNull('fee_type_id')->orWhere('qty', '!=', 1)->exists()) {
            throw new RuntimeException('Maintenance items exist. Preserve these operational records before rolling back this schema.');
        }
        Schema::table('maintenance_events', fn (Blueprint $table) => $table->foreignUlid('ticket_id')->nullable(false)->change());
        Schema::table('maintenance', function (Blueprint $table) {
            $table->dropUnique('maintenance_manual_request_unique');
            $table->dropConstrainedForeignId('fee_type_id');
            $table->dropColumn(['qty', 'manual_request_key', 'manual_payload_hash']);
        });
    }
};
