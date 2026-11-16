<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_operations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('module', 32)->index();
            $table->foreignUlid('actor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('manager_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('request_key');
            $table->string('status')->default('queued');
            $table->unsignedInteger('total');
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('page')->default(1);
            $table->string('draft_key');
            $table->longText('payload')->nullable();
            $table->json('counts')->nullable();
            $table->json('errors')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->unique(['module', 'actor_id', 'request_key']);
            $table->index(['actor_id', 'manager_id', 'page', 'acknowledged_at'], 'bulk_pending_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_operations');
    }
};
