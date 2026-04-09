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
        Schema::create('properties', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->index();      // 物业名称 (e.g. SS2 Landed House)
            $table->string('address')->index();   // 地址
            $table->string('city')->index();      // 城市
            $table->string('postcode')->index();  // 邮编
            $table->string('state')->index();     // 州属
            $table->string('type')->index();      // 类型 (Landed, Condo, Shoplot)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
