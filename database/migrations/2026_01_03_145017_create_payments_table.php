<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('payment_type');
            $table->integer('amount_due'); 
            $table->integer('amount_paid')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('status')->default('pending');
            $table->foreignUlid('approved_by')->nullable()->constrained('owners')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};