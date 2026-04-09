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
        if (Schema::hasTable('leases')) {
            Schema::table('leases', function (Blueprint $table) {
                
                // 1. 处理 parent_lease_id (必须也是 ULID 类型)
                if (!Schema::hasColumn('leases', 'parent_lease_id')) {
                    $table->foreignUlid('parent_lease_id')
                          ->nullable()
                          ->after('id') // 放在主键 ID 后面
                          ->constrained('leases') // 关联到自己这张表
                          ->onDelete('set null'); // 如果父级删除了，子级设为 null
                }

                // 2. 处理 is_current (标记是否为最新租约)
                if (!Schema::hasColumn('leases', 'is_current')) {
                    $table->boolean('is_current')
                          ->default(true)
                          ->after('status')
                          ->index();
                }

                if (!Schema::hasColumn('leases', 'checked_out_at')) {
                    $table->date('checked_out_at')
                        ->nullable()
                        ->after('end_date')
                        ->index();
                }

                if (!Schema::hasColumn('leases', 'agreement_ended_at')) {
                    $table->date('agreement_ended_at')
                        ->nullable()
                        ->after('checked_out_at')
                        ->index();
                }

                if (!Schema::hasColumn('leases', 'stamping_status')) {
                    $table->boolean('stamping_status')->default(false)->after('agreement_ended_at')->index();
                    
                    $table->string('stamping_cert_path')->nullable()->after('stamping_status');
                    
                    $table->string('stamping_reference_no')->nullable()->after('stamping_cert_path')
                        ->comment('LHDN Adjudication Number / No. Penyelarasan');
                        
                    $table->timestamp('stamped_at')->nullable()->after('stamping_reference_no');
                }
                
                // 以后若需增加更多 Lease 相关字段，直接在此下方添加 if (!Schema::hasColumn...)
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('leases')) {
            Schema::table('leases', function (Blueprint $table) {
                if (Schema::hasColumn('leases', 'parent_lease_id')) {
                    // 必须先删除外键约束，才能删除字段
                    $table->dropForeign(['parent_lease_id']);
                    $table->dropColumn('parent_lease_id');
                }

                if (Schema::hasColumn('leases', 'is_current')) {
                    $table->dropColumn('is_current');
                }

                if (Schema::hasColumn('leases', 'checked_out_at')) {
                    $table->dropColumn('checked_out_at');
                }

                if (Schema::hasColumn('leases', 'agreement_ended_at')) {
                    $table->dropColumn('agreement_ended_at');
                }
            });
        }
    }
};