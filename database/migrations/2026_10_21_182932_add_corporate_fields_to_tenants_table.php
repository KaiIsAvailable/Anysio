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
        Schema::table('tenants', function (Blueprint $table) {
            // 1. Add tenant classification type
            $table->string('tenant_type')->default('individual')->index()->after('owner_id');

            // 2. Make personal fields nullable (since corporate tenants won't have IC, passport, nationality, or gender)
            $table->string('nationality')->nullable()->change();
            $table->string('gender')->nullable()->change();

            // 3. Add Corporate / SSM Fields
            $table->string('ssm_number')->nullable()->unique()->after('occupation');
            $table->text('company_address')->nullable()->after('ssm_number');
            $table->string('ssm_cert_path')->nullable()->after('company_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Drop columns if rolling back
            $table->dropColumn([
                'tenant_type',
                'ssm_number',
                'company_address',
                'ssm_cert_path',
            ]);

            // Revert nationality and gender back to non-nullable if necessary 
            // (Note: Ensure no null records exist before rolling back, or it will fail)
            $table->string('nationality')->nullable(false)->change();
            $table->string('gender')->nullable(false)->change();
        });
    }
};