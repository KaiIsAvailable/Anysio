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
        Schema::table('properties', function (Blueprint $table) {
            // 1. 如果旧的 owner_id 已经存在，先删除它的外键和字段
            if (Schema::hasColumn('properties', 'owner_id')) {
                // 注意：必须先删外键，再删字段
                $table->dropForeign(['owner_id']); 
                $table->dropColumn('owner_id');
            }
        });

        Schema::table('properties', function (Blueprint $table) {
            // 2. 重新创建正确的 owner_id 字段和外键
            $table->foreignUlid('owner_id')
                ->nullable()
                ->after('type')
                ->constrained('owners') // 明确指向 owners 表
                ->onDelete('set null');

            // 3. 顺便加上 status 字段
            if (!Schema::hasColumn('properties', 'status')) {
                $table->string('status')->default('Vacant')->after('owner_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn(['owner_id', 'status']);
        });
    }
};
