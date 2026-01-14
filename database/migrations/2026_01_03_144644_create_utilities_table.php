<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->string('type')->index();
            $table->decimal('prev_reading', 10, 2);
            $table->decimal('curr_reading', 10, 2);
            $table->integer('amount');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utilities');
    }
};