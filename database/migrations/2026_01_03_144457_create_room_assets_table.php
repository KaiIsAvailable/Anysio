<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_assets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('name')->index();
            $table->string('condition')->index();
            $table->date('last_maintenance')->nullable()->index();
            $table->string('remark')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_assets');
    }
};