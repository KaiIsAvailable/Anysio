<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Additive only: legacy rows, asset columns, costs and photographs are untouched.
        Schema::table('maintenance', function (Blueprint $table) {
            $table->string('maintenance_number')->nullable()->unique();
            $table->string('source_type')->nullable()->index();
            $table->foreignUlid('ticket_id')->nullable()->constrained('tickets')->restrictOnDelete();
            $table->string('management_user_id', 26)->nullable();
            $table->text('location_snapshot')->nullable();
            $table->string('location_detail')->nullable();
            $table->string('contractor_name')->nullable();
            $table->string('contractor_phone')->nullable();
            $table->string('created_by', 26)->nullable();
            $table->string('assigned_by', 26)->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->string('completed_by', 26)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('version')->default(0);
        });
        Schema::create('maintenance_attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('maintenance_id')->constrained('maintenance')->restrictOnDelete();
            $table->string('purpose');
            $table->string('path');
            $table->string('filename');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->string('source_ticket_msg_id', 26)->nullable();
            $table->string('uploaded_by', 26);
            $table->timestamps();
        });
        Schema::create('maintenance_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('maintenance_id')->constrained('maintenance')->restrictOnDelete();
            $table->foreignUlid('ticket_id')->constrained('tickets')->restrictOnDelete();
            $table->uuid('request_key');
            $table->string('payload_hash', 64);
            $table->string('action');
            $table->string('actor_id', 26);
            $table->json('changes');
            $table->timestamps();
            $table->unique(['ticket_id', 'request_key']);
        });
        Schema::table('ticket_msgs', function (Blueprint $table) {
            $table->foreignUlid('maintenance_id')->nullable()->constrained('maintenance')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Prevent rollback from silently discarding operational records or evidence.
        if (\Illuminate\Support\Facades\DB::table('maintenance')->whereNotNull('source_type')->exists()) {
            throw new \RuntimeException('Maintenance work orders exist. Archive/review them before schema rollback.');
        }
        Schema::table('ticket_msgs', fn (Blueprint $t) => $t->dropConstrainedForeignId('maintenance_id'));
        Schema::dropIfExists('maintenance_events');
        Schema::dropIfExists('maintenance_attachments');
        Schema::table('maintenance', function (Blueprint $t) {
            $t->dropConstrainedForeignId('ticket_id');
            $t->dropColumn(['maintenance_number', 'source_type', 'management_user_id', 'location_snapshot', 'location_detail',
                'contractor_name', 'contractor_phone', 'created_by', 'assigned_by', 'assigned_at', 'completed_by', 'completed_at', 'version']);
        });
    }
};
