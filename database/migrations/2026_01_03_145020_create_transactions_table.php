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
            $table->foreignUlid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->bigInteger('amount_paid')->unsigned();
            $table->string('payment_method'); // cash, transfer, cheque, fpx
            $table->string('transaction_ref')->unique();
            $table->string('receipt_no')->unique()->nullable();
            $table->date('payment_date');
            $table->foreignUlid('approved_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};