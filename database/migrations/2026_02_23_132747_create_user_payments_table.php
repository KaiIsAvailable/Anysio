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
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ref_code')->index();
            
            $table->string('invoice_no')->unique();
            // 修正：这里的类型应该是针对 SaaS 订阅的
            $table->string('payment_type')->default('subscription'); // subscription, upgrade, topup
            
            // 金额核心 (以分为单位，例如 18000 代表 RM180.00)
            $table->unsignedInteger('amount_due');         // 订单原价
            $table->integer('amount_paid')->default(0);    // 实际到账
            $table->integer('amount_over_paid')->default(0);  
            $table->integer('amount_under_paid')->default(0); 
            
            // 支付状态
            $table->string('status')->default('pending')->index(); // pending, paid, rejected, cancelled
            $table->date('payment_date')->nullable();
            $table->string('payment_method')->nullable();  // bank_transfer, duitnow, credit_card
            $table->string('transaction_ref')->nullable(); // user自己银行流水 Reference No.
            $table->string('approve_transaction_ref')->nullable(); // approve的时候写银行流水 Reference No.
            $table->string('received_via')->nullable();    // 你的收款银行 (e.g. Maybank-Anysio-Account)
            
            // 文件处理
            $table->string('attachment')->nullable();      // 用户上传的 Proof of Payment (图片/PDF)
            $table->string('receipt_path')->nullable();    // 系统生成的正式收据 (Invoice/Receipt PDF)
            
            // 审核审计
            $table->foreignUlid('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();           // 如果 reject，可以写原因（如：金额不符）

            $table->timestamps();
            $table->softDeletes(); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_payments');
    }
};