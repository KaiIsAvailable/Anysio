<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->bigInteger('deposit_collected_cents')
                ->default(0)
                ->after('gross_amount_cents');
            $table->bigInteger('auto_deduction_cents')
                ->default(0)
                ->after('deposit_collected_cents');
            $table->bigInteger('manual_deduction_cents')
                ->default(0)
                ->after('auto_deduction_cents');

            $table->index(['status', 'created_at'], 'refunds_status_created_at_index');
        });

        Schema::table('refund_items', function (Blueprint $table) {
            $table->string('origin')
                ->default('manual')
                ->after('refund_id');
            $table->bigInteger('unit_amount_cents')
                ->nullable()
                ->after('qty');

            $table->foreignUlid('invoice_id')
                ->nullable()
                ->after('lease_charge_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['refund_id', 'origin'], 'refund_items_refund_origin_index');
            $table->index('invoice_id', 'refund_items_invoice_id_index');
            $table->index('invoice_item_id', 'refund_items_invoice_item_id_index');
            $table->index('lease_charge_id', 'refund_items_lease_charge_id_index');
            $table->index('fee_type_id', 'refund_items_fee_type_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('refund_items', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropIndex('refund_items_refund_origin_index');
            $table->dropIndex('refund_items_invoice_id_index');
            $table->dropIndex('refund_items_invoice_item_id_index');
            $table->dropIndex('refund_items_lease_charge_id_index');
            $table->dropIndex('refund_items_fee_type_id_index');
            $table->dropColumn([
                'origin',
                'unit_amount_cents',
                'invoice_id',
            ]);
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->dropIndex('refunds_status_created_at_index');
            $table->dropColumn([
                'deposit_collected_cents',
                'auto_deduction_cents',
                'manual_deduction_cents',
            ]);
        });
    }
};
