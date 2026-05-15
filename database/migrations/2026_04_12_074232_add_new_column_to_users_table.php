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
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                
                // 1. 是否同意协议
                if (!Schema::hasColumn('users', 'is_agree')) {
                    $table->boolean('is_agree')
                          ->default(false)
                          ->after('remember_token') // 建议放在密码后面
                          ->comment('User agreement status');
                }

                // 2. 关联的 TOS ID (使用 foreignId 或 foreignUlid，取决于你 Agreements 表的主键类型)
                // 这里假设你用的是常规 unsignedBigInteger，如果是 ULID 请改为 foreignUlid
                if (!Schema::hasColumn('users', 'tos_id')) {
                    $table->foreignUlid('tos_id') // 必须是 foreignUlid 才能匹配 01knya... 这种格式
                        ->nullable()
                        ->after('is_agree')
                        ->constrained('agreements') 
                        ->onDelete('set null');
                }

                // 3. 关联的 Privacy Policy ID
                if (!Schema::hasColumn('users', 'privacy_id')) {
                    $table->foreignUlid('privacy_id') // 同样改为 foreignUlid
                        ->nullable()
                        ->after('tos_id')
                        ->constrained('agreements')
                        ->onDelete('set null');
                }

                // 4. 同意的时间（可选但推荐，法律审计更有力）
                if (!Schema::hasColumn('users', 'agreed_at')) {
                    $table->timestamp('agreed_at')->nullable()->after('privacy_id');
                }

                if (!Schema::hasColumn('users', 'status')) {
                    $table->string('status')->default('active')->index()->after('agreed_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                // 删除外键必须先于删除字段
                if (Schema::hasColumn('users', 'tos_id')) {
                    $table->dropForeign(['tos_id']);
                    $table->dropColumn('tos_id');
                }

                if (Schema::hasColumn('users', 'privacy_id')) {
                    $table->dropForeign(['privacy_id']);
                    $table->dropColumn('privacy_id');
                }

                if (Schema::hasColumn('users', 'is_agree')) {
                    $table->dropColumn('is_agree');
                }

                if (Schema::hasColumn('users', 'agreed_at')) {
                    $table->dropColumn('agreed_at');
                }
            });
        }
    }
};