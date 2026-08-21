<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Storage;
use App\Models\Tenants;
use App\Models\EmergencyContact;
use Illuminate\Support\Str;

class EmergencyContactSheetImport implements ToCollection, WithHeadingRow
{
    protected $importSessionKey;

    public function __construct($importSessionKey)
    {
        $this->importSessionKey = $importSessionKey;
    }

    public function collection(Collection $rows)
    {
        // 1. Load session tracking data
        $sessionPath = 'imports/' . $this->importSessionKey;
        $importedData = Storage::disk('local')->exists($sessionPath) 
            ? json_decode(Storage::disk('local')->get($sessionPath), true) 
            : ['users' => [], 'tenants' => [], 'emergency_contacts' => []];

        $validRows = $rows->filter(function ($row) {
            return !empty(trim($row['tenant_name'] ?? '')) && !empty(trim($row['emergency_name'] ?? ''));
        });

        if ($validRows->isEmpty()) {
            return;
        }

        // 2. Collect all unique tenant names to fetch them in a SINGLE query upfront
        $tenantNames = $validRows->pluck('tenant_name')->map(fn($name) => trim($name))->unique()->toArray();

        $tenantsMap = Tenants::whereHas('user', function ($query) use ($tenantNames) {
                $query->whereIn('name', $tenantNames);
            })
            ->with('user')
            ->get()
            ->keyBy(function ($tenant) {
                return $tenant->user ? trim($tenant->user->name) : null;
            });

        $contactsToInsert = [];
        $now = now();

        // 3. Process records entirely in memory
        foreach ($validRows as $row) {
            $tenantName = trim($row['tenant_name']);
            $tenant = $tenantsMap->get($tenantName);

            if ($tenant) {
                $contactId = (string) Str::ulid();
                
                $contactsToInsert[] = [
                    'id'           => $contactId,
                    'tenant_id'    => $tenant->id,
                    'name'         => trim($row['emergency_name']),
                    'phone'        => trim($row['phone_number'] ?? '-'),
                    'relationship' => trim($row['relationship'] ?? '-'),
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];

                // Track ID for rollback/revert features
                $importedData['emergency_contacts'][] = $contactId;
            }
        }

        // 4. BULK INSERT all emergency contacts in one single database operation
        if (!empty($contactsToInsert)) {
            EmergencyContact::insert($contactsToInsert);
        }

        // 5. Save back the tracking session file
        Storage::disk('local')->put($sessionPath, json_encode($importedData));
    }
}