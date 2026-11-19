<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance', function (Blueprint $table) {
            $table->foreignUlid('refund_id')->nullable()->constrained('refunds')->restrictOnDelete();
            $table->foreignUlid('refund_item_id')->nullable()->unique()->constrained('refund_items')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (DB::table('maintenance')->whereNotNull('refund_id')->orWhereNotNull('deleted_at')->exists()) {
            throw new RuntimeException('Refund maintenance records exist. Preserve their history before rolling back.');
        }
        Schema::table('maintenance', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refund_item_id');
            $table->dropConstrainedForeignId('refund_id');
            $table->dropSoftDeletes();
        });
    }
};
