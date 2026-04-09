<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 确保表存在才操作
        if (Schema::hasTable('asset_room')) {
            Schema::table('asset_room', function (Blueprint $table) {
                if (!Schema::hasColumn('asset_room', 'unit_id')) {
                    $table->foreignUlid('unit_id')->nullable()->after('room_id')->constrained('units')->onDelete('cascade');
                }
                $table->foreignUlid('room_id')->nullable()->change();
                if (!Schema::hasColumn('asset_room', 'status')) {
                    $table->string('status')->default('Active')->after('unit_id')->index();
                }
            });
        }

        if (Schema::hasTable('assets')) {
            Schema::table('assets', function (Blueprint $table) {
                if (!Schema::hasColumn('assets', 'status')) {
                    $table->string('status')->default('Active')->after('category')->index();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('asset_room', function (Blueprint $table) {
            if (Schema::hasColumn('asset_room', 'unit_id')) {
                $table->dropForeign(['unit_id']);
                $table->dropColumn('unit_id');
            }
            if (Schema::hasColumn('asset_room', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};