<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // --- 关联部分 ---
            $table->string('leasable_type')->index(); 
            $table->ulid('leasable_id')->index();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            
            // --- 租约链管理 ---
            $table->foreignUlid('parent_lease_id')->nullable()->constrained('leases')->nullOnDelete();
            $table->foreignUlid('document_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->boolean('is_current')->default(true)->index();
            
            // --- 日期与期限 ---
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->date('checked_out_at')->nullable()->index();
            $table->date('agreement_ended_at')->nullable()->index();
            $table->string('term_type')->default('monthly')->index();
            
            // --- 财务与状态 ---
            $table->bigInteger('rent_price')->unsigned()->index(); // 建议改为 bigInteger (Cents)
            $table->bigInteger('security_deposit')->default(0)->index();
            $table->bigInteger('utilities_deposit')->default(0)->index();
            $table->string('deposit_mode')->default('both')->index();
            $table->string('status')->default('active')->index(); // New, Renew, Check Out, etc.
            
            // --- 印花税合规 (LHDN) ---
            $table->boolean('stamping_status')->default(false)->index();
            $table->string('stamping_cert_path')->nullable();
            $table->string('stamping_reference_no')->nullable();
            $table->timestamp('stamped_at')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};