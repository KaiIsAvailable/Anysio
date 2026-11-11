<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignUlid('settlement_refund_id')->nullable()->constrained('refunds')->restrictOnDelete();
            $table->text('adjustment_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('settlement_refund_id');
            $table->dropColumn('adjustment_reason');
        });
    }
};
