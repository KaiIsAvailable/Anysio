<?php

namespace Tests\Performance;

use App\Models\Tenants;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\TenantBulkSaveTest;

// Run explicitly; this benchmark intentionally uses production-strength password hashing.
class TenantBulkSaveBenchmarkTest extends TenantBulkSaveTest
{
    public function test_measure_three_hundred_tenants_in_isolated_database(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        auth()->user()->update(['email_verified_at' => now()]);

        $rows = [];
        for ($index = 0; $index < 300; $index++) {
            $rows[] = [
                'action' => 'create', 'id' => null, 'version' => null, 'rowNumber' => $index + 1,
                'name' => 'Benchmark Tenant '.($index + 1), 'email' => '', 'type' => 'individual',
                'phone' => '0123456789', 'ic' => '900101'.str_pad((string) $index, 6, '0', STR_PAD_LEFT),
                'passport' => '', 'nationality' => 'MALAYSIAN', 'gender' => 'Male', 'occupation' => '',
                'contacts' => [['name' => 'Benchmark Contact', 'phone' => '0199999999', 'relationship' => 'Parent']],
            ];
        }

        \Illuminate\Support\Facades\Queue::fake();
        $start = hrtime(true);
        $this->post(route('admin.tenants.bulk-save'), ['rows_json' => json_encode($rows), 'page' => 1])
            ->assertSessionHasNoErrors();
        $queueSeconds = (hrtime(true) - $start) / 1e9;
        $this->assertSame(0, Tenants::count());
        $operation = \App\Models\TenantBulkOperation::firstOrFail();
        fwrite(STDOUT, sprintf("\n300-row request queued in %.3f seconds.\n", $queueSeconds));

        config(['hashing.bcrypt.rounds' => 12]);
        app('hash')->driver()->setRounds(12);
        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });
        $start = hrtime(true);
        app(\App\Services\TenantBulkOperationService::class)->process($operation->id);
        $processingSeconds = (hrtime(true) - $start) / 1e9;
        $this->assertSame('succeeded', $operation->fresh()->status, json_encode($operation->fresh()->errors));
        $this->assertSame(300, Tenants::count());
        $tenantUser = User::where('role', 'tenant')->firstOrFail();
        $this->assertSame(12, password_get_info($tenantUser->getRawOriginal('password'))['options']['cost']);
        $result = ['database' => 'SQLite in-memory', 'bcrypt_rounds' => 12, 'uploads' => false,
            'queued_rows' => 300, 'queue_request_seconds' => round($queueSeconds, 3),
            'processing_seconds' => round($processingSeconds, 3), 'processing_queries' => $queryCount];
        file_put_contents(storage_path('framework/tenant-bulk-benchmark.json'), json_encode($result, JSON_PRETTY_PRINT));
        fwrite(STDOUT, sprintf("300 tenants processed in %.3f seconds.\n", $processingSeconds));
    }
}
