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
        Schema::table('ticket_msgs', function (Blueprint $table) {
            $table->string('status')->nullable()->after('message');
            $table->timestamp('read_at')->nullable()->after('status'); // 💡 When it was read
            $table->string('read_by')->nullable()->after('read_at');   // 💡 Who read it (e.g., admin ID or user type)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_msgs', function (Blueprint $table) {
            $table->dropColumn(['status', 'read_at', 'read_by']);
        });
    }
};