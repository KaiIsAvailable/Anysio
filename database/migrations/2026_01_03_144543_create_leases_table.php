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
            $table->string('leasable_type')->index(); 
            $table->ulid('leasable_id')->index();
            $table->foreignUlid('tenant_id')->constrained('tenants');
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->string('term_type')->default('monthly')->index();
            $table->integer('rent_price')->index(); 
            $table->string('deposit_mode')->default('both')->index();
            $table->integer('security_deposit')->default(0)->index();
            $table->integer('utilities_deposit')->default(0)->index(); // 修正了原本的拼写错误 depost -> deposit
            $table->string('status')->index(); // New, Renew, Check Out, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};