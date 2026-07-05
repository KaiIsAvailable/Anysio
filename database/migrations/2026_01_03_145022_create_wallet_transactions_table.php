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
            $table->foreignUlid('wallet_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount'); // signed — positive = credit, negative = debit
            $table->string('type');       // overpayment_credit | payment_debit | manual_credit | refund
            $table->string('reference_id')->nullable(); // invoice_id or transaction_id
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};