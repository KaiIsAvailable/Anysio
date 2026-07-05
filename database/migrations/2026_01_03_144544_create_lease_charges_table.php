<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_charges', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->foreignUlid('fee_type_id')->nullable()->constrained('fee_types')->nullOnDelete();
            $table->string('description');            
            $table->unsignedBigInteger('amount');     
            $table->string('charge_type')->default('recurring');
            // recurring   → billed every cycle automatically
            // one_time    → billed once (e.g. initial deposit, admin fee)
            // refundable  → one_time + must be returned at lease end
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0); 
            $table->timestamps();
            $table->softDeletes(); 
            $table->index(['lease_id', 'charge_type']);
            $table->index(['lease_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_charges');
    }
};