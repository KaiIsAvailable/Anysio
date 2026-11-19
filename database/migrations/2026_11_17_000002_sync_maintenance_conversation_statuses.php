<?php

use App\Models\Ticket;
use App\Services\MaintenanceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        $service = app(MaintenanceService::class);
        if (!$service->ready()) return;

        if (DB::table('maintenance')->whereNotNull('ticket_id')->whereNotNull('source_type')
            ->select('ticket_id', 'source_type')->groupBy('ticket_id', 'source_type')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Multiple work orders are linked to a conversation. Review existing records before enforcing one work order per conversation.');
        }
        if (!Schema::hasIndex('maintenance', 'maintenance_ticket_source_unique')) {
            Schema::table('maintenance', fn (Blueprint $table) => $table->unique(['ticket_id', 'source_type'], 'maintenance_ticket_source_unique'));
        }

        Ticket::whereHas('maintenanceOrder')->select('id')->chunkById(100, function ($tickets) use ($service) {
            foreach ($tickets as $ticket) {
                DB::transaction(function () use ($ticket, $service) {
                    $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->first();
                    if ($locked) $service->syncConversationStatus($locked);
                });
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasIndex('maintenance', 'maintenance_ticket_source_unique')) {
            Schema::table('maintenance', fn (Blueprint $table) => $table->dropUnique('maintenance_ticket_source_unique'));
        }
        // Conversation history must not be reopened when rolling back code.
    }
};
