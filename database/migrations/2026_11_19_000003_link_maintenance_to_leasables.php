<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance', function (Blueprint $table) {
            $table->ulid('lease_id')->nullable()->change();
            $table->nullableUlidMorphs('leasable');
        });
        DB::table('maintenance')->join('leases', 'leases.id', '=', 'maintenance.lease_id')
            ->select('maintenance.id', 'leases.leasable_type', 'leases.leasable_id')
            ->orderBy('maintenance.id')->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('maintenance')->where('id', $row->id)->update([
                        'leasable_type' => $row->leasable_type, 'leasable_id' => $row->leasable_id,
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (DB::table('maintenance')->whereNull('lease_id')->exists()) {
            throw new RuntimeException('Maintenance without a lease exists. Preserve these records before rollback.');
        }
        Schema::table('maintenance', function (Blueprint $table) {
            $table->dropMorphs('leasable');
            $table->ulid('lease_id')->nullable(false)->change();
        });
    }
};
