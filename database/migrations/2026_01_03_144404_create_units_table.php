<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            // 使用 ULID 保持一致性
            $table->ulid('id')->primary();
            // 关联到 Property (大楼)
            $table->foreignUlid('property_id')->constrained()->onDelete('cascade');

            // 关联到 Owner (业主) 
            // 理由：在 Condo 模式下，业主拥有的是这个 Unit 的 Title
            $table->foreignUlid('owner_id')->constrained()->onDelete('cascade');

            // 核心字段
            $table->string('unit_no')->index();       // 例如: A-10-01
            $table->string('block')->index()->nullable();      // 例如: Block A
            $table->integer('floor')->index()->nullable();     // 楼层: 10
            $table->decimal('sqft', 8, 2)->nullable();// 面积: 1313.00
            
            // 财务/管理字段
            $table->decimal('management_fee', 10, 2)->default(0); // 物业管理费
            $table->string('electricity_acc_no')->index()->nullable();    // TNB 账号 (主表)
            $table->string('water_acc_no')->index()->nullable();          // 水费账号
            
            // 状态
            $table->string('status')->default('active'); // active, maintenance, sold
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
