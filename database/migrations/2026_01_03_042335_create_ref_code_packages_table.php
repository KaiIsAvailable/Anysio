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
            $table->foreignUlid('user_mgnt_id')->nullable()->constrained('user_management')->nullOnDelete();
            $table->boolean('is_official')->default(false);
            $table->integer('ref_installation_price'); 
            $table->integer('ref_monthly_price');      
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_code_packages');
    }
};