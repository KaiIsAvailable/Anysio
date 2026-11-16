<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utility_services', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        if (DB::table('utility_services')->whereNotNull('deleted_at')->exists()) {
            throw new RuntimeException('Deleted utility services exist. Reconcile them before rolling back deletion history.');
        }
        Schema::table('utility_services', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
