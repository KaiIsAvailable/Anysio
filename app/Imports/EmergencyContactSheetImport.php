<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Storage;
use App\Models\Tenants;
use App\Models\EmergencyContact;

class EmergencyContactSheetImport implements ToCollection, WithHeadingRow
{
    protected $importSessionKey;

    public function __construct($importSessionKey)
    {
        $this->importSessionKey = $importSessionKey;
    }

    public function collection(Collection $rows)
    {
        // Load the existing tracking session data created by the Tenant sheet
        $sessionPath = 'imports/' . $this->importSessionKey;
        $importedData = Storage::disk('local')->exists($sessionPath) 
            ? json_decode(Storage::disk('local')->get($sessionPath), true) 
            : ['users' => [], 'tenants' => [], 'emergency_contacts' => []];

        foreach ($rows as $row) {
            $tenantName    = trim($row['tenant_name'] ?? '');
            $emergencyName = trim($row['emergency_name'] ?? '');

            // Skip if mandatory fields are missing
            if (empty($tenantName) || empty($emergencyName)) {
                continue;
            }

            // Look up the target tenant by matching the user's name via the relationship
            $targetTenant = Tenants::whereHas('user', function ($query) use ($tenantName) {
                $query->where('name', $tenantName);
            })->first();

            if ($targetTenant) {
                // Upsert the emergency contact (updates if it exists, creates if it doesn't)
                $contact = EmergencyContact::updateOrCreate(
                    [
                        'tenant_id' => $targetTenant->id,
                        'name'      => $emergencyName
                    ],
                    [
                        'phone'        => trim($row['phone_number'] ?? null),
                        'relationship' => trim($row['relationship'] ?? ''),
                    ]
                );

                // Track the ID for potential rollback/revert features
                $importedData['emergency_contacts'][] = $contact->id;
            }
        }

        // Save back the updated tracking data to the JSON session file
        Storage::disk('local')->put($sessionPath, json_encode($importedData));
    }
}