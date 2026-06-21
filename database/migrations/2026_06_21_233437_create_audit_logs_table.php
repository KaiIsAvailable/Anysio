<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary(); // 使用 ULID 作为主键
            $table->ulid('user_id')->nullable(); // 操作人ID
            $table->string('event'); // created, updated, deleted
            $table->string('auditable_type'); // 模型类名
            $table->ulid('auditable_id'); // 模型 ID
            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // 建立联合索引，极大提升查询效率
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit_logs');
    }
};
