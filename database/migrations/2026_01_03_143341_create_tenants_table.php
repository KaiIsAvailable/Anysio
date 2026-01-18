<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('owner_id')->nullable()->index()->constrained('users')->cascadeOnDelete();
            $table->string('phone')->index();
            $table->string('ic_number')->nullable()->index();
            $table->string('passport')->nullable()->index();
            $table->string('nationality')->index();
            $table->string('gender')->index();
            $table->string('occupation')->nullable();
            $table->string('ic_photo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};