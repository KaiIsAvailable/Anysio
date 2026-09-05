<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lease_charges', function (Blueprint $table) {
            $table->string('frequency')->default('monthly')->after('charge_type');
            $table->date('next_billing_date')->nullable()->after('frequency');
            
            $table->index(['is_active', 'next_billing_date']);
        });
    }

    public function down(): void
    {
        Schema::table('lease_charges', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'next_billing_date']);
            $table->dropColumn(['frequency', 'next_billing_date']);
        });
    }
};