<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->foreignUlid('asset_room_id')->nullable()->constrained('asset_room')->nullOnDelete();
            $table->string('title');
            $table->text('desc');
            $table->string('photo_path')->nullable();
            $table->string('status')->default('Pending')->index();
            $table->integer('cost')->default(0)->index();
            $table->string('paid_by')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance');
    }
};