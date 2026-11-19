<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['lease_charges', 'invoice_items', 'refund_items', 'maintenance'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['fee_type_id']);
                // A concurrent insertion must never be silently detached by deletion.
                $table->foreign('fee_type_id')->references('id')->on('fee_types')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['fee_type_id']);
                $table->foreign('fee_type_id')->references('id')->on('fee_types')->nullOnDelete();
            });
        }
    }
};
