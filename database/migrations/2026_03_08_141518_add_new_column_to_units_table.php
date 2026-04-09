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
        Schema::table('units', function (Blueprint $table) {
            // 1. 删除旧的 management_fee 字段
            if (Schema::hasColumn('units', 'management_fee')) {
                $table->dropColumn('management_fee');
            }
            if (Schema::hasColumn('units', 'total_rooms')){
                $table->dropColumn('total_rooms');
            }
        });

        Schema::table('units', function (Blueprint $table) {
            // 默认设为 false，表示默认是整套单位
            if (!Schema::hasColumn('units', 'has_rooms')){
                $table->boolean('has_rooms')->default(false)->after('status');
            }
            $table->integer('total_rooms')->default(0)->after('has_rooms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            // 回滚时，按照相反的顺序删除字段
            $table->dropColumn(['has_rooms', 'total_rooms']);
        });
    }
};
