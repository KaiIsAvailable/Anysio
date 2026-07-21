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
            $table->string('context')->default('tenant');
            $table->string('billable_type')->nullable();
            $table->ulid('billable_id')->nullable();
            $table->ulid('lease_id')->nullable();          
            $table->foreign('lease_id')->references('id')->on('leases')->nullOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invoice_no', 40)->unique();
            $table->foreignUlid('document_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('rent');
            $table->date('period');
            $table->date('due_date');
            $table->unsignedBigInteger('total_amount');
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->unsignedBigInteger('amount_balance')->default(0);
            $table->string('status')->default('unpaid');
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // --- Indexes ---
            $table->index(['context', 'status']);
            $table->index(['context', 'user_id']);
            $table->index(['lease_id', 'status']);
            $table->index(['status', 'due_date']);          
            $table->index(['billable_type', 'billable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};