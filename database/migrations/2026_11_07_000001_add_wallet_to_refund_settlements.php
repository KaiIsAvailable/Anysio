<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->unsignedBigInteger('wallet_refund_cents')->default(0);
        });
        Schema::table('wallets', function (Blueprint $table) {
            $table->foreignUlid('reserved_refund_id')->nullable()->constrained('refunds')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wallets', fn (Blueprint $table) => $table->dropConstrainedForeignId('reserved_refund_id'));
        Schema::table('refunds', fn (Blueprint $table) => $table->dropColumn('wallet_refund_cents'));
    }
};
