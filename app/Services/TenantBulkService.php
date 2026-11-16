<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Lease;
use App\Models\Tenants;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantBulkService implements \App\Contracts\BulkSaveHandler
{
    public function __construct(private CompanyService $companies, private BankAccountService $banks, private FileService $files) {}

    public function revision(Tenants $tenant, bool $includeBank): string
    {
        return hash('sha256', json_encode([
            $tenant->getAttributes(), $tenant->user?->getAttributes(),
            $tenant->user?->companies->sortBy('id')->map->getAttributes()->values()->all(),
            $tenant->emergencyContacts->sortBy('id')->map->getAttributes()->values()->all(),
            $includeBank ? $tenant->user?->bankDetails->sortBy('id')->map->getAttributes()->values()->all() : [],
        ], JSON_THROW_ON_ERROR));
    }

    public function authorizeRows(array $rows, User $manager): array
    {
        $super = Gate::allows('super-admin');
        if ($manager->role === 'tenant' || (! $super && Gate::denies('tenant.tab'))) {
            throw new AuthorizationException;
        }
        $canBank = $super || Gate::allows('tenant.modify bank details');
        $allowedActions = [];
        foreach ($rows as $row) {
            $permission = ['create' => 'tenant.create', 'update' => 'tenant.edit', 'remove' => 'tenant.delete'][$row['action']];
            $allowedActions[$permission] ??= $super || Gate::allows($permission);
            if (! $allowedActions[$permission]) {
                throw new AuthorizationException;
            }
            if (! $canBank && collect(['bankName', 'bankCode', 'accountHolder', 'accountNumber'])->contains(fn ($field) => filled($row[$field] ?? null))) {
                throw new AuthorizationException;
            }
        }

        return [$super, $canBank];
    }

    public function validateRows(array $rows, User $manager, bool $lock = false): array
    {
        [$super, $canBank] = $this->authorizeRows($rows, $manager);
        $ids = collect($rows)->where('action', '!=', 'create')->pluck('id');
        $query = Tenants::whereIn('id', $ids)->where('status', 'active')->orderBy('id')->when($lock, fn ($query) => $query->lockForUpdate())->with([
            'user' => fn ($query) => $query->when($lock, fn ($lockedQuery) => $lockedQuery->lockForUpdate()),
            'user.companies' => fn ($query) => $query->orderBy('companies.id')->when($lock, fn ($query) => $query->lockForUpdate()),
            'emergencyContacts' => fn ($query) => $query->when($lock, fn ($lockedQuery) => $lockedQuery->lockForUpdate()),
        ]);
        if (! $super) {
            $query->where($manager->role === 'owner' ? 'owner_id' : 'created_by', $manager->id);
        }
        if ($canBank) {
            $query->with(['user.bankDetails' => fn ($query) => $query->wherePivot('is_default', true)->when($lock, fn ($query) => $query->lockForUpdate())]);
        }
        $existing = $query->get()->keyBy('id');
        if ($existing->count() !== $ids->count()) {
            throw new AuthorizationException;
        }

        foreach ($rows as $index => $row) {
            if ($row['action'] === 'create') {
                continue;
            }
            $tenant = $existing[$row['id']];
            if (! hash_equals($this->revision($tenant, $canBank), $row['version'])) {
                $this->fail($index, $row, 'version', 'This tenant changed since the grid loaded. Discard your draft and reload before editing it again.');
            }
            if ($row['action'] === 'update' && $row['type'] !== $tenant->tenant_type) {
                $this->fail($index, $row, 'type', 'The existing tenant type cannot be changed.');
            }
        }

        $removeIds = collect($rows)->where('action', 'remove')->pluck('id');
        $activeLeases = Lease::whereIn('tenant_id', $removeIds)->where('status', 'active')->when($lock, fn ($query) => $query->lockForUpdate())->pluck('tenant_id');
        foreach ($rows as $index => $row) {
            if ($row['action'] === 'remove' && $activeLeases->contains($row['id'])) {
                $this->fail($index, $row, 'action', 'Cannot remove a tenant with an active lease.');
            }
            if ($row['action'] === 'remove' || ! $canBank) {
                continue;
            }
            $hasBankInput = collect(['bankName', 'bankCode', 'accountHolder', 'accountNumber'])->contains(fn ($field) => filled($row[$field] ?? null));
            $bank = isset($existing[$row['id'] ?? '']) ? $existing[$row['id']]->user->bankDetails->first() : null;
            if ($hasBankInput && (blank($row['bankName'] ?? null) || blank($row['accountHolder'] ?? null) || (! $bank && blank($row['accountNumber'] ?? null)))) {
                $this->fail($index, $row, 'bankName', 'Provide bank name, account holder and account number together, or leave bank details empty.');
            }
        }
        $this->checkDuplicates($rows, $existing, $manager);

        return [$existing, $canBank];
    }

    public function save(array $rows, User $manager, array $uploads = [], ?callable $onProgress = null, ?callable $beforeCommit = null): array
    {
        $newFiles = [];
        $committed = false;
        try {
            return DB::transaction(function () use ($rows, $manager, $uploads, &$newFiles, &$committed, $onProgress, $beforeCommit): array {
                DB::afterCommit(function () use (&$committed): void {
                    $committed = true;
                });
                [$existing, $canBank] = $this->validateRows($rows, $manager, true);

                $counts = ['created' => 0, 'updated' => 0, 'removed' => 0];
                $processed = 0;
                foreach ($rows as $row) {
                    if ($row['action'] === 'remove') {
                        $existing[$row['id']]->update(['status' => 'inactive']);
                        $counts['removed']++;
                        if ($onProgress) {
                            $onProgress(++$processed);
                        }

                        continue;
                    }
                    $tenant = $row['action'] === 'create' ? null : $existing[$row['id']];
                    $user = $tenant?->user ?? new User(['role' => 'tenant', 'password' => Str::random(32)]);
                    $email = $row['email'] ?? null;
                    if (blank($email)) {
                        $email = $user->exists ? $user->email : 'tenant_'.Str::lower((string) Str::ulid()).'@anysio.local';
                    }
                    if ($user->exists && $email !== $user->email) {
                        $user->email_verified_at = null;
                    }
                    $user->fill(['name' => $row['name'], 'email' => $email]);
                    if (! $user->exists || $user->isDirty()) {
                        $user->save();
                    }

                    $personal = $row['type'] === 'individual';
                    $rowUploads = $uploads[$row['uploadKey'] ?? ''] ?? [];
                    $data = ['phone' => $row['phone']];
                    if ($personal && isset($rowUploads['icPhoto'])) {
                        $oldPath = $tenant?->ic_photo_path;
                        $path = $this->files->upload($rowUploads['icPhoto'], $user->id, 'tenant_ic');
                        if (! $path) {
                            throw new \RuntimeException('Unable to upload the identity document.');
                        }
                        $newFiles[] = ['local', $path];
                        $data['ic_photo_path'] = $path;
                        if ($oldPath) {
                            DB::afterCommit(fn () => $this->files->delete($oldPath));
                        }
                    }
                    foreach (['ic' => 'ic_number', 'passport' => 'passport', 'nationality' => 'nationality', 'gender' => 'gender', 'occupation' => 'occupation'] as $input => $column) {
                        $data[$column] = $personal && filled($row[$input] ?? null) ? $row[$input] : null;
                    }
                    if (! $tenant) {
                        $tenant = Tenants::create($data + ['user_id' => $user->id, 'created_by' => $manager->id, 'owner_id' => $manager->role === 'owner' ? $manager->id : null, 'tenant_type' => $row['type'], 'status' => 'active']);
                        $user->setRelation('companies', collect());
                        $tenant->setRelation('emergencyContacts', collect());
                    } else {
                        $tenant->fill($data);
                        if ($tenant->isDirty()) {
                            $tenant->save();
                        }
                    }
                    if (! $personal) {
                        $companyData = [];
                        foreach (['companyName' => 'company_name', 'ssmOld' => 'ssm_old_number', 'ssmNew' => 'ssm_new_number', 'companyPhone' => 'company_phone', 'companyEmail' => 'company_email', 'companyAddress' => 'company_address'] as $input => $column) {
                            $companyData[$column] = $row[$input] ?? null;
                        }
                        $company = $user->companies->first();
                        $attributes = ['name' => $companyData['company_name'], 'ssm_old_number' => $companyData['ssm_old_number'], 'ssm_new_number' => $companyData['ssm_new_number'], 'phone' => $companyData['company_phone'], 'email' => $companyData['company_email'], 'address' => $companyData['company_address']];
                        if (! $company || isset($rowUploads['companyLogo']) || isset($rowUploads['ssmCertificate']) || collect($attributes)->contains(fn ($value, $field) => ($company->$field ?? '') !== ($value ?? ''))) {
                            $this->companies->saveForTenant($user, $companyData, $rowUploads['ssmCertificate'] ?? null, $rowUploads['companyLogo'] ?? null, function ($disk, $path) use (&$newFiles): void {
                                $newFiles[] = [$disk, $path];
                            });
                        }
                    }
                    if ($canBank && filled($row['bankName'] ?? null)) {
                        $bank = $user->relationLoaded('bankDetails') ? $user->bankDetails->first() : null;
                        $bankData = ['bank_name' => $row['bankName'], 'bank_code' => $row['bankCode'] ?? null, 'account_holder_name' => $row['accountHolder']];
                        if (! $bank || collect($bankData)->contains(fn ($value, $field) => ($bank->$field ?? '') !== ($value ?? '')) || (filled($row['accountNumber'] ?? null) && $row['accountNumber'] !== $bank->account_number)) {
                            $this->banks->saveForUser($user, $bankData + ['account_number' => $row['accountNumber'] ?? null]);
                        }
                    }
                    $contacts = array_map(fn ($contact) => ['name' => $contact['name'], 'phone' => $contact['phone'], 'relationship' => $contact['relationship'] ?? ''], $row['contacts']);
                    $oldContacts = $tenant->emergencyContacts->map(fn ($contact) => ['name' => $contact->name, 'phone' => $contact->phone, 'relationship' => $contact->relationship ?? ''])->all();
                    if ($oldContacts !== $contacts) {
                        foreach ($tenant->emergencyContacts as $contact) {
                            $contact->delete();
                        }
                        $tenant->emergencyContacts()->createMany($contacts);
                    }
                    $counts[$row['action'] === 'create' ? 'created' : 'updated']++;
                    if ($onProgress) {
                        $onProgress(++$processed);
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

    private function checkDuplicates(array $rows, $existing, User $manager): void
    {
        $identityGroups = [];
        foreach ($rows as $index => $row) {
            $tenant = $existing[$row['id'] ?? ''] ?? null;
            $scope = $tenant ? $tenant->created_by : $manager->id;
            $key = $scope ?? '';
            $identityGroups[$key]['scope'] = $scope;
            $identityGroups[$key]['rows'][$index] = $row;
        }
        foreach ($identityGroups as $group) {
            app(BulkDuplicateValidator::class)->validate($group['rows'], ['ic' => [Tenants::class, 'ic_number']],
                fn (array $row) => ($existing[$row['id'] ?? ''] ?? null)?->id,
                fn ($query) => $query->where('created_by', $group['scope'])->where('status', 'active'));
        }
        $checks = [
            'email' => [User::class, 'email'],
            'passport' => [Tenants::class, 'passport'], 'ssmNew' => [Company::class, 'ssm_new_number'],
        ];
        app(BulkDuplicateValidator::class)->validate($rows, $checks, function (array $row, string $field) use ($existing) {
            $tenant = $existing[$row['id'] ?? ''] ?? null;

            return match ($field) {
                'email' => $tenant?->user_id,
                'ssmNew' => $tenant?->user?->companies->first()?->id,
                default => $tenant?->id,
            };
        });
        $companyEdits = [];
        foreach ($rows as $index => $row) {
            if ($row['action'] !== 'update' || $row['type'] !== 'corporate') {
                continue;
            }
            $companyId = $existing[$row['id']]->user->companies->first()?->id;
            if (! $companyId) {
                continue;
            }
            $values = array_intersect_key($row, array_flip(['companyName', 'ssmOld', 'ssmNew', 'companyPhone', 'companyEmail', 'companyAddress']));
            if (isset($companyEdits[$companyId]) && $companyEdits[$companyId] !== $values) {
                $this->fail($index, $row, 'companyName', 'The same company has conflicting edits in this batch.');
            }
            $companyEdits[$companyId] = $values;
        }
    }

    private function fail(int $index, array $row, string $field, string $message): never
    {
        throw ValidationException::withMessages(["rows.{$index}.{$field}" => 'Row '.($row['rowNumber'] ?? $index + 1).': '.$message]);
    }
}
