<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            if (Schema::hasColumn('rooms', 'owner_id')) {
                try {
                    $table->dropForeign(['owner_id']);
                } catch (\Exception $e) {
                    // 如果外键名字不对报错，这里会跳过，防止程序崩溃
                }
                $table->dropColumn('owner_id');
            }

            if (!Schema::hasColumn('rooms', 'unit_id')) {
                $table->foreignUlid('unit_id')->after('id')->nullable()->constrained()->cascadeOnDelete();
            }

            if (!Schema::hasColumn('rooms', 'status')) {
                $table->string('status')->default('Vacant')->after('unit_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            if (Schema::hasColumn('rooms', 'unit_id')) {
                $table->dropForeign(['unit_id']);
                $table->dropColumn('unit_id');
            }
        });
    }
};