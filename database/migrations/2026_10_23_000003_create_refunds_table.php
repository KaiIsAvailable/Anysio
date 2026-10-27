<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('refunds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('refund_number')->unique();
            $table->foreignUlid('lease_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('type'); // deposit, payment_reversal, overpayment
            $table->bigInteger('gross_amount_cents')->default(0);
            $table->bigInteger('deduction_amount_cents')->default(0);
            $table->bigInteger('net_amount_cents')->default(0);
            $table->string('status')->default('draft'); // draft, pending, approved, paid, rejected, cancelled
            $table->text('reason');
            $table->text('internal_notes')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('paid_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['lease_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('refunds');
    }
};