<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Tenants;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TenantSheetImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    protected $createdBy;
    public $importSessionKey;

    public function __construct($createdBy)
    {
        $this->createdBy = $createdBy;
        $this->importSessionKey = 'import_session_' . time() . '.json';
    }

    public function collection(Collection $rows)
    {
        $importedData = [
            'users' => [],
            'tenants' => [],
            'emergency_contacts' => []
        ];

        DB::transaction(function () use ($rows, &$importedData) {
            $validRows = $rows->filter(function ($row) {
                return !empty(trim($row['full_name'] ?? ''));
            });

            if ($validRows->isEmpty()) {
                return;
            }

            // 1. Extract unique identifiers to fetch existing records in ONE single query
            $icNumbers = $validRows->pluck('ic_number')->filter()->map(fn($v) => trim($v))->toArray();
            $passports = $validRows->pluck('passport_number')->filter()->map(fn($v) => trim($v))->toArray();

            $existingTenants = Tenants::where('created_by', $this->createdBy)
                ->where(function ($query) use ($icNumbers, $passports) {
                    if (!empty($icNumbers)) {
                        $query->orWhereIn('ic_number', $icNumbers);
                    }
                    if (!empty($passports)) {
                        $query->orWhereIn('passport', $passports);
                    }
                })
                ->with('user')
                ->get()
                ->keyBy(fn($tenant) => $tenant->ic_number ?: $tenant->passport);

            $usersToInsert = [];
            $tenantsToInsert = [];
            $now = now();

            // 2. Separate rows into memory arrays for bulk handling
            foreach ($validRows as $row) {
                $icNumber = !empty(trim($row['ic_number'] ?? '')) ? trim($row['ic_number']) : null;
                $passport = !empty(trim($row['passport_number'] ?? '')) ? trim($row['passport_number']) : null;
                $phone    = !empty(trim($row['phone_number'] ?? '')) ? trim($row['phone_number']) : '-';
                
                $lookupKey = $icNumber ?: $passport;
                $existingTenant = $lookupKey ? $existingTenants->get($lookupKey) : null;

                if ($existingTenant) {
                    // Fast update for existing records
                    $existingTenant->update([
                        'ic_number'   => $icNumber ?? $existingTenant->ic_number,
                        'passport'    => $passport ?? $existingTenant->passport,
                        'phone'       => $phone,
                        'nationality' => $row['nationality'] ?? $existingTenant->nationality, 
                        'gender'      => $row['gender'] ?? $existingTenant->gender,
                        'occupation'  => $row['occupation'] ?? $existingTenant->occupation,
                    ]);

                    if ($existingTenant->user) {
                        $existingTenant->user->update(['name' => trim($row['full_name'])]);
                    }
                } else {
                    // Generate ULIDs natively in PHP for bulk mapping
                    $userId = (string) Str::ulid();
                    $tenantId = (string) Str::ulid();
                    $email = $row['email_address'] ?? ('tenant_' . time() . '_' . Str::random(5) . '@anysio.local');

                    // Queue User for bulk insert
                    $usersToInsert[] = [
                        'id'         => $userId,
                        'name'       => trim($row['full_name']),
                        'email'      => $email,
                        'password'   => Hash::make(Str::random(10)),
                        'role'       => 'tenant',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    // Queue Tenant for bulk insert
                    $tenantsToInsert[] = [
                        'id'          => $tenantId,
                        'user_id'     => $userId,
                        'created_by'  => $this->createdBy,
                        'phone'       => $phone,
                        'ic_number'   => $icNumber,
                        'passport'    => $passport,
                        'nationality' => $row['nationality'] ?? '', 
                        'gender'      => $row['gender'] ?? '',
                        'occupation'  => $row['occupation'] ?? null,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];

                    $importedData['users'][] = $userId;
                    $importedData['tenants'][] = $tenantId;
                }
            }

            // 3. Perform Bulk Inserts (1 query for users, 1 query for tenants)
            if (!empty($usersToInsert)) {
                User::insert($usersToInsert);
            }

            if (!empty($tenantsToInsert)) {
                Tenants::insert($tenantsToInsert);
            }

            // Save tracking session file
            Storage::disk('local')->put('imports/' . $this->importSessionKey, json_encode($importedData));
        });
    }
}