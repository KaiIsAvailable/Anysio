<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->text('address')->nullable()->after('gender');
            $table->string('postcode')->nullable()->after('address');
            $table->string('city')->nullable()->after('postcode');
            $table->string('state')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->dropColumn(['address', 'postcode', 'city', 'state']);
        });
    }
};