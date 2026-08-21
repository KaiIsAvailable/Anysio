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
            // 1. Filter out rows without full names first
            $validRows = $rows->filter(function ($row) {
                return !empty($row['full_name']);
            });

            if ($validRows->isEmpty()) {
                return;
            }

            // 2. Extract all unique identifiers from the sheet to check existing records in bulk
            $icNumbers = $validRows->pluck('ic_number')->filter()->map(fn($v) => trim($v))->toArray();
            $passports = $validRows->pluck('passport_number')->filter()->map(fn($v) => trim($v))->toArray();

            // Fetch all potential existing tenants in ONE single query
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
                ->keyBy(function ($tenant) {
                    return $tenant->ic_number ?: $tenant->passport;
                });

            // Separate rows into updates and creates
            foreach ($validRows as $row) {
                $icNumber = !empty(trim($row['ic_number'] ?? '')) ? trim($row['ic_number']) : null;
                $passport = !empty(trim($row['passport_number'] ?? '')) ? trim($row['passport_number']) : null;
                $phone    = !empty(trim($row['phone_number'] ?? '')) ? trim($row['phone_number']) : '-';
                
                $lookupKey = $icNumber ?: $passport;
                $existingTenant = $lookupKey ? $existingTenants->get($lookupKey) : null;

                if ($existingTenant) {
                    // UPDATE directly (still efficient if individual, but you can also batch updates if needed)
                    $existingTenant->update([
                        'ic_number'   => $icNumber ?? $existingTenant->ic_number,
                        'passport'    => $passport ?? $existingTenant->passport,
                        'phone'       => $phone,
                        'nationality' => $row['nationality'] ?? $existingTenant->nationality, 
                        'gender'      => $row['gender'] ?? $existingTenant->gender,
                        'occupation'  => $row['occupation'] ?? $existingTenant->occupation,
                    ]);

                    if ($existingTenant->user) {
                        $existingTenant->user->update(['name' => $row['full_name']]);
                    }
                } else {
                    // Prepare data for BULK INSERT users
                    // Note: Bulk insert doesn't generate IDs automatically in standard Eloquent, 
                    // so we generate UUIDs or handle them cleanly, OR use chunked inserts.
                    $email = $row['email_address'] ?? ('tenant_' . time() . '_' . Str::random(5) . '@anysio.local');
                    
                    // CREATE new User account via Eloquent (safely generates ULID)
                    $user = User::create([
                        'name'       => $row['full_name'],
                        'email'      => $email,
                        'password'   => Hash::make(Str::random(10)),
                        'role'       => 'tenant',
                    ]);
                    $importedData['users'][] = $user->id; // $user->id will correctly be the generated ULID string

                    // CREATE new Tenant record via Eloquent (safely generates ULID)
                    $tenant = Tenants::create([
                        'user_id'     => $user->id,
                        'created_by'  => $this->createdBy,
                        'phone'       => $phone,
                        'ic_number'   => $icNumber,
                        'passport'    => $passport,
                        'nationality' => $row['nationality'] ?? '', 
                        'gender'      => $row['gender'] ?? '',
                        'occupation'  => $row['occupation'] ?? null,
                    ]);
                    $importedData['tenants'][] = $tenant->id; // $tenant->id will correctly be the generated ULID string
                }
            }

            // Save session tracking data
            Storage::disk('local')->put('imports/' . $this->importSessionKey, json_encode($importedData));
        });
    }
}