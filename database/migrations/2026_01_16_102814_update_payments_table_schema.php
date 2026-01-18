<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('amount_type')->after('payment_type'); // maintenance, water, electricity, rent
            $table->dropForeign(['approved_by']);
            // We want approved_by to link to users table now, and satisfy "Take login's user id"
            // We need to drop the constraint first.
            // Note: existing data might violate new constraint if we had any. Assuming empty or compatible.
        });

        Schema::table('payments', function (Blueprint $table) {
             $table->foreignUlid('approved_by')->change()->nullable()->constrained('users')->nullOnDelete();
             $table->string('status')->default('paid')->change(); // Default status 'paid'
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('amount_type');
            $table->dropForeign(['approved_by']);
            $table->string('status')->default('pending')->change();
        });

        Schema::table('payments', function (Blueprint $table) {
             $table->foreignUlid('approved_by')->change()->nullable()->constrained('owners')->nullOnDelete();
        });
    }
};
