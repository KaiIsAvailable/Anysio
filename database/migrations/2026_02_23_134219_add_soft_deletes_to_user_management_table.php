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
        Schema::table('user_management', function (Blueprint $table) {
            // 在 subscription_status 后面增加 start_date 和 end_date
            // 使用 nullable() 是因为新用户可能还没开始订阅
            $table->timestamp('start_date')->nullable()->after('subscription_status');
            $table->timestamp('end_date')->nullable()->after('start_date');
            
            // 如果你之前没跑过这个 softDeletes，可以保留在这里
            // 如果已经跑过了，就把下面这行删掉
            if (!Schema::hasColumn('user_management', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_management', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
            $table->dropSoftDeletes();
        });
    }
};