<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utilities', function (Blueprint $table) {
            $table->dropForeign(['lease_id']);
        });
        Schema::table('utilities', function (Blueprint $table) {
            $table->ulid('lease_id')->nullable()->change();
            $table->foreign('lease_id')->references('id')->on('leases')->nullOnDelete();
            $table->foreignUlid('utility_service_id')->nullable()->constrained('utility_services')->restrictOnDelete();
            $table->date('period')->nullable();
            $table->date('prev_reading_date')->nullable();
            $table->date('curr_reading_date')->nullable();
            $table->decimal('prev_reading', 15, 3)->nullable()->change();
            $table->decimal('curr_reading', 15, 3)->nullable()->change();
            $table->integer('amount')->nullable()->change(); // Existing amounts remain in sen.
            $table->decimal('applied_rate', 15, 6)->nullable(); // RM per usage unit.
            $table->json('calculation_details')->nullable();
            $table->foreignUlid('invoice_item_id')->nullable()->constrained('invoice_items')->restrictOnDelete();
            $table->string('status', 20)->default('confirmed'); // Preserve legacy history as recorded.
            $table->index(['utility_service_id', 'curr_reading_date'], 'utilities_service_reading_date');
            $table->index(['lease_id', 'period'], 'utilities_lease_period');
        });
    }

    public function down(): void
    {
        // Restoring the old schema would truncate decimal readings or lose new history.
        if (DB::table('utilities')->where(function ($query) {
            $query->whereNull('lease_id')->orWhereNull('prev_reading')->orWhereNull('curr_reading')
                ->orWhereNull('amount')->orWhereNotNull('utility_service_id')->orWhereNotNull('invoice_item_id')
                ->orWhereNotNull('period')->orWhereNotNull('prev_reading_date')->orWhereNotNull('curr_reading_date')
                ->orWhereNotNull('applied_rate')->orWhereNotNull('calculation_details')->orWhere('status', '!=', 'confirmed')
                ->orWhereRaw('prev_reading != ROUND(prev_reading, 0)')
                ->orWhereRaw('curr_reading != ROUND(curr_reading, 0)');
        })->exists()) {
            throw new RuntimeException('Utility history uses the new schema. Export and reconcile it before rollback.');
        }
        Schema::table('utilities', function (Blueprint $table) {
            $table->dropForeign(['lease_id']);
            $table->dropForeign(['utility_service_id']);
            $table->dropForeign(['invoice_item_id']);
            $table->dropIndex('utilities_service_reading_date');
            $table->dropIndex('utilities_lease_period');
        });
        Schema::table('utilities', function (Blueprint $table) {
            $table->dropColumn(['utility_service_id', 'period', 'prev_reading_date', 'curr_reading_date', 'applied_rate', 'calculation_details', 'invoice_item_id', 'status']);
            $table->ulid('lease_id')->nullable(false)->change();
            $table->integer('prev_reading')->nullable(false)->change();
            $table->integer('curr_reading')->nullable(false)->change();
            $table->integer('amount')->nullable(false)->change();
            $table->foreign('lease_id')->references('id')->on('leases')->cascadeOnDelete();
        });
    }
};
