<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // 1. Drop the old global unique index
            // (Laravel usually names unique indexes as table_column_unique)
            $table->dropUnique('invoices_invoice_no_unique');

            // 2. Add the new composite unique constraint per user
            $table->unique(['user_id', 'invoice_no']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'invoice_no']);
            $table->string('invoice_no', 40)->unique();
        });
    }
};
