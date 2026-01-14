<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('room_id')->constrained('rooms');
            $table->foreignUlid('tenant_id')->constrained('tenants');
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->integer('monthly_rent')->index();
            $table->integer('security_deposit')->index();
            $table->integer('utilities_depost')->index();
            $table->string('status')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};