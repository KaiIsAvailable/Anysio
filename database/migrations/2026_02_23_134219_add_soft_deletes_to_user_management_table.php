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
            if (!Schema::hasColumn('user_management', 'start_date')) {
                $table->timestamp('start_date')->nullable()->after('subscription_status');
            }
            if (!Schema::hasColumn('user_management', 'end_date')) {
                $table->timestamp('end_date')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('user_management', 'extra_lease')) {
                $table->integer('extra_lease')->nullable()->after('end_date');
            }
            if (!Schema::hasColumn('user_management', 'tot_price')) {
                $table->integer('tot_price')->nullable()->after('extra_lease');
            }
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
            if (Schema::hasColumn('user_management', 'start_date')) {
                $table->dropColumn('start_date');
            }
            if (Schema::hasColumn('user_management', 'end_date')) {
                $table->dropColumn('end_date');
            }
            if (Schema::hasColumn('user_management', 'deleted_at')) {
                $table->dropSoftDeletes(); // 注意：软删除有专用的删除方法
            }
        });
    }
};