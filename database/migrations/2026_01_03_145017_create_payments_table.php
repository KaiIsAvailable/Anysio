<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('lease_id')->constrained('leases')->cascadeOnDelete();
            
            $table->string('invoice_no')->unique();
            $table->string('payment_type'); // rent, deposit, utility_water, utility_electric, fine
            $table->date('period')->nullable()->index();
            
            // 金额核心
            $table->unsignedInteger('amount_due');         // 应付
            $table->integer('amount_paid')->default(0);    // 实付
            $table->integer('amount_over_paid')->default(0);  // 多付 (Credit)
            $table->integer('amount_under_paid')->default(0); // 少付 (Arrears)
            
            // 支付状态
            $table->string('status')->default('unpaid')->index();
            $table->date('payment_date')->nullable();
            $table->string('payment_method')->nullable();  // cash, transfer, cheque
            $table->string('transaction_ref')->nullable();
            $table->string('received_via')->nullable();    // Maybank, Public Bank, Petty Cash
            $table->string('receipt_path')->nullable();
            
            // 附件信息
            $table->string('attachment')->nullable(); // 租客上传的转账截图路径
            
            // 审核与审计
            $table->foreignUlid('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();
            $table->softDeletes(); // 财务记录建议保留软删除，防止误删后无法追溯
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};