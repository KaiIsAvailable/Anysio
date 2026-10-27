<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('refund_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('refund_id')->constrained()->cascadeOnDelete();
            
            // 🌟 Add fee_type_id so custom deduction fee types can be selected & saved
            $table->foreignUlid('fee_type_id')->nullable()->constrained('fee_types')->nullOnDelete();
            
            $table->foreignUlid('lease_charge_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            
            $table->string('type'); // refundable_amount, damage, utility, cleaning, adjustment
            $table->string('description');
            
            // 🌟 Add quantity so items like quantities > 1 calculate properly
            $table->integer('qty')->default(1);
            
            $table->bigInteger('amount_cents'); // Total amount in cents (qty * unit price)
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('refund_items');
    }
};