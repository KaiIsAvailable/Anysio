<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('tenants')->where(function ($query) {
            $query->whereNotNull('ssm_number')
                ->orWhereNotNull('company_address')
                ->orWhereNotNull('ssm_cert_path');
        })->exists()) {
            throw new \RuntimeException('Cannot drop tenant company columns while they contain data. Run tenants:migrate-companies first.');
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['ssm_number']);
            $table->dropColumn(['ssm_number', 'company_address', 'ssm_cert_path']);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('ssm_number')->nullable()->unique()->after('occupation');
            $table->text('company_address')->nullable()->after('ssm_number');
            $table->string('ssm_cert_path')->nullable()->after('company_address');
        });
    }
};
