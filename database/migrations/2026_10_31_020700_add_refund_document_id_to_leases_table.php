<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->foreignUlid('refund_document_id')
                ->nullable()
                ->after('document_id')
                ->constrained('document_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropForeign(['refund_document_id']);
            $table->dropColumn('refund_document_id');
        });
    }
};
