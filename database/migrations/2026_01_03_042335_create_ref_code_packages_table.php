<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_code_packages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('ref_code')->unique();
            $table->string('name');              
            $table->string('price_mode')->default('monthly')->index(); 
            $table->integer('price')->default(0); 
            $table->integer('commission_rate')->default(0); 
            $table->integer('base_lease')->default(0); 
            $table->integer('max_lease_limit')->default(0); 
            $table->boolean('allow_extra_lease')->default(true); 
            $table->integer('extra_lease_price')->default(0); 

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_code_packages');
    }
};