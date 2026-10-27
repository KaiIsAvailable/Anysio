<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignUlid('lease_charge_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['lease_charge_id']);
            $table->dropColumn('lease_charge_id');
        });
    }
};