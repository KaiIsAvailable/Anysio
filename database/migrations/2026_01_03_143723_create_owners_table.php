<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owners', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('company_name')->nullable();
            $table->string('ic_number');
            $table->string('phone');
            $table->ulid('referred_by')->nullable();
            $table->string('gender');
            $table->string('subscription_status')->default('active'); // active/locked
            $table->integer('discount_rate')->default(0);
            $table->integer('usage_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owners');
    }
};