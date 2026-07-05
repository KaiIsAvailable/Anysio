<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_paid');    // cents
            $table->unsignedBigInteger('amount_applied'); // how much went to this invoice
            $table->unsignedBigInteger('amount_excess')->default(0); // went to wallet
            $table->string('payment_method');  // cash|bank_transfer|card|wallet
            $table->string('transaction_ref')->nullable();
            $table->string('receipt_no')->nullable();
            $table->date('payment_date');
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};