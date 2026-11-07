<?php

use App\Services\AccountPermissionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('account_permissions')->nullable();
        });

        // Preserve existing accounts and staff access on rollout. Subsequent
        // changes apply only to the account selected by the system administrator.
        DB::table('users')->whereIn('role', ['agentAdmin', 'ownerAdmin'])
            ->update(['account_permissions' => json_encode(AccountPermissionService::names(), JSON_THROW_ON_ERROR)]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('account_permissions'));
    }
};
