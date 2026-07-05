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

            // --- Relations ---
            $table->string('leasable_type')->index();
            $table->ulid('leasable_id')->index();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // --- Lease chain management ---
            $table->foreignUlid('parent_lease_id')->nullable()->constrained('leases')->nullOnDelete();
            $table->foreignUlid('document_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->boolean('is_current')->default(true)->index();

            // --- Dates ---
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->date('checked_out_at')->nullable()->index();
            $table->date('agreement_ended_at')->nullable()->index();
            $table->string('term_type')->default('monthly')->index();

            // --- Billing config ---
            $table->unsignedTinyInteger('due_day')->default(1); // day of month invoice is due (1-28)

            // --- Status ---
            $table->string('status')->default('active')->index();

            // --- Stamping compliance (LHDN) ---
            $table->boolean('stamping_status')->default(false)->index();
            $table->string('stamping_cert_path')->nullable();
            $table->string('stamping_reference_no')->nullable();
            $table->timestamp('stamped_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};