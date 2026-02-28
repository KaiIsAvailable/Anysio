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
        Schema::create('assets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // 关键：锁定这个资产分类是谁创建的
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name')->index(); // 例如：空调、热水器
            $table->string('category')->nullable(); // 例如：家电、家具
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
