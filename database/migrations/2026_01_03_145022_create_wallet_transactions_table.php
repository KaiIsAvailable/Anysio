<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->bigInteger('amount'); // 正数存入，负数支出
            $table->string('type'); // 'overpayment_credit', 'invoice_offset', 'refund_payout'
            $table->string('reference_id')->nullable(); // 关联 Invoice 或 Transaction ID
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};