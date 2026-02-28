<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_management_id')
                  ->constrained('user_management');
            $table->string('invoice_no')->unique()->nullable();
            $table->string('payment_type')->default('registration_fee'); // registration, subscription, upgrade
            $table->integer('amount_due');       // 应付总额
            $table->integer('amount_paid')->default(0); // 已付总额
            $table->string('payment_method')->nullable(); // bank_transfer, e-wallet, cash
            $table->string('transaction_ref')->nullable(); // 银行流水号
            $table->string('bank_name')->nullable();       // 收到钱的银行 (e.g. Maybank)
            $table->string('receipt_path')->nullable();    // 上传的凭证路径
            $table->string('status')->default('pending');  // pending, partial, paid, rejected
            $table->foreignUlid('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_payments');
    }
};