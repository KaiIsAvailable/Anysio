<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_room', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // 关联到资产字典
            $table->foreignUlid('asset_id')->constrained('assets')->cascadeOnDelete();
            // 关联到具体房间
            $table->foreignUlid('room_id')->constrained('rooms')->cascadeOnDelete();
            
            // 保留你需要的状态字段，因为同一款空调在不同房间的新旧程度不同
            $table->string('condition')->default('Good')->index();
            $table->date('last_maintenance')->nullable();
            $table->string('remark')->nullable();
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_assets');
    }
};