<?php
//THIS ACTIONS ARE DONE IN 08 November 2026 1:52 for all server online user
//only use 1 time to copy paste the comapny info
//Old format is the tenant table still have company info
//New format is we create the new table company to store user's company info
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MigrateTenantCompanies extends Command
{
    protected $signature = 'tenants:migrate-companies {--dry-run : Preview without saving changes}';

    protected $description = 'Copy corporate tenant companies, clear copied fields, and drop the old columns';

    public function handle(): int
    {
        if (!Schema::hasColumns('companies', ['ssm_cert_path']) ||
            !Schema::hasColumns('tenants', ['tenant_type', 'ssm_number', 'company_address', 'ssm_cert_path'])) {
            $this->error('Required columns are missing. Run the company certificate migration first; retain the tenant columns.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $skipped = 0;

        try {
            // One transaction ensures a failure leaves no partially copied records.
            DB::transaction(function () use ($dryRun, &$created, &$skipped): void {
                $tenants = DB::table('tenants')->where('tenant_type', 'corporate')
                    ->orderBy('id')->lockForUpdate()->get();

                foreach ($tenants as $tenant) {
                    $user = DB::table('users')->where('id', $tenant->user_id)->first();

                    if (!$user || trim((string) $user->name) === '') {
                        throw new RuntimeException("Tenant {$tenant->id} has no user with a company name.");
                    }

                    $data = [
                        'name' => $user->name,
                        'ssm_new_number' => $tenant->ssm_number,
                        'address' => $tenant->company_address,
                        'ssm_cert_path' => $tenant->ssm_cert_path,
                    ];

                    $linked = DB::table('companies')
                        ->join('company_user', 'companies.id', '=', 'company_user.company_id')
                        ->where('company_user.user_id', $tenant->user_id)
                        ->select('companies.*')->get();

                    if ($linked->isNotEmpty()) {
                        // A previous run may have committed the copy before schema cleanup failed.
                        if ($tenant->ssm_number === null && $tenant->company_address === null && $tenant->ssm_cert_path === null) {
                            $skipped++;
                            continue;
                        }

                        $matches = $linked->contains(function ($company) use ($data): bool {
                            foreach ($data as $field => $value) {
                                if ($company->{$field} !== $value) {
                                    return false;
                                }
                            }

                            return true;
                        });

                        if (!$matches) {
                            throw new RuntimeException("Tenant {$tenant->id} already has linked company data that differs. Review it before retrying.");
                        }

                        $skipped++;
                        continue;
                    }

                    $this->line(($dryRun ? 'Would copy tenant: ' : 'Copying tenant: ').$tenant->id);

                    if (!$dryRun) {
                        $companyId = (string) Str::ulid();
                        $now = now();
                        DB::table('companies')->insert(array_merge($data, [
                            'id' => $companyId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]));
                        DB::table('company_user')->insert([
                            'id' => (string) Str::ulid(),
                            'company_id' => $companyId,
                            'user_id' => $tenant->user_id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    $created++;
                }

                if (!$dryRun) {
                    DB::table('tenants')->where('tenant_type', 'corporate')->update([
                        'ssm_number' => null,
                        'company_address' => null,
                        'ssm_cert_path' => null,
                    ]);

                    if (DB::table('tenants')->where(function ($query) {
                        $query->whereNotNull('ssm_number')
                            ->orWhereNotNull('company_address')
                            ->orWhereNotNull('ssm_cert_path');
                    })->exists()) {
                        throw new RuntimeException('Other tenants still contain company data. Copy and clearing have been rolled back; review those records first.');
                    }
                }
            });
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            $this->error('Migration failed. No company changes from this run were committed.');

            return self::FAILURE;
        }

        $this->info(($dryRun ? 'Preview complete. Would create: ' : 'Migration complete. Created: ').$created."; already copied: {$skipped}.");
        if ($dryRun) {
            $this->info('No data or schema changed. A real run clears copied fields and runs the guarded column-drop migration.');

            return self::SUCCESS;
        }

        // Run DDL separately: MySQL schema changes cannot be rolled back with the copy.
        try {
            $result = $this->call('migrate', [
                '--path' => 'database/migrations/2026_11_05_000001_drop_company_fields_from_tenants_table.php',
                '--force' => true,
            ]);
        } catch (Throwable $exception) {
            $this->error('Company data was copied and old fields cleared, but column cleanup failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($result !== self::SUCCESS) {
            $this->error('Company data was copied and old fields cleared, but column cleanup failed. Retry the cleanup migration after resolving the error.');

            return self::FAILURE;
        }

        $this->info('Old tenant company columns dropped. Uploaded files were retained.');

        return self::SUCCESS;
    }
}
