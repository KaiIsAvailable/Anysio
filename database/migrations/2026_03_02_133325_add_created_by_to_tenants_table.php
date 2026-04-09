<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenants')) return;

        Schema::table('tenants', function (Blueprint $table) {
            // 1. 处理字段
            if (!Schema::hasColumn('tenants', 'created_by')) {
                $table->foreignUlid('created_by')
                    ->after('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        // 2. 处理索引 (放在闭包外面处理更稳)
        $this->dropIndexIfExists('tenants', 'tenants_ic_number_unique');

        // 3. 建立新的复合索引
        Schema::table('tenants', function (Blueprint $table) {
            // 获取当前表的所有索引名称
            $logicalIndexes = Schema::getIndexes('tenants');
            $indexNames = array_column($logicalIndexes, 'name');

            if (!in_array('tenants_ic_scoped_unique', $indexNames)) {
                $table->unique(['ic_number', 'created_by', 'status'], 'tenants_ic_scoped_unique');
            }
        });
    }

    /**
     * 原生 SQL 方式删除索引，避免方法不存在的报错
     */
    protected function dropIndexIfExists($tableName, $indexName)
    {
        // 获取所有索引
        $logicalIndexes = Schema::getIndexes($tableName);
        $indexNames = array_column($logicalIndexes, 'name');

        if (in_array($indexName, $indexNames)) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $logicalIndexes = Schema::getIndexes('tenants');
            $indexNames = array_column($logicalIndexes, 'name');

            if (in_array('tenants_ic_scoped_unique', $indexNames)) {
                $table->dropUnique('tenants_ic_scoped_unique');
            }

            if (Schema::hasColumn('tenants', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
        });
    }
};