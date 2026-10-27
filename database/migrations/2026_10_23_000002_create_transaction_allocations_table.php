<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('transaction_allocations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('invoice_item_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_cents');
            $table->timestamps();

            $table->index(['transaction_id', 'invoice_item_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('transaction_allocations');
    }
};