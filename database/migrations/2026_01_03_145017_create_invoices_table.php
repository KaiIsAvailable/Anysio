<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->string('invoice_no')->unique();
            $table->bigInteger('total_amount')->unsigned(); // 总金额 (Cents)
            $table->date('due_date')->index();
            $table->string('status')->default('unpaid')->index(); // unpaid, partial, paid
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};