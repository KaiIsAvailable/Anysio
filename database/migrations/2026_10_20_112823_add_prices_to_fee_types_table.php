<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_types', function (Blueprint $table) {
            // Nullable integer cents
            $table->unsignedInteger('original_price')->nullable()->after('category');
            $table->unsignedInteger('sell_price')->nullable()->after('original_price');
        });
    }

    public function down(): void
    {
        Schema::table('fee_types', function (Blueprint $table) {
            $table->dropColumn(['original_price', 'sell_price']);
        });
    }
};