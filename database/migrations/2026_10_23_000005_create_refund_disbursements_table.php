<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('refund_disbursements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('refund_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_cents');
            $table->string('method'); // e.g., bank_transfer, cash, qr_pay
            $table->string('reference')->nullable();
            $table->string('status')->default('pending'); // pending, successful, failed
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('refund_disbursements');
    }
};