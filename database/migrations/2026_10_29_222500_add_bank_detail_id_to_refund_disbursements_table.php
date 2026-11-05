<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_disbursements', function (Blueprint $table) {
            $table->foreignUlid('bank_detail_id')
                ->nullable()
                ->after('refund_id')
                ->constrained('bank_details')
                ->nullOnDelete();

            $table->index('bank_detail_id');
        });
    }

    public function down(): void
    {
        Schema::table('refund_disbursements', function (Blueprint $table) {
            $table->dropForeign(['bank_detail_id']);
            $table->dropIndex(['bank_detail_id']);
            $table->dropColumn('bank_detail_id');
        });
    }
};
