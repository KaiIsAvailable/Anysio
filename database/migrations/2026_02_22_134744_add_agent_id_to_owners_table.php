<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owners', function (Blueprint $blueprint) {
            $blueprint->foreignUlid('agent_id')
                      ->nullable() 
                      ->after('user_id')
                      ->constrained('users')
                      ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('owners', function (Blueprint $blueprint) {
            $blueprint->dropForeign(['agent_id']);
            $blueprint->dropColumn('agent_id');
        });
    }
};