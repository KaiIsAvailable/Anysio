<?php

namespace App\Services;

use App\Contracts\BulkSaveHandler;
use App\Models\Company;
use App\Models\Owners;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OwnerBulkService implements BulkSaveHandler
{
    public function __construct(private CompanyService $companies, private BankAccountService $banks) {}

    public function revision(Owners $owner): string
    {
        return hash('sha256', json_encode([
            $owner->getAttributes(), $owner->user?->getAttributes(),
            $owner->user?->companies->sortBy('id')->map->getAttributes()->values()->all(),
            $owner->user?->bankDetails->sortBy('id')->map->getAttributes()->values()->all(),
        ], JSON_THROW_ON_ERROR));
    }

    public function authorizeRows(array $rows, User $manager): array
    {
        $super = Gate::allows('super-admin');
        if (! $super && Gate::denies('owner.tab')) {
            throw new AuthorizationException;
        }
        $permissions = [];
        foreach ($rows as $row) {
            $permission = ['create' => 'owner.create', 'update' => 'owner.edit', 'remove' => 'owner.delete'][$row['action']];
            $permissions[$permission] ??= $super || Gate::allows($permission);
            if (! $permissions[$permission]) {
                throw new AuthorizationException;
            }
        }

        return [$super];
    }

    public function validateRows(array $rows, User $manager, bool $lock = false): array
    {
        $this->authorizeRows($rows, $manager);
        $ids = collect($rows)->where('action', '!=', 'create')->pluck('id');
        if ($lock) {
            $accountIds = Owners::forManagement($manager)->whereIn('id', $ids)->pluck('agent_id')->push($manager->id)->all();
            app(OwnerIdentityValidator::class)->lockAccounts($accountIds);
        }
        $existing = Owners::forManagement($manager)->whereIn('id', $ids)
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))->orderBy('id')
            ->when($lock, fn ($q) => $q->lockForUpdate())->with([
                'user' => fn ($q) => $q->when($lock, fn ($q) => $q->lockForUpdate()),
                'user.companies' => fn ($q) => $q->orderBy('companies.id')->when($lock, fn ($q) => $q->lockForUpdate()),
                'user.bankDetails' => fn ($q) => $q->wherePivot('is_default', true)->orderBy('bank_details.id')->when($lock, fn ($q) => $q->lockForUpdate()),
            ])->get()->keyBy('id');
        if ($existing->count() !== $ids->count()) {
            throw new AuthorizationException;
        }

        app(OwnerIdentityValidator::class)->validateRows($rows, $existing, $manager->id);

        $companyEdits = [];
        foreach ($rows as $index => $row) {
            $owner = $existing[$row['id'] ?? ''] ?? null;
            if ($owner && ! hash_equals($this->revision($owner), $row['version'])) {
                $this->fail($index, $row, 'version', 'This owner changed since the grid loaded. Reload before editing it again.');
            }
            if ($row['action'] === 'remove') {
                continue;
            }
            $hasCompany = collect(['companyName', 'ssmOld', 'ssmNew', 'companyPhone', 'companyEmail', 'companyAddress'])
                ->contains(fn ($field) => filled($row[$field] ?? null)) || ! empty($row['fileFields']);
            if ($hasCompany && blank($row['companyName'] ?? null)) {
                $this->fail($index, $row, 'companyName', 'Enter a company name when adding company details.');
            }
            $hasBank = collect(['bankName', 'bankCode', 'accountHolder', 'accountNumber'])
                ->contains(fn ($field) => filled($row[$field] ?? null));
            $bank = $owner?->user?->bankDetails->first();
            if ($hasBank && (blank($row['bankName'] ?? null) || blank($row['accountHolder'] ?? null) || (! $bank && blank($row['accountNumber'] ?? null)))) {
                $this->fail($index, $row, 'bankName', 'Provide bank name, account holder and account number together, or leave bank details empty.');
            }
            $companyId = $owner?->user?->companies->first()?->id;
            if ($companyId && filled($row['companyName'] ?? null)) {
                $values = array_intersect_key($row, array_flip(['companyName', 'ssmOld', 'ssmNew', 'companyPhone', 'companyEmail', 'companyAddress']));
                if (isset($companyEdits[$companyId]) && $companyEdits[$companyId] !== $values) {
                    $this->fail($index, $row, 'companyName', 'The same company has conflicting edits in this batch.');
                }
                $companyEdits[$companyId] = $values;
            }
        }
        app(BulkDuplicateValidator::class)->validate($rows, [
            'email' => [User::class, 'email'], 'ssmNew' => [Company::class, 'ssm_new_number'],
        ], function (array $row, string $field) use ($existing) {
            $owner = $existing[$row['id'] ?? ''] ?? null;

            return $field === 'email' ? $owner?->user_id : $owner?->user?->companies->first()?->id;
        });

        return [$existing];
    }

    public function save(array $rows, User $manager, array $uploads = [], ?callable $onProgress = null, ?callable $beforeCommit = null): array
    {
        $newFiles = [];
        $committed = false;
        try {
            return DB::transaction(function () use ($rows, $manager, $uploads, $onProgress, $beforeCommit, &$newFiles, &$committed) {
                DB::afterCommit(function () use (&$committed): void {
                    $committed = true;
                });
                [$existing] = $this->validateRows($rows, $manager, true);
                $counts = ['created' => 0, 'updated' => 0, 'removed' => 0];
                foreach ($rows as $index => $row) {
                    if ($row['action'] === 'remove') {
                        $existing[$row['id']]->user->update(['status' => 'inactive']);
                        $counts['removed']++;
                    } else {
                        $owner = $row['action'] === 'create' ? null : $existing[$row['id']];
                        $user = $owner?->user ?? new User(['role' => 'owner', 'status' => 'active', 'password' => Str::random(32)]);
                        $email = filled($row['email'] ?? null) ? $row['email']
                            : ($user->exists ? $user->email : 'owner_'.Str::lower((string) Str::ulid()).'@anysio.local');
                        if ($user->exists && $user->email !== $email) {
                            $user->email_verified_at = null;
                        }
                        $user->fill(['name' => $row['name'], 'email' => $email]);
                        if (! $user->exists || $user->isDirty()) {
                            $user->save();
                        }
                        $data = [];
                        foreach (['phone' => 'phone', 'ic' => 'ic_number', 'gender' => 'gender', 'address' => 'address', 'postcode' => 'postcode', 'city' => 'city', 'state' => 'state'] as $input => $column) {
                            if (array_key_exists($input, $row)) {
                                $data[$column] = $row[$input];
                            }
                        }
                        if (! $owner) {
                            $owner = Owners::create($data + ['user_id' => $user->id, 'agent_id' => $manager->id]);
                            $user->setRelation('companies', collect());
                        } else {
                            $owner->fill($data);
                            if ($owner->isDirty()) {
                                $owner->save();
                            }
                        }
                        if (filled($row['companyName'] ?? null)) {
                            $companyData = [];
                            foreach (['companyName' => 'company_name', 'ssmOld' => 'ssm_old_number', 'ssmNew' => 'ssm_new_number', 'companyPhone' => 'company_phone', 'companyEmail' => 'company_email', 'companyAddress' => 'company_address'] as $input => $column) {
                                $companyData[$column] = $row[$input] ?? null;
                            }
                            $files = $uploads[$row['uploadKey'] ?? ''] ?? [];
                            $this->companies->saveProfileForUser($user, $companyData, $files['ssmCertificate'] ?? null, $files['companyLogo'] ?? null,
                                function ($disk, $path) use (&$newFiles): void {
                                    $newFiles[] = [$disk, $path];
                                }, 'owner_ssm');
                        }
                        if (filled($row['bankName'] ?? null)) {
                            $this->banks->saveForUser($user, [
                                'bank_name' => $row['bankName'], 'bank_code' => $row['bankCode'] ?? null,
                                'account_holder_name' => $row['accountHolder'], 'account_number' => $row['accountNumber'] ?? null,
                            ]);
                        }
                        $counts[$row['action'] === 'create' ? 'created' : 'updated']++;
                    }
                    if ($onProgress) {
                        $onProgress($index + 1);
                    }
                }
                if ($beforeCommit) {
                    $beforeCommit($counts);
                }

                return $counts;
            });
        } catch (\Throwable $exception) {
            if (! $committed) {
                foreach ($newFiles as [$disk, $path]) {
                    Storage::disk($disk)->delete($path);
                }
            }
            throw $exception;
        }
    }

    private function fail(int $index, array $row, string $field, string $message): never
    {
        throw ValidationException::withMessages(["rows.{$index}.{$field}" => 'Row '.($row['rowNumber'] ?? $index + 1).': '.$message]);
    }
}
