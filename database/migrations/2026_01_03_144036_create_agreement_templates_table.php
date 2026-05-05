<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreements', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // 添加 parent_agreement_id，放在 id 后面
            $table->ulid('parent_agreement_id')->nullable()->index();
            $table->foreign('parent_agreement_id')->references('id')->on('agreements')->onDelete('set null');
            
            // 房东 ID (可为空，为空代表是系统全局协议)
            $table->ulid('owner_id')->nullable()->index();
            $table->foreign('owner_id')->references('id')->on('owners')->onDelete('cascade');
            
            // 协议类型：'tos', 'privacy', 'rental_lease'
            $table->string('type')->index(); 
            
            $table->string('title');               // 协议标题，如 "标准一年期租约"
            $table->string('version')->nullable(); // 版本号，如 "1.0.1"
            
            // 核心：存放带有 {owner_name} 等变量的固定文本
            $table->longText('content'); 
            
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreements');
    }
};